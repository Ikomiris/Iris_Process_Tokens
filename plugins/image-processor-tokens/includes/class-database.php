<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe de gestion de la base de données Iris Process
 * 
 * @since 1.0.0
 */
class Iris_Process_Database {
    
    /**
     * Initialise la classe
     * 
     * @since 1.0.0
     */
    public function __construct() {
        // Initialisations de base de données
        add_action('plugins_loaded', array($this, 'check_database_version'));
    }
    
    /**
     * Vérifie et met à jour la version de la base de données
     * 
     * @since 1.0.0
     * @return void
     */
    public function check_database_version() {
        $current_version = get_option('iris_process_db_version', '1.0.0');
        
        if (version_compare($current_version, IRIS_PLUGIN_VERSION, '<')) {
            $this->update_database_schema();
            update_option('iris_process_db_version', IRIS_PLUGIN_VERSION);
        }
    }
    
    /**
     * Met à jour le schéma de la base de données
     * 
     * @since 1.0.0
     * @return void
     */
    private function update_database_schema() {
        global $wpdb;
        
        // Créer/mettre à jour les tables si nécessaire
        if (function_exists('iris_create_tables')) {
            iris_create_tables();
        }
        
        // Migrations spécifiques selon la version
        $this->run_database_migrations();
    }
    
    /**
     * Exécute les migrations de base de données
     * 
     * @since 1.1.0
     * @return void
     */
    private function run_database_migrations() {
        $current_version = get_option('iris_process_db_version', '1.0.0');
        
        // Migration pour v1.1.0 - Ajout des presets JSON
        if (version_compare($current_version, '1.1.0', '<')) {
            $this->migrate_to_json_presets();
        }
    }
    
    /**
     * Migration vers le système de presets JSON
     * 
     * @since 1.1.0
     * @return void
     */
    private function migrate_to_json_presets() {
        global $wpdb;
        
        // Créer la table des presets si elle n'existe pas
        $table_presets = $wpdb->prefix . 'iris_presets';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_presets (
            id int(11) NOT NULL AUTO_INCREMENT,
            preset_name varchar(255) NOT NULL,
            preset_data longtext NOT NULL,
            created_by int(11) NOT NULL,
            is_public tinyint(1) DEFAULT 0,
            is_default tinyint(1) DEFAULT 0,
            usage_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_by (created_by),
            KEY is_public (is_public),
            KEY is_default (is_default)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Créer un preset par défaut si aucun n'existe
        $existing_presets = $wpdb->get_var("SELECT COUNT(*) FROM $table_presets");
        if ($existing_presets == 0) {
            $this->create_default_preset();
        }
        
        iris_log_error('Migration vers presets JSON terminée');
    }
    
    /**
     * Crée le preset par défaut
     * 
     * @since 1.1.0
     * @return void
     */
    private function create_default_preset() {
        global $wpdb;
        
        $default_preset_data = array(
            'exposure' => 0.0,
            'highlights' => -50,
            'shadows' => 50,
            'whites' => 0,
            'blacks' => 0,
            'contrast' => 25,
            'brightness' => 0,
            'saturation' => 10,
            'vibrance' => 20,
            'clarity' => 15,
            'sharpening' => array(
                'amount' => 40,
                'radius' => 1.0,
                'detail' => 25,
                'masking' => 0
            ),
            'noise_reduction' => array(
                'luminance' => 25,
                'color' => 25
            ),
            'color_grading' => array(
                'temperature' => 0,
                'tint' => 0
            )
        );
        
        $table_presets = $wpdb->prefix . 'iris_presets';
        $wpdb->insert(
            $table_presets,
            array(
                'preset_name' => 'Iris Default',
                'preset_data' => json_encode($default_preset_data),
                'created_by' => 1, // Admin user
                'is_public' => 1,
                'is_default' => 1,
                'usage_count' => 0
            ),
            array('%s', '%s', '%d', '%d', '%d', '%d')
        );
        
        iris_log_error('Preset par défaut créé');
    }
    
    /**
     * Nettoie les anciennes données XMP (si nécessaire)
     * 
     * @since 1.1.0
     * @return void
     */
    public function cleanup_legacy_xmp_data() {
        global $wpdb;
        
        // Supprimer les anciennes colonnes XMP si elles existent
        $tables_to_clean = array(
            $wpdb->prefix . 'iris_image_processes',
            $wpdb->prefix . 'iris_processing_jobs'
        );
        
        foreach ($tables_to_clean as $table) {
            // Vérifier si les colonnes XMP existent et les supprimer
            $columns = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE '%xmp%'");
            
            foreach ($columns as $column) {
                $wpdb->query("ALTER TABLE $table DROP COLUMN IF EXISTS `{$column->Field}`");
                iris_log_error("Colonne XMP supprimée: {$column->Field} de $table");
            }
        }
    }
    
    /**
     * Obtient les statistiques de la base de données
     * 
     * @since 1.0.0
     * @return array Statistiques
     */
    public function get_database_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Statistiques des tables principales
        $tables = array(
            'iris_user_tokens' => 'Jetons utilisateurs',
            'iris_token_transactions' => 'Transactions',
            'iris_image_processes' => 'Traitements d\'images',
            'iris_processing_jobs' => 'Jobs de traitement',
            'iris_presets' => 'Presets JSON'
        );
        
        foreach ($tables as $table_suffix => $description) {
            $table_name = $wpdb->prefix . $table_suffix;
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $stats[$table_suffix] = array(
                'description' => $description,
                'count' => intval($count)
            );
        }
        
        return $stats;
    }
    
    /**
     * Optimise les tables de la base de données
     * 
     * @since 1.0.0
     * @return bool Succès de l'opération
     */
    public function optimize_database() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'iris_user_tokens',
            $wpdb->prefix . 'iris_token_transactions', 
            $wpdb->prefix . 'iris_image_processes',
            $wpdb->prefix . 'iris_processing_jobs',
            $wpdb->prefix . 'iris_presets'
        );
        
        $optimized = 0;
        foreach ($tables as $table) {
            $result = $wpdb->query("OPTIMIZE TABLE $table");
            if ($result !== false) {
                $optimized++;
            }
        }
        
        iris_log_error("Optimisation de la base de données : $optimized tables optimisées");
        return $optimized === count($tables);
    }
}