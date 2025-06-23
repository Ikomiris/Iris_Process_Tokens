<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fonctions utilitaires pour Iris Process
 * 
 * @since 1.0.6
 */

/**
 * Fonction wrapper pour l'envoi vers l'API (compatibilité)
 * 
 * @since 1.0.6
 * @param string $file_path Chemin du fichier
 * @param int $user_id ID utilisateur
 * @param int $process_id ID processus
 * @return array|WP_Error Résultat
 */
function iris_send_to_python_api($file_path, $user_id, $process_id) {
    global $iris_api_client;
    
    if (!$iris_api_client) {
        return new WP_Error('api_client_not_initialized', 'Client API non initialisé');
    }
    
    return $iris_api_client->send_to_python_api($file_path, $user_id, $process_id);
}

/**
 * Test rapide de connectivité API
 * 
 * @since 1.0.6
 * @return bool True si l'API est accessible
 */
function iris_is_api_available() {
    global $iris_api_client;
    
    if (!$iris_api_client) {
        return false;
    }
    
    $result = $iris_api_client->test_connectivity();
    return $result['success'];
}

/**
 * Récupère les statistiques API formatées pour l'affichage
 * 
 * @since 1.0.6
 * @return array Statistiques formatées
 */
function iris_get_formatted_api_stats() {
    global $iris_api_client;
    
    if (!$iris_api_client) {
        return array();
    }
    
    $stats = $iris_api_client->get_statistics();
    
    return array(
        'requests_sent' => number_format($stats['requests_sent']),
        'requests_failed' => number_format($stats['requests_failed']),
        'success_rate' => $stats['success_rate'] . '%',
        'most_used_preset' => $stats['most_used_preset'],
        'average_file_size' => size_format($stats['average_file_size']),
        'last_24h_activity' => number_format($stats['last_24h_activity']) . ' requêtes',
        'peak_hour' => $stats['peak_hour']
    );
}

/**
 * Vérifie si le preprocessing est activé
 * 
 * @since 1.0.6
 * @return bool True si activé
 */
function iris_is_preprocessing_enabled() {
    return get_option('iris_auto_preprocessing', 1) == 1;
}

/**
 * Récupère la liste des presets disponibles
 * 
 * @since 1.0.6
 * @return array Liste des presets
 */
function iris_get_available_presets() {
    $upload_dir = wp_upload_dir();
    $presets_dir = $upload_dir['basedir'] . '/iris-presets/';
    $presets = array();
    
    // Presets par défaut
    if (is_dir($presets_dir)) {
        $default_presets = glob($presets_dir . '*.json');
        foreach ($default_presets as $preset_file) {
            if (strpos($preset_file, '/uploads/') === false) {
                $preset_data = json_decode(file_get_contents($preset_file), true);
                $presets[basename($preset_file, '.json')] = array(
                    'name' => $preset_data['name'] ?? basename($preset_file, '.json'),
                    'type' => 'default',
                    'description' => $preset_data['description'] ?? ''
                );
            }
        }
    }
    
    // Presets uploadés
    $uploads_dir = $presets_dir . 'uploads/';
    if (is_dir($uploads_dir)) {
        $uploaded_presets = glob($uploads_dir . '*.json');
        foreach ($uploaded_presets as $preset_file) {
            $preset_data = json_decode(file_get_contents($preset_file), true);
            $presets[basename($preset_file, '.json')] = array(
                'name' => $preset_data['name'] ?? basename($preset_file, '.json'),
                'type' => 'uploaded',
                'description' => $preset_data['description'] ?? '',
                'author' => $preset_data['upload_info']['uploaded_by'] ?? 'Inconnu'
            );
        }
    }
    
    return $presets;
}

/**
 * Valide un fichier image pour le traitement
 * 
 * @since 1.0.6
 * @param string $file_path Chemin du fichier
 * @return true|WP_Error True si valide, WP_Error sinon
 */
function iris_validate_image_file($file_path) {
    if (!file_exists($file_path)) {
        return new WP_Error('file_not_found', 'Fichier non trouvé');
    }
    
    $file_info = pathinfo($file_path);
    $extension = strtolower($file_info['extension']);
    $file_size = filesize($file_path);
    
    // Extensions autorisées
    $allowed_extensions = array('jpg', 'jpeg', 'tif', 'tiff', 'cr2', 'cr3', 'nef', 'arw', 'dng', 'orf', 'raf', 'rw2');
    if (!in_array($extension, $allowed_extensions)) {
        return new WP_Error('invalid_extension', 'Extension non supportée: ' . $extension);
    }
    
    // Taille maximale (100MB)
    $max_size = apply_filters('iris_max_file_size', 100 * 1024 * 1024);
    if ($file_size > $max_size) {
        return new WP_Error('file_too_large', 'Fichier trop volumineux: ' . size_format($file_size));
    }
    
    // Vérification MIME type
    $mime_type = mime_content_type($file_path);
    $allowed_mimes = array(
        'image/jpeg', 'image/tiff', 'image/x-canon-cr2', 'image/x-canon-cr3',
        'image/x-nikon-nef', 'image/x-sony-arw', 'image/x-adobe-dng'
    );
    
    if (!in_array($mime_type, $allowed_mimes)) {
        // Vérification secondaire par extension pour les formats RAW
        $raw_extensions = array('cr2', 'cr3', 'nef', 'arw', 'dng', 'orf', 'raf', 'rw2');
        if (!in_array($extension, $raw_extensions)) {
            return new WP_Error('invalid_mime', 'Type MIME non supporté: ' . $mime_type);
        }
    }
    
    return true;
}

/**
 * Récupère les informations d'un job de traitement
 * 
 * @since 1.0.6
 * @param string $job_id ID du job
 * @return array|null Informations du job ou null
 */
function iris_get_job_info($job_id) {
    global $wpdb;
    
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    $job = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_jobs WHERE job_id = %s",
        $job_id
    ));
    
    if (!$job) {
        return null;
    }
    
    return array(
        'job_id' => $job->job_id,
        'user_id' => $job->user_id,
        'status' => $job->status,
        'original_file' => $job->original_file,
        'file_size' => isset($job->file_size) ? size_format($job->file_size) : 'Inconnu',
        'camera_model' => $job->camera_model ?? 'Inconnu',
        'preset_used' => $job->preset_used ?? 'default',
        'created_at' => $job->created_at,
        'completed_at' => $job->completed_at ?? null,
        'processing_time' => $job->completed_at ? 
            human_time_diff(strtotime($job->created_at), strtotime($job->completed_at)) : null,
        'result_files' => $job->result_files ? json_decode($job->result_files, true) : array()
    );
}

/**
 * Nettoie les anciens jobs et fichiers temporaires
 * 
 * @since 1.0.6
 * @param int $days_old Ancienneté en jours
 * @return int Nombre d'éléments nettoyés
 */
function iris_cleanup_old_data($days_old = 30) {
    global $wpdb;
    
    $cleanup_count = 0;
    
    // Nettoyage des jobs en base
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    $deleted_jobs = $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_jobs WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
        $days_old
    ));
    
    $cleanup_count += $deleted_jobs;
    
    // Nettoyage des fichiers temporaires
    $upload_dir = wp_upload_dir();
    $iris_dir = $upload_dir['basedir'] . '/iris-process/';
    
    if (is_dir($iris_dir)) {
        $files = glob($iris_dir . '*');
        $cutoff_time = time() - ($days_old * 24 * 3600);
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $cleanup_count++;
                }
            }
        }
    }
    
    return $cleanup_count;
}

/**
 * Formate une durée en secondes en texte lisible
 * 
 * @since 1.0.6
 * @param int $seconds Durée en secondes
 * @return string Durée formatée
 */
function iris_format_duration($seconds) {
    if ($seconds < 60) {
        return $seconds . ' secondes';
    } elseif ($seconds < 3600) {
        return round($seconds / 60, 1) . ' minutes';
    } else {
        return round($seconds / 3600, 1) . ' heures';
    }
}

/**
 * Récupère les erreurs récentes de l'API
 * 
 * @since 1.0.6
 * @param int $limit Nombre maximum d'erreurs
 * @return array Liste des erreurs récentes
 */
function iris_get_recent_api_errors($limit = 10) {
    $stats = get_option('iris_api_stats', array());
    
    if (!isset($stats['request_failed']['data'])) {
        return array();
    }
    
    $errors = array_slice(array_reverse($stats['request_failed']['data']), 0, $limit);
    
    foreach ($errors as &$error) {
        $error['formatted_time'] = human_time_diff(strtotime($error['timestamp'])) . ' ago';
    }
    
    return $errors;
}