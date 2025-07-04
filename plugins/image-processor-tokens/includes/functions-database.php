<?php
/**
 * Fonctions de gestion de la base de données
 * 
 * @package IrisProcessTokens
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Création des tables de base de données
 * 
 * @since 1.0.0
 * @since 1.1.0 Ajout table presets JSON
 * @return void
 */
function iris_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Table des jetons utilisateur
    $table_tokens = $wpdb->prefix . 'iris_user_tokens';
    $sql_tokens = "CREATE TABLE IF NOT EXISTS $table_tokens (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        token_balance int(11) DEFAULT 0,
        total_purchased int(11) DEFAULT 0,
        total_used int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) $charset_collate;";
    
    // Table des transactions de jetons
    $table_transactions = $wpdb->prefix . 'iris_token_transactions';
    $sql_transactions = "CREATE TABLE IF NOT EXISTS $table_transactions (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        transaction_type varchar(50) NOT NULL,
        tokens_amount int(11) NOT NULL,
        order_id varchar(100) NULL,
        image_process_id int(11) NULL,
        description text NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // Table des traitements d'images
    $table_processes = $wpdb->prefix . 'iris_image_processes';
    $sql_processes = "CREATE TABLE IF NOT EXISTS $table_processes (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        original_filename varchar(255) NOT NULL,
        file_path varchar(500) NOT NULL,
        status varchar(50) DEFAULT 'uploaded',
        processed_file_path varchar(500) NULL,
        processing_start_time datetime NULL,
        processing_end_time datetime NULL,
        error_message text NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // Table des jobs de traitement API
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    $sql_jobs = "CREATE TABLE IF NOT EXISTS $table_jobs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        job_id varchar(100) NOT NULL,
        user_id bigint(20) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        original_file varchar(255) NOT NULL,
        result_files longtext,
        error_message text,
        api_response longtext,
        preset_id int(11) NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        completed_at datetime,
        PRIMARY KEY (id),
        UNIQUE KEY job_id (job_id),
        KEY user_id (user_id),
        KEY status (status),
        KEY preset_id (preset_id)
    ) $charset_collate;";
    
    // Table des presets JSON administrateur (NOUVEAU v1.1.0)
    $table_admin_presets = $wpdb->prefix . 'iris_admin_presets';
    $sql_admin_presets = "CREATE TABLE IF NOT EXISTS $table_admin_presets (
        id int(11) NOT NULL AUTO_INCREMENT,
        preset_name varchar(255) NOT NULL,
        description text NULL,
        preset_data longtext NOT NULL,
        file_name varchar(255) NOT NULL,
        is_default tinyint(1) DEFAULT 0,
        usage_count int(11) DEFAULT 0,
        created_by varchar(100) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY preset_name (preset_name),
        KEY is_default (is_default)
    ) $charset_collate;";
    
    // Table pour stocker les paramètres de traitement par job (NOUVEAU v1.1.0)
    $table_processing_params = $wpdb->prefix . 'iris_processing_params';
    $sql_processing_params = "CREATE TABLE IF NOT EXISTS $table_processing_params (
        id int(11) NOT NULL AUTO_INCREMENT,
        job_id varchar(100) NOT NULL,
        preset_id int(11) NULL,
        custom_params longtext NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY job_id (job_id),
        KEY preset_id (preset_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_tokens);
    dbDelta($sql_transactions);
    dbDelta($sql_processes);
    dbDelta($sql_jobs);
    dbDelta($sql_admin_presets);
    dbDelta($sql_processing_params);
}

/**
 * Création d'un enregistrement de traitement
 * 
 * @since 1.0.0
 * @param int $user_id ID de l'utilisateur
 * @param string $file_name Nom du fichier
 * @param string $file_path Chemin du fichier
 * @return int ID de l'enregistrement créé
 */
function iris_create_process_record($user_id, $file_name, $file_path) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'iris_image_processes';
    
    // Insertion de l'enregistrement
    $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'original_filename' => $file_name,
            'file_path' => $file_path,
            'status' => 'uploaded',
            'processing_start_time' => current_time('mysql')
        ),
        array('%d', '%s', '%s', '%s', '%s')
    );
    
    return $wpdb->insert_id;
}

/**
 * Nettoyage automatique des anciens jobs
 * 
 * @since 1.0.0
 * @return void
 */
function iris_cleanup_old_jobs() {
    global $wpdb;
    
    // Supprimer les jobs de plus de 30 jours
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}iris_processing_jobs 
         WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
        30
    ));
    
    // Nettoyer les fichiers temporaires
    $upload_dir = wp_upload_dir();
    $iris_dir = $upload_dir['basedir'] . '/iris-process/';
    
    if (is_dir($iris_dir)) {
        $files = glob($iris_dir . '*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > (7 * 24 * 3600)) { // 7 jours
                unlink($file);
            }
        }
    }
}

/**
 * Récupération de l'historique des traitements utilisateur
 * 
 * @since 1.0.0
 * @param int $user_id ID de l'utilisateur
 * @param int $limit Nombre maximum de résultats
 * @return string HTML de l'historique
 */
function iris_get_user_process_history($user_id, $limit = 10) {
    global $wpdb;
    
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    $table_presets = $wpdb->prefix . 'iris_admin_presets';
    
    // MODIFIÉ v1.1.0 - Jointure avec presets
    $jobs = $wpdb->get_results($wpdb->prepare(
        "SELECT j.*, p.preset_name 
         FROM $table_jobs j 
         LEFT JOIN $table_presets p ON j.preset_id = p.id
         WHERE j.user_id = %d 
         ORDER BY j.created_at DESC 
         LIMIT %d",
        $user_id, $limit
    ));
    
    if (empty($jobs)) {
        return '<p style="color: #124C58; text-align: center; padding: 20px; font-family: \'Lato\', sans-serif;">Aucun traitement effectué pour le moment.</p>';
    }
    
    $output = '<div class="iris-history-items">';
    foreach ($jobs as $job) {
        $status_class = 'iris-status-' . $job->status;
        $status_text = iris_get_status_text($job->status);
        
        $output .= '<div class="iris-history-item ' . $status_class . '">';
        $output .= '<div class="iris-history-info">';
        $output .= '<strong>' . esc_html($job->original_file) . '</strong>';
        
        // Afficher le preset utilisé (NOUVEAU v1.1.0)
        if ($job->preset_name) {
            $output .= '<small style="color: #3de9f4; display: block;">🎨 ' . esc_html($job->preset_name) . '</small>';
        }
        
        $output .= '<span class="iris-status">' . $status_text . '</span>';
        $output .= '<span class="iris-date">' . date('d/m/Y H:i', strtotime($job->created_at)) . '</span>';
        $output .= '</div>';
        
        if ($job->status === 'completed' && $job->result_files) {
            $files = json_decode($job->result_files, true);
            if ($files) {
                $output .= '<div class="iris-download">';
                foreach ($files as $file) {
                    $download_url = home_url('/wp-json/iris/v1/download/' . $job->job_id . '/' . basename($file));
                    $output .= '<a href="' . esc_url($download_url) . '" class="iris-download-btn" download>Télécharger ' . esc_html(basename($file)) . '</a>';
                }
                $output .= '</div>';
            }
        }
        
        $output .= '</div>';
    }
    $output .= '</div>';
    
    return $output;
}

/**
 * Conversion du statut en texte lisible
 * 
 * @since 1.0.0
 * @param string $status Statut du job
 * @return string Texte lisible
 */
function iris_get_status_text($status) {
    $statuses = array(
        'pending' => 'En attente',
        'processing' => 'En cours de traitement',
        'completed' => 'Terminé',
        'failed' => 'Erreur'
    );
    
    return isset($statuses[$status]) ? $statuses[$status] : $status;
}