<?php
/**
 * Fonctions de gestion de la base de données
 * 
 * @package IrisProcessTokens
 * @since 1.0.0
 * @version 1.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Création des tables de base de données
 * 
 * @since 1.0.0
 * @since 1.1.0 Ajout table presets JSON
 * @since 1.1.1 Correction des erreurs SQL
 * @return void
 */
function iris_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    try {
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
            UNIQUE KEY user_id (user_id),
            KEY user_id_balance (user_id, token_balance)
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
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY transaction_type (transaction_type),
            KEY created_at (created_at)
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
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
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
            completed_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY job_id (job_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY preset_id (preset_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Table des presets JSON administrateur (v1.1.0)
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
            KEY is_default (is_default),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // Table pour stocker les paramètres de traitement par job (v1.1.0)
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
        
        // Exécution des requêtes
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $results = array();
        $results['tokens'] = dbDelta($sql_tokens);
        $results['transactions'] = dbDelta($sql_transactions);
        $results['processes'] = dbDelta($sql_processes);
        $results['jobs'] = dbDelta($sql_jobs);
        $results['admin_presets'] = dbDelta($sql_admin_presets);
        $results['processing_params'] = dbDelta($sql_processing_params);
        
        // Log des résultats
        iris_log_error('Tables créées avec succès: ' . json_encode(array_keys($results)));
        
        // Créer un preset par défaut si aucun n'existe
        iris_ensure_default_preset();
        
    } catch (Exception $e) {
        iris_log_error('Erreur lors de la création des tables: ' . $e->getMessage());
    }
}

/**
 * S'assurer qu'un preset par défaut existe
 * 
 * @since 1.1.1
 * @return void
 */
function iris_ensure_default_preset() {
    global $wpdb;
    
    $table_presets = $wpdb->prefix . 'iris_admin_presets';
    
    // Vérifier si la table existe
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_presets}'") !== $table_presets) {
        return;
    }
    
    // Vérifier si un preset par défaut existe
    $existing = $wpdb->get_var("SELECT COUNT(*) FROM {$table_presets} WHERE is_default = 1");
    
    if ($existing > 0) {
        return; // Un preset par défaut existe déjà
    }
    
    // Créer le preset par défaut
    $default_preset_data = array(
        'name' => 'Iris Default',
        'version' => '2.1',
        'description' => 'Preset par défaut pour le traitement d\'images Iris',
        'created_with' => 'Iris Process WordPress Plugin v' . IRIS_PLUGIN_VERSION,
        'parameters' => array(
            'exposure' => 0.0,
            'contrast' => 25,
            'highlights' => -50,
            'shadows' => 50,
            'whites' => 0,
            'blacks' => 0,
            'texture' => 15,
            'clarity' => 10,
            'vibrance' => 20,
            'saturation' => 0,
            'sharpening_amount' => 40,
            'sharpening_radius' => 1.0,
            'sharpening_detail' => 25,
            'noise_reduction_luminance' => 25,
            'noise_reduction_color' => 25,
            'temperature' => 0,
            'tint' => 0,
            // Réglages HSL pour chaque couleur
            'hue_red' => 0, 'sat_red' => 0, 'lum_red' => 0,
            'hue_orange' => 0, 'sat_orange' => 0, 'lum_orange' => 0,
            'hue_yellow' => 0, 'sat_yellow' => 0, 'lum_yellow' => 0,
            'hue_green' => 0, 'sat_green' => 0, 'lum_green' => 0,
            'hue_aqua' => 8, 'sat_aqua' => 26, 'lum_aqua' => 0,
            'hue_blue' => 0, 'sat_blue' => 26, 'lum_blue' => 0
        )
    );
    
    $result = $wpdb->insert(
        $table_presets,
        array(
            'preset_name' => 'Iris Default',
            'description' => 'Preset par défaut optimisé pour les images d\'iris',
            'preset_data' => json_encode($default_preset_data),
            'file_name' => 'iris_default.json',
            'is_default' => 1,
            'usage_count' => 0,
            'created_by' => 'System'
        ),
        array('%s', '%s', '%s', '%s', '%d', '%d', '%s')
    );
    
    if ($result) {
        iris_log_error('Preset par défaut créé avec succès');
    }
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
    $result = $wpdb->insert(
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
    
    if ($result === false) {
        iris_log_error('Erreur lors de la création du process record: ' . $wpdb->last_error);
        return 0;
    }
    
    return $wpdb->insert_id;
}

/**
 * Récupération de l'historique des traitements utilisateur
 * 
 * @since 1.0.0
 * @since 1.1.0 Ajout des presets dans l'historique
 * @param int $user_id ID de l'utilisateur
 * @param int $limit Nombre maximum de résultats
 * @return string HTML de l'historique
 */
function iris_get_user_process_history($user_id, $limit = 10) {
    global $wpdb;
    
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    $table_presets = $wpdb->prefix . 'iris_admin_presets';
    
    // Vérifier que les tables existent
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_jobs}'") !== $table_jobs) {
        return '<p style="color: #124C58; text-align: center; padding: 20px;">Aucun historique disponible.</p>';
    }
    
    // Requête sécurisée avec jointure sur les presets
    $jobs = $wpdb->get_results($wpdb->prepare(
        "SELECT j.*, p.preset_name 
         FROM {$table_jobs} j 
         LEFT JOIN {$table_presets} p ON j.preset_id = p.id
         WHERE j.user_id = %d 
         ORDER BY j.created_at DESC 
         LIMIT %d",
        $user_id, 
        $limit
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
        
        // Afficher le preset utilisé (v1.1.0)
        if ($job->preset_name) {
            $output .= '<small style="color: #3de9f4; display: block;">🎨 ' . esc_html($job->preset_name) . '</small>';
        }
        
        $output .= '<span class="iris-status">' . $status_text . '</span>';
        $output .= '<span class="iris-date">' . date('d/m/Y H:i', strtotime($job->created_at)) . '</span>';
        $output .= '</div>';
        
        if ($job->status === 'completed' && $job->result_files) {
            $files = json_decode($job->result_files, true);
            if ($files && is_array($files)) {
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
        'failed' => 'Erreur',
        'uploaded' => 'Uploadé'
    );
    
    return isset($statuses[$status]) ? $statuses[$status] : ucfirst($status);
}

/**
 * Nettoyage automatique des anciens jobs
 * 
 * @since 1.0.0
 * @since 1.1.1 Amélioration de la sécurité
 * @return void
 */
function iris_cleanup_old_jobs() {
    global $wpdb;
    
    try {
        // Supprimer les jobs de plus de 30 jours
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_jobs}'") === $table_jobs) {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table_jobs} 
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                30
            ));
            
            if ($deleted !== false && $deleted > 0) {
                iris_log_error("Nettoyage automatique : {$deleted} jobs supprimés");
            }
        }
        
        // Nettoyer les fichiers temporaires
        $upload_dir = wp_upload_dir();
        $iris_dir = $upload_dir['basedir'] . '/iris-process/';
        
        if (is_dir($iris_dir)) {
            $files = glob($iris_dir . '*');
            $now = time();
            $deleted_files = 0;
            
            foreach ($files as $file) {
                if (is_file($file) && ($now - filemtime($file)) > (7 * 24 * 3600)) { // 7 jours
                    if (unlink($file)) {
                        $deleted_files++;
                    }
                }
            }
            
            if ($deleted_files > 0) {
                iris_log_error("Nettoyage fichiers : {$deleted_files} fichiers supprimés");
            }
        }
        
    } catch (Exception $e) {
        iris_log_error('Erreur lors du nettoyage automatique: ' . $e->getMessage());
    }
}

/**
 * Obtenir les statistiques de la base de données
 * 
 * @since 1.1.1
 * @return array Statistiques
 */
function iris_get_database_stats() {
    global $wpdb;
    
    $stats = array();
    
    $tables = array(
        'iris_user_tokens' => 'Utilisateurs avec jetons',
        'iris_token_transactions' => 'Transactions de jetons',
        'iris_image_processes' => 'Traitements d\'images',
        'iris_processing_jobs' => 'Jobs de traitement',
        'iris_admin_presets' => 'Presets JSON'
    );
    
    foreach ($tables as $table_suffix => $description) {
        $table_name = $wpdb->prefix . $table_suffix;
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            $stats[$table_suffix] = array(
                'description' => $description,
                'count' => intval($count),
                'exists' => true
            );
        } else {
            $stats[$table_suffix] = array(
                'description' => $description,
                'count' => 0,
                'exists' => false
            );
        }
    }
    
    return $stats;
}

/**
 * Vérifier et mettre à jour la version de la base de données
 * 
 * @since 1.1.1
 * @return void
 */
function iris_maybe_update_database() {
    $current_version = get_option('iris_process_db_version', '1.0.0');
    $plugin_version = IRIS_PLUGIN_VERSION;
    
    if (version_compare($current_version, $plugin_version, '<')) {
        iris_log_error("Mise à jour BDD de {$current_version} vers {$plugin_version}");
        
        // Recréer les tables avec les dernières modifications
        iris_create_tables();
        
        // Mettre à jour la version
        update_option('iris_process_db_version', $plugin_version);
        
        iris_log_error("Base de données mise à jour vers la version {$plugin_version}");
    }
}