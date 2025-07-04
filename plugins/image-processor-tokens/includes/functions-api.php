<?php
/**
 * Fonctions d'interaction avec l'API Python
 * 
 * @package IrisProcessTokens
 * @since 1.0.0
 * @version 1.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enregistrement des endpoints REST API
 * 
 * @since 1.0.0
 * @since 1.1.1 Sécurité renforcée
 * @return void
 */
function iris_register_rest_routes() {
    // Callback depuis l'API Python
    register_rest_route('iris/v1', '/callback', array(
        'methods' => 'POST',
        'callback' => 'iris_handle_api_callback',
        'permission_callback' => '__return_true', // Publique mais avec validation interne
        'args' => array(
            'job_id' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'status' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'user_id' => array(
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            )
        )
    ));
    
    // Statut d'un job
    register_rest_route('iris/v1', '/status/(?P<job_id>[a-zA-Z0-9_\-]+)', array(
        'methods' => 'GET',
        'callback' => 'iris_get_job_status_api',
        'permission_callback' => 'iris_check_job_access_permission',
        'args' => array(
            'job_id' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            )
        )
    ));
    
    // Statistiques (admin seulement)
    register_rest_route('iris/v1', '/stats', array(
        'methods' => 'GET',
        'callback' => 'iris_get_stats_api',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
    
    // Téléchargement de fichiers
    register_rest_route('iris/v1', '/download/(?P<job_id>[a-zA-Z0-9_\-]+)/(?P<filename>[^/]+)', array(
        'methods' => 'GET',
        'callback' => 'iris_download_file_api',
        'permission_callback' => 'iris_check_download_permission',
        'args' => array(
            'job_id' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'filename' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_file_name'
            )
        )
    ));
    
    // Test de santé de l'API
    register_rest_route('iris/v1', '/health', array(
        'methods' => 'GET',
        'callback' => 'iris_health_check_api',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
}

/**
 * Callback depuis l'API Python
 * 
 * @since 1.0.0
 * @since 1.1.1 Validation et sécurité renforcées
 * @param WP_REST_Request $request Requête REST
 * @return WP_REST_Response|WP_Error Réponse ou erreur
 */
function iris_handle_api_callback($request) {
    global $wpdb;
    
    $data = $request->get_json_params();
    
    // Validation des données requises
    if (!isset($data['job_id']) || !isset($data['status']) || !isset($data['user_id'])) {
        return new WP_Error('missing_data', 'Données requises manquantes', array('status' => 400));
    }
    
    $job_id = sanitize_text_field($data['job_id']);
    $status = sanitize_text_field($data['status']);
    $user_id = intval($data['user_id']);
    
    // Validation du statut
    $valid_statuses = array('pending', 'processing', 'completed', 'failed');
    if (!in_array($status, $valid_statuses)) {
        return new WP_Error('invalid_status', 'Statut invalide', array('status' => 400));
    }
    
    // Vérifier que le job existe
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    $existing_job = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_jobs} WHERE job_id = %s",
        $job_id
    ));
    
    if (!$existing_job) {
        return new WP_Error('job_not_found', 'Job non trouvé', array('status' => 404));
    }
    
    // Vérifier que l'user_id correspond
    if ($existing_job->user_id != $user_id) {
        return new WP_Error('user_mismatch', 'Utilisateur incorrect', array('status' => 403));
    }
    
    // Préparer les données de mise à jour
    $update_data = array(
        'status' => $status,
        'updated_at' => current_time('mysql')
    );
    
    $update_format = array('%s', '%s');
    
    // Traitement selon le statut
    if ($status === 'completed') {
        $update_data['completed_at'] = current_time('mysql');
        $update_format[] = '%s';
        
        // Gérer les fichiers de résultat
        if (isset($data['result_files']) && is_array($data['result_files'])) {
            $update_data['result_files'] = json_encode($data['result_files']);
            $update_format[] = '%s';
        }
        
        // Décompter un jeton pour l'utilisateur
        if (class_exists('Token_Manager')) {
            Token_Manager::use_token($user_id, 0);
        }
        
        // Déclencher les hooks de completion
        iris_trigger_job_completion_hooks($job_id, $status, $user_id);
        
        iris_log_error("Job {$job_id} terminé avec succès pour utilisateur {$user_id}");
        
    } elseif ($status === 'failed') {
        $error_message = isset($data['error']) ? sanitize_textarea_field($data['error']) : 'Erreur inconnue';
        $update_data['error_message'] = $error_message;
        $update_format[] = '%s';
        
        iris_log_error("Job {$job_id} échoué - {$error_message}");
        
    } elseif ($status === 'processing') {
        iris_log_error("Job {$job_id} en cours de traitement");
    }
    
    // Mise à jour en base de données
    $result = $wpdb->update(
        $table_jobs,
        $update_data,
        array('job_id' => $job_id),
        $update_format,
        array('%s')
    );
    
    if ($result === false) {
        return new WP_Error('update_failed', 'Échec de la mise à jour', array('status' => 500));
    }
    
    return rest_ensure_response(array(
        'status' => 'ok', 
        'message' => 'Callback traité avec succès',
        'job_id' => $job_id,
        'new_status' => $status
    ));
}

/**
 * Statut d'un job via API REST
 * 
 * @since 1.0.0
 * @since 1.1.1 Contrôle d'accès amélioré
 * @param WP_REST_Request $request Requête REST
 * @return WP_REST_Response|WP_Error Réponse ou erreur
 */
function iris_get_job_status_api($request) {
    global $wpdb;
    
    $job_id = $request->get_param('job_id');
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    
    $job = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_jobs} WHERE job_id = %s",
        $job_id
    ));
    
    if (!$job) {
        return new WP_Error('job_not_found', 'Job non trouvé', array('status' => 404));
    }
    
    // Vérifier l'accès utilisateur (sauf pour les admins)
    if (!current_user_can('manage_options')) {
        $current_user_id = get_current_user_id();
        if (!$current_user_id || $job->user_id != $current_user_id) {
            return new WP_Error('access_denied', 'Accès refusé', array('status' => 403));
        }
    }
    
    $response_data = array(
        'job_id' => $job->job_id,
        'status' => $job->status,
        'original_file' => $job->original_file,
        'created_at' => $job->created_at,
        'updated_at' => $job->updated_at,
        'completed_at' => $job->completed_at,
        'progress' => iris_calculate_job_progress($job)
    );
    
    // Ajouter les fichiers de résultat si disponibles
    if ($job->result_files) {
        $files = json_decode($job->result_files, true);
        if (is_array($files)) {
            $response_data['result_files'] = $files;
        }
    }
    
    // Ajouter le message d'erreur si applicable
    if ($job->status === 'failed' && $job->error_message) {
        $response_data['error_message'] = $job->error_message;
    }
    
    return rest_ensure_response($response_data);
}

/**
 * Calcul du progrès d'un job
 * 
 * @since 1.1.1
 * @param object $job Données du job
 * @return int Pourcentage de progression
 */
function iris_calculate_job_progress($job) {
    switch ($job->status) {
        case 'pending':
            return 0;
        case 'processing':
            // Estimation basée sur le temps écoulé
            if ($job->created_at) {
                $elapsed = time() - strtotime($job->created_at);
                $estimated_total = 300; // 5 minutes estimation
                return min(90, intval(($elapsed / $estimated_total) * 100));
            }
            return 50;
        case 'completed':
            return 100;
        case 'failed':
            return 0;
        default:
            return 0;
    }
}

/**
 * API REST pour les statistiques (admin)
 * 
 * @since 1.0.0
 * @since 1.1.1 Statistiques enrichies
 * @return WP_REST_Response Réponse avec les statistiques
 */
function iris_get_stats_api() {
    global $wpdb;
    
    $table_tokens = $wpdb->prefix . 'iris_user_tokens';
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    $table_presets = $wpdb->prefix . 'iris_admin_presets';
    
    $stats = array(
        'users' => array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_tokens}") ?: 0,
            'active_last_30_days' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$table_jobs} WHERE created_at > DATE_SUB(NOW(), INTERVAL %d DAY)",
                30
            )) ?: 0
        ),
        'jobs' => array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_jobs}") ?: 0,
            'completed' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_jobs} WHERE status = 'completed'") ?: 0,
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_jobs} WHERE status IN ('pending', 'processing')") ?: 0,
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_jobs} WHERE status = 'failed'") ?: 0,
            'today' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_jobs} WHERE DATE(created_at) = CURDATE()") ?: 0
        ),
        'tokens' => array(
            'total_purchased' => $wpdb->get_var("SELECT SUM(total_purchased) FROM {$table_tokens}") ?: 0,
            'total_used' => $wpdb->get_var("SELECT SUM(total_used) FROM {$table_tokens}") ?: 0,
            'current_balance' => $wpdb->get_var("SELECT SUM(token_balance) FROM {$table_tokens}") ?: 0
        ),
        'presets' => array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_presets}") ?: 0,
            'most_used' => $wpdb->get_row("SELECT preset_name, usage_count FROM {$table_presets} ORDER BY usage_count DESC LIMIT 1")
        ),
        'system' => array(
            'api_url' => IRIS_API_URL,
            'plugin_version' => IRIS_PLUGIN_VERSION,
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'last_cleanup' => get_option('iris_last_cleanup', 'Jamais')
        )
    );
    
    return rest_ensure_response($stats);
}

/**
 * Envoi vers l'API Python (MODIFIÉ v1.1.0 pour presets)
 * 
 * @since 1.0.0
 * @since 1.1.0 Ajout support presets JSON
 * @since 1.1.1 Retry logic et timeouts adaptatifs
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
    
    // Récupérer le preset à appliquer (v1.1.0)
    $preset_data = null;
    if ($preset_id && function_exists('iris_get_preset_by_id')) {
        $preset_data = iris_get_preset_by_id($preset_id);
    } elseif (function_exists('iris_get_default_preset')) {
        // Utiliser le preset par défaut
        $preset_data = iris_get_default_preset();
    }
    
    // Calculer timeout adaptatif basé sur la taille du fichier
    $file_size = filesize($file_path);
    $timeout = max(60, min(300, intval($file_size / (1024 * 1024)) * 10)); // 10s par MB, min 60s, max 300s
    
    // Préparer le fichier pour l'upload
    $curl_file = new CURLFile($file_path, mime_content_type($file_path), basename($file_path));
    
    // Données pour l'API (v1.1.0)
    $post_data = array(
        'file' => $curl_file,
        'user_id' => $user_id,
        'callback_url' => $callback_url,
        'processing_options' => json_encode(array(
            'use_preset' => $preset_data !== null,
            'preset_data' => $preset_data,
            'preset_format' => 'iris_json_v2',
            'quality' => 'high',
            'output_format' => 'tiff'
        ))
    );
    
    // Retry logic avec backoff exponentiel
    $max_retries = 3;
    $retry_count = 0;
    $last_error = null;
    
    while ($retry_count < $max_retries) {
        try {
            // Configuration cURL avec options robustes
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $api_url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post_data,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_HTTPHEADER => array(
                    'Accept: application/json',
                    'User-Agent: Iris-Process-WordPress/' . IRIS_PLUGIN_VERSION
                ),
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_SSL_VERIFYPEER => false, // Pour les environnements de dev
                CURLOPT_PROGRESSFUNCTION => 'iris_curl_progress_callback',
                CURLOPT_NOPROGRESS => false
            ));
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                throw new Exception('Erreur cURL: ' . $curl_error);
            }
            
            if ($http_code !== 200) {
                throw new Exception("Erreur HTTP: {$http_code} - {$response}");
            }
            
            $result = json_decode($response, true);
            if (!$result) {
                throw new Exception('Réponse JSON invalide: ' . $response);
            }
            
            if (!isset($result['job_id'])) {
                throw new Exception('Job ID manquant dans la réponse API');
            }
            
            // Succès - enregistrer le job en base de données
            $job_id = $result['job_id'];
            $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
            
            $insert_result = $wpdb->insert(
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
            
            if ($insert_result === false) {
                iris_log_error('Erreur insertion job en BDD: ' . $wpdb->last_error);
            }
            
            // Sauvegarder les paramètres de traitement (v1.1.0)
            if ($preset_data && function_exists('iris_save_processing_params')) {
                iris_save_processing_params($job_id, $preset_id, $preset_data);
            }
            
            iris_log_error("Job {$job_id} créé avec succès pour utilisateur {$user_id}" . ($preset_data ? ' avec preset' : ''));
            
            return array(
                'success' => true,
                'job_id' => $job_id,
                'message' => $result['message'] ?? 'Traitement démarré avec succès',
                'preset_applied' => $preset_data !== null,
                'estimated_time' => $result['estimated_time'] ?? 300
            );
            
        } catch (Exception $e) {
            $last_error = $e->getMessage();
            $retry_count++;
            
            iris_log_error("Tentative {$retry_count}/{$max_retries} échouée: {$last_error}");
            
            if ($retry_count < $max_retries) {
                // Backoff exponentiel: 2^retry_count secondes
                sleep(pow(2, $retry_count));
            }
        }
    }
    
    // Toutes les tentatives ont échoué
    iris_log_error("Échec définitif après {$max_retries} tentatives: {$last_error}");
    return new WP_Error('api_error', 'Erreur API après ' . $max_retries . ' tentatives: ' . $last_error);
}

/**
 * Callback de progression cURL
 * 
 * @since 1.1.1
 * @param resource $resource cURL resource
 * @param int $download_size Taille totale de téléchargement
 * @param int $downloaded Déjà téléchargé
 * @param int $upload_size Taille totale d'upload
 * @param int $uploaded Déjà uploadé
 * @return int 0 pour continuer
 */
function iris_curl_progress_callback($resource, $download_size, $downloaded, $upload_size, $uploaded) {
    if ($upload_size > 0 && $uploaded > 0) {
        $progress = intval(($uploaded / $upload_size) * 100);
        // On pourrait stocker le progrès en base ou déclencher un hook
        iris_log_error("Upload progress: {$progress}%");
    }
    return 0; // Continuer
}

/**
 * Fonction pour tester la connexion API
 * 
 * @since 1.0.0
 * @since 1.1.1 Test plus robuste
 * @return array Résultat du test
 */
function iris_test_api_connection() {
    $health_url = IRIS_API_URL . '/health';
    
    $response = wp_remote_get($health_url, array(
        'timeout' => 15,
        'sslverify' => false,
        'headers' => array(
            'User-Agent' => 'Iris-Process-WordPress/' . IRIS_PLUGIN_VERSION
        )
    ));
    
    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => 'Erreur de connexion: ' . $response->get_error_message(),
            'code' => $response->get_error_code()
        );
    }
    
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if ($code === 200) {
        $data = json_decode($body, true);
        return array(
            'success' => true,
            'message' => 'API accessible et fonctionnelle',
            'data' => $data,
            'response_time' => $data['response_time'] ?? 'N/A'
        );
    } else {
        return array(
            'success' => false,
            'message' => "Erreur HTTP: {$code}",
            'code' => $code,
            'body' => $body
        );
    }
}

/**
 * Vérification des permissions pour l'accès aux jobs
 * 
 * @since 1.1.1
 * @param WP_REST_Request $request Requête REST
 * @return bool Permission accordée
 */
function iris_check_job_access_permission($request) {
    // Les admins ont accès à tout
    if (current_user_can('manage_options')) {
        return true;
    }
    
    // Les utilisateurs connectés ont accès à leurs propres jobs
    $user_id = get_current_user_id();
    if (!$user_id) {
        return false;
    }
    
    // La vérification spécifique se fait dans la fonction API
    return true;
}

/**
 * Vérification des permissions pour le téléchargement
 * 
 * @since 1.1.1
 * @param WP_REST_Request $request Requête REST
 * @return bool Permission accordée
 */
function iris_check_download_permission($request) {
    return is_user_logged_in();
}

/**
 * API de téléchargement de fichiers
 * 
 * @since 1.1.1
 * @param WP_REST_Request $request Requête REST
 * @return WP_REST_Response|WP_Error Réponse ou erreur
 */
function iris_download_file_api($request) {
    $job_id = $request->get_param('job_id');
    $filename = $request->get_param('filename');
    $user_id = get_current_user_id();
    
    global $wpdb;
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    
    // Vérifier le job et l'accès
    $job = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_jobs} WHERE job_id = %s",
        $job_id
    ));
    
    if (!$job) {
        return new WP_Error('job_not_found', 'Job non trouvé', array('status' => 404));
    }
    
    // Vérifier l'accès utilisateur
    if (!current_user_can('manage_options') && $job->user_id != $user_id) {
        return new WP_Error('access_denied', 'Accès refusé', array('status' => 403));
    }
    
    // Récupérer les fichiers de résultat
    if (!$job->result_files) {
        return new WP_Error('no_files', 'Aucun fichier de résultat disponible', array('status' => 404));
    }
    
    $result_files = json_decode($job->result_files, true);
    if (!is_array($result_files)) {
        return new WP_Error('invalid_files', 'Fichiers de résultat invalides', array('status' => 500));
    }
    
    // Trouver le fichier demandé
    $file_path = null;
    foreach ($result_files as $file) {
        if (basename($file) === $filename) {
            $file_path = $file;
            break;
        }
    }
    
    if (!$file_path || !file_exists($file_path)) {
        return new WP_Error('file_not_found', 'Fichier non trouvé', array('status' => 404));
    }
    
    // Rediriger vers l'ancien système de téléchargement sécurisé
    $download_nonce = wp_create_nonce('iris_download_' . $job->id);
    $download_url = admin_url('admin-ajax.php') . '?action=iris_download&process_id=' . $job->id . '&nonce=' . $download_nonce;
    
    return rest_ensure_response(array(
        'download_url' => $download_url,
        'filename' => $filename,
        'file_size' => filesize($file_path)
    ));
}

/**
 * Check de santé de l'API
 * 
 * @since 1.1.1
 * @return WP_REST_Response Statut de santé
 */
function iris_health_check_api() {
    $health = array(
        'status' => 'ok',
        'timestamp' => current_time('mysql'),
        'version' => IRIS_PLUGIN_VERSION,
        'database' => iris_check_database_health(),
        'python_api' => iris_test_api_connection(),
        'storage' => iris_check_storage_health()
    );
    
    return rest_ensure_response($health);
}

/**
 * Vérification de la santé de la base de données
 * 
 * @since 1.1.1
 * @return array Statut de la BDD
 */
function iris_check_database_health() {
    global $wpdb;
    
    $tables = array(
        'iris_user_tokens',
        'iris_processing_jobs',
        'iris_admin_presets'
    );
    
    $health = array('status' => 'ok', 'tables' => array());
    
    foreach ($tables as $table) {
        $full_name = $wpdb->prefix . $table;
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_name}'") === $full_name;
        $health['tables'][$table] = $exists ? 'ok' : 'missing';
        
        if (!$exists) {
            $health['status'] = 'warning';
        }
    }
    
    return $health;
}

/**
 * Vérification de la santé du stockage
 * 
 * @since 1.1.1
 * @return array Statut du stockage
 */
function iris_check_storage_health() {
    $upload_dir = wp_upload_dir();
    $iris_dir = $upload_dir['basedir'] . '/iris-process';
    
    $health = array(
        'status' => 'ok',
        'upload_dir_writable' => is_writable($upload_dir['basedir']),
        'iris_dir_exists' => is_dir($iris_dir),
        'iris_dir_writable' => is_dir($iris_dir) ? is_writable($iris_dir) : false,
        'free_space' => disk_free_space($upload_dir['basedir'])
    );
    
    // Vérifier les problèmes
    if (!$health['upload_dir_writable'] || !$health['iris_dir_writable']) {
        $health['status'] = 'error';
    }
    
    if (!$health['iris_dir_exists']) {
        $health['status'] = 'warning';
    }
    
    return $health;
}

/**
 * Déclencher les hooks de completion de job
 * 
 * @since 1.1.1
 * @param string $job_id ID du job
 * @param string $status Statut final
 * @param int $user_id ID de l'utilisateur
 * @return void
 */
function iris_trigger_job_completion_hooks($job_id, $status, $user_id) {
    // Hook générique
    do_action('iris_job_completed', $user_id, $job_id, $status);
    
    // Hooks spécifiques au statut
    if ($status === 'completed') {
        do_action('iris_job_success', $user_id, $job_id);
    } elseif ($status === 'failed') {
        do_action('iris_job_failed', $user_id, $job_id);
    }
    
    // Hook pour les notifications
    do_action('iris_send_notification', $user_id, $job_id, $status);
}

/**
 * AJAX pour tester l'API depuis l'admin
 * 
 * @since 1.1.1
 * @return void
 */
function iris_ajax_test_api() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission insuffisante');
        return;
    }
    
    $result = iris_test_api_connection();
    
    if ($result['success']) {
        wp_send_json_success(array(
            'message' => $result['message'],
            'details' => $result['data'] ?? null,
            'response_time' => $result['response_time'] ?? 'N/A'
        ));
    } else {
        wp_send_json_error(array(
            'message' => $result['message'],
            'code' => $result['code'] ?? 'unknown',
            'details' => $result['body'] ?? null
        ));
    }
}