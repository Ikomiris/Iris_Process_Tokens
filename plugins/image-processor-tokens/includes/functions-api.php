<?php
/**
 * Fonctions d'interaction avec l'API Python
 * 
 * @package IrisProcessTokens
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enregistrement des endpoints REST API
 * 
 * @since 1.0.0
 * @return void
 */
function iris_register_rest_routes() {
    register_rest_route('iris/v1', '/callback', array(
        'methods' => 'POST',
        'callback' => 'iris_handle_api_callback',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('iris/v1', '/status/(?P<job_id>[a-zA-Z0-9_]+)', array(
        'methods' => 'GET',
        'callback' => 'iris_get_job_status_api',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('iris/v1', '/stats', array(
        'methods' => 'GET',
        'callback' => 'iris_get_stats_api',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
}

/**
 * Callback depuis l'API Python
 * 
 * @since 1.0.0
 * @param WP_REST_Request $request Requête REST
 * @return WP_REST_Response|WP_Error Réponse ou erreur
 */
function iris_handle_api_callback($request) {
    global $wpdb;
    
    $data = $request->get_json_params();
    
    if (!isset($data['job_id'])) {
        return new WP_Error('missing_data', 'Job ID manquant', array('status' => 400));
    }
    
    $job_id = sanitize_text_field($data['job_id']);
    $status = sanitize_text_field($data['status']);
    $user_id = intval($data['user_id']);
    
    // Mettre à jour le job en base
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    $update_data = array(
        'status' => $status,
        'updated_at' => current_time('mysql')
    );
    
    if ($status === 'completed') {
        $update_data['completed_at'] = current_time('mysql');
        if (isset($data['result_files'])) {
            $update_data['result_files'] = json_encode($data['result_files']);
        }
        
        // Décompter un jeton pour l'utilisateur
        Token_Manager::use_token($user_id, 0);
        
        // Déclencher les hooks de completion
        iris_trigger_job_completion_hooks($job_id, $status, $user_id);
        
        // Log d'activité
        iris_log_error("Job $job_id terminé pour utilisateur $user_id");
        
    } elseif ($status === 'failed') {
        $update_data['error_message'] = isset($data['error']) ? sanitize_text_field($data['error']) : 'Erreur inconnue';
        iris_log_error("Job $job_id échoué - " . $update_data['error_message']);
    }
    
    $wpdb->update(
        $table_jobs,
        $update_data,
        array('job_id' => $job_id),
        array('%s', '%s'),
        array('%s')
    );
    
    return rest_ensure_response(array('status' => 'ok', 'message' => 'Callback traité'));
}

/**
 * Statut d'un job via API REST
 * 
 * @since 1.0.0
 * @param WP_REST_Request $request Requête REST
 * @return WP_REST_Response|WP_Error Réponse ou erreur
 */
function iris_get_job_status_api($request) {
    global $wpdb;
    
    $job_id = $request->get_param('job_id');
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    
    $job = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_jobs WHERE job_id = %s",
        $job_id
    ));
    
    if (!$job) {
        return new WP_Error('job_not_found', 'Job non trouvé', array('status' => 404));
    }
    
    return rest_ensure_response(array(
        'job_id' => $job->job_id,
        'status' => $job->status,
        'created_at' => $job->created_at,
        'completed_at' => $job->completed_at,
        'result_files' => $job->result_files ? json_decode($job->result_files, true) : []
    ));
}

/**
 * API REST pour les statistiques (pour l'admin)
 * 
 * @since 1.0.0
 * @return WP_REST_Response Réponse avec les statistiques
 */
function iris_get_stats_api() {
    global $wpdb;
    
    $table_tokens = $wpdb->prefix . 'iris_user_tokens';
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    $table_presets = $wpdb->prefix . 'iris_admin_presets';
    
    $stats = array(
        'total_users' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tokens"),
        'total_jobs' => $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs"),
        'completed_jobs' => $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status = 'completed'"),
        'pending_jobs' => $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status IN ('pending', 'processing')"),
        'failed_jobs' => $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status = 'failed'"),
        'total_tokens_purchased' => $wpdb->get_var("SELECT SUM(total_purchased) FROM $table_tokens"),
        'total_tokens_used' => $wpdb->get_var("SELECT SUM(total_used) FROM $table_tokens"),
        'total_presets' => $wpdb->get_var("SELECT COUNT(*) FROM $table_presets"),
        'api_url' => IRIS_API_URL
    );
    
    return rest_ensure_response($stats);
}

/**
 * Envoi vers l'API Python (MODIFIÉ v1.1.0 pour presets)
 * 
 * @since 1.0.0
 * @since 1.1.0 Ajout support presets JSON
 * @param string $file_path Chemin du fichier
 * @param int $user_id ID de l'utilisateur
 * @param int $process_id ID du processus
 * @param int|null $preset_id ID du preset à appliquer
 * @return array|WP_Error Résultat de l'API ou erreur
 */
function iris_send_to_python_api($file_path, $user_id, $process_id, $preset_id = null) {
    global $wpdb;
    
    // URL de l'API Python
    $api_url = IRIS_API_URL . '/process';
    $callback_url = home_url('/wp-json/iris/v1/callback');
    
    // Vérifier que le fichier existe
    if (!file_exists($file_path)) {
        return new WP_Error('file_not_found', 'Fichier non trouvé: ' . $file_path);
    }
    
    // Récupérer le preset à appliquer (NOUVEAU v1.1.0)
    $preset_data = null;
    if ($preset_id) {
        $preset_data = iris_get_preset_by_id($preset_id);
    } else {
        // Utiliser le preset par défaut
        $preset_data = iris_get_default_preset();
    }
    
    // Préparer le fichier pour l'upload
    $curl_file = new CURLFile($file_path, mime_content_type($file_path), basename($file_path));
    
    // Données pour l'API (MODIFIÉ v1.1.0)
    $post_data = array(
        'file' => $curl_file,
        'user_id' => $user_id,
        'callback_url' => $callback_url,
        'processing_options' => json_encode(array(
            'use_preset' => $preset_data !== null,
            'preset_data' => $preset_data,
            'preset_format' => 'iris_json_v2'
        ))
    );
    
    try {
        // Configuration cURL
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $api_url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post_data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => array('Accept: application/json')
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Erreur cURL: ' . $error);
        }
        
        if ($http_code !== 200) {
            throw new Exception('Erreur HTTP: ' . $http_code . ' - ' . $response);
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            throw new Exception('Réponse JSON invalide');
        }
        
        // Enregistrer le job en base de données (MODIFIÉ v1.1.0)
        $job_id = $result['job_id'];
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        $wpdb->insert(
            $table_jobs,
            array(
                'job_id' => $job_id,
                'user_id' => $user_id,
                'status' => 'pending',
                'original_file' => basename($file_path),
                'preset_id' => $preset_id,
                'created_at' => current_time('mysql'),
                'api_response' => $response
            ),
            array('%s', '%d', '%s', '%s', '%d', '%s', '%s')
        );
        
        // Sauvegarder les paramètres de traitement (NOUVEAU v1.1.0)
        if ($preset_data) {
            iris_save_processing_params($job_id, $preset_id, $preset_data);
        }
        
        iris_log_error("Job $job_id créé pour utilisateur $user_id avec preset: " . ($preset_data ? 'Oui' : 'Non'));
        
        return array(
            'success' => true,
            'job_id' => $job_id,
            'message' => $result['message'],
            'preset_applied' => $preset_data !== null
        );
        
    } catch (Exception $e) {
        iris_log_error('Iris API Error: ' . $e->getMessage());
        return new WP_Error('api_error', 'Erreur API: ' . $e->getMessage());
    }
}

/**
 * Fonction pour tester la connexion API (utilitaire)
 * 
 * @since 1.0.0
 * @return array Résultat du test
 */
function iris_test_api_connection() {
    $response = wp_remote_get(IRIS_API_URL . '/health', array(
        'timeout' => 10,
        'sslverify' => false
    ));
    
    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => $response->get_error_message()
        );
    }
    
    $code = wp_remote_retrieve_response_code($response);
    if ($code === 200) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return array(
            'success' => true,
            'message' => 'API accessible',
            'data' => $body
        );
    } else {
        return array(
            'success' => false,
            'message' => "Erreur HTTP: $code"
        );
    }
}

/**
 * Ajout d'un endpoint pour vérifier l'état de l'API
 * 
 * @since 1.0.0
 * @return void
 */
function iris_ajax_test_api() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission insuffisante');
    }
    
    $result = iris_test_api_connection();
    
    if ($result['success']) {
        wp_send_json_success($result['message']);
    } else {
        wp_send_json_error($result['message']);
    }
}