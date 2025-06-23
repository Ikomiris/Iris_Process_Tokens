<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour gérer les presets Lightroom via l'interface WordPress
 * 
 * @since 1.0.6
 */
class Iris_Preset_Manager {
    
    private $presets_dir;
    private $uploads_dir;
    private $archives_dir;
    private $capability = 'manage_options';
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->presets_dir = $upload_dir['basedir'] . '/iris-presets/';
        $this->uploads_dir = $this->presets_dir . 'uploads/';
        $this->archives_dir = $this->uploads_dir . 'archives/';
        
        $this->init_hooks();
        $this->ensure_directories();
        $this->create_default_presets();
    }
    
    /**
     * Initialisation des hooks WordPress
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_preset_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_iris_upload_preset', array($this, 'handle_preset_upload'));
        add_action('wp_ajax_iris_delete_preset', array($this, 'handle_preset_delete'));
        add_action('wp_ajax_iris_test_preset', array($this, 'handle_preset_test'));
        add_action('wp_ajax_iris_clear_preset_cache', array($this, 'handle_clear_cache'));
        add_action('wp_ajax_iris_preset_export', array($this, 'handle_preset_export'));
        add_action('wp_ajax_iris_preview_xmp', array($this, 'handle_preview_xmp'));
        
        // Settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Cron pour nettoyage
        add_action('iris_preset_cleanup', array($this, 'cleanup_old_files'));
        if (!wp_next_scheduled('iris_preset_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'iris_preset_cleanup');
        }
    }
    
    /**
     * Création des répertoires nécessaires
     */
    private function ensure_directories() {
        $directories = array(
            $this->presets_dir,
            $this->uploads_dir,
            $this->archives_dir
        );
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
        }
        
        // Création du fichier .htaccess pour sécuriser
        $htaccess_content = "Options -Indexes\n";
        $htaccess_content .= "<Files \"*.json\">\n";
        $htaccess_content .= "    Order allow,deny\n";
        $htaccess_content .= "    Allow from all\n";
        $htaccess_content .= "</Files>\n";
        $htaccess_content .= "<Files \"*.xmp\">\n";
        $htaccess_content .= "    Order allow,deny\n";
        $htaccess_content .= "    Deny from all\n";
        $htaccess_content .= "</Files>\n";
        
        file_put_contents($this->presets_dir . '.htaccess', $htaccess_content);
        
        // Fichier index.php pour éviter le listing
        file_put_contents($this->presets_dir . 'index.php', '<?php // Silence is golden');
        file_put_contents($this->uploads_dir . 'index.php', '<?php // Silence is golden');
    }
    
    /**
     * Création des presets par défaut
     */
    private function create_default_presets() {
        $default_preset_file = $this->presets_dir . 'canon_eos_r.json';
        
        if (!file_exists($default_preset_file)) {
            $canon_eos_r_preset = array(
                'name' => 'Iris - Canon EOS R',
                'description' => 'Preset par défaut pour Canon EOS R optimisé pour les photos d\'iris',
                'version' => '1.0',
                'author' => 'Iris4Pro',
                'camera_models' => array('Canon EOS R', 'EOS R'),
                'raw_params' => array(
                    'white_balance' => 'custom',
                    'temperature' => 4726,
                    'tint' => -2,
                    'output_color' => 'adobe',
                    'output_bps' => 16,
                    'gamma' => array(2.2, 4.5),
                    'no_auto_bright' => true,
                    'noise_thr' => 100,
                    'use_camera_wb' => false
                ),
                'tone_adjustments' => array(
                    'exposure' => 0.10,
                    'contrast' => 0.13,
                    'highlights' => -0.93,
                    'shadows' => 1.0,
                    'whites' => 0.0,
                    'blacks' => 0.0,
                    'texture' => 1.0,
                    'clarity' => 0.15,
                    'dehaze' => 0.0,
                    'vibrance' => 0.0,
                    'saturation' => 0.0
                ),
                'color_adjustments' => array(
                    'hue_adjustments' => array(
                        'red' => 0, 'orange' => -4, 'yellow' => -5, 'green' => 14,
                        'aqua' => 6, 'blue' => 0, 'purple' => 0, 'magenta' => 0
                    ),
                    'saturation_adjustments' => array(
                        'red' => 0, 'orange' => -4, 'yellow' => 3, 'green' => 0,
                        'aqua' => 8, 'blue' => 26, 'purple' => 0, 'magenta' => 0
                    ),
                    'luminance_adjustments' => array(
                        'red' => 0, 'orange' => 2, 'yellow' => 0, 'green' => 0,
                        'aqua' => 0, 'blue' => 0, 'purple' => 0, 'magenta' => 0
                    )
                ),
                'detail' => array(
                    'sharpness' => 40,
                    'sharpen_radius' => 1.0,
                    'sharpen_detail' => 25,
                    'noise_reduction' => 25
                ),
                'created_at' => current_time('mysql'),
                'type' => 'default'
            );
            
            file_put_contents($default_preset_file, json_encode($canon_eos_r_preset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        
        // Preset par défaut générique
        $default_generic_file = $this->presets_dir . 'default.json';
        
        if (!file_exists($default_generic_file)) {
            $generic_preset = array(
                'name' => 'Iris - Preset par défaut',
                'description' => 'Preset générique pour tous types d\'appareils photo',
                'version' => '1.0',
                'author' => 'Iris4Pro',
                'camera_models' => array(),
                'raw_params' => array(
                    'white_balance' => 'camera',
                    'temperature' => 5500,
                    'tint' => 0,
                    'output_color' => 'adobe',
                    'output_bps' => 16,
                    'gamma' => array(2.2, 4.5),
                    'no_auto_bright' => true,
                    'noise_thr' => 100,
                    'use_camera_wb' => true
                ),
                'tone_adjustments' => array(
                    'exposure' => 0.05,
                    'contrast' => 0.08,
                    'highlights' => -0.50,
                    'shadows' => 0.50,
                    'whites' => 0.0,
                    'blacks' => 0.0,
                    'texture' => 0.50,
                    'clarity' => 0.10,
                    'dehaze' => 0.0,
                    'vibrance' => 0.0,
                    'saturation' => 0.0
                ),
                'color_adjustments' => array(
                    'hue_adjustments' => array(
                        'red' => 0, 'orange' => 0, 'yellow' => 0, 'green' => 0,
                        'aqua' => 0, 'blue' => 0, 'purple' => 0, 'magenta' => 0
                    ),
                    'saturation_adjustments' => array(
                        'red' => 0, 'orange' => 0, 'yellow' => 0, 'green' => 0,
                        'aqua' => 5, 'blue' => 15, 'purple' => 0, 'magenta' => 0
                    ),
                    'luminance_adjustments' => array(
                        'red' => 0, 'orange' => 0, 'yellow' => 0, 'green' => 0,
                        'aqua' => 0, 'blue' => 0, 'purple' => 0, 'magenta' => 0
                    )
                ),
                'detail' => array(
                    'sharpness' => 35,
                    'sharpen_radius' => 1.0,
                    'sharpen_detail' => 25,
                    'noise_reduction' => 30
                ),
                'created_at' => current_time('mysql'),
                'type' => 'default'
            );
            
            file_put_contents($default_generic_file, json_encode($generic_preset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    
    /**
     * Ajout du menu d'administration
     */
    public function add_preset_menu() {
        add_submenu_page(
            'iris-process',
            'Gestion des Presets Lightroom',
            'Presets Lightroom',
            $this->capability,
            'iris-presets',
            array($this, 'render_preset_page')
        );
    }
    
    /**
     * Enregistrement des scripts et styles admin
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'iris-presets') === false) {
            return;
        }
        
        // Scripts WordPress natifs
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        // Scripts du plugin
        wp_enqueue_script('iris-preset-manager', 
            IRIS_PLUGIN_URL . 'admin/js/preset-manager.js', 
            array('jquery', 'wp-util', 'jquery-ui-dialog'), 
            IRIS_PLUGIN_VERSION, 
            true
        );
        
        wp_localize_script('iris-preset-manager', 'iris_preset_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('iris_preset_nonce'),
            'strings' => array(
                'upload_success' => __('Preset uploadé avec succès', 'iris-process-tokens'),
                'upload_error' => __('Erreur lors de l\'upload', 'iris-process-tokens'),
                'delete_confirm' => __('Êtes-vous sûr de vouloir supprimer ce preset ?', 'iris-process-tokens'),
                'test_success' => __('Test du preset réussi', 'iris-process-tokens'),
                'test_error' => __('Erreur lors du test', 'iris-process-tokens'),
                'cache_cleared' => __('Cache vidé avec succès', 'iris-process-tokens'),
                'processing' => __('Traitement en cours...', 'iris-process-tokens'),
                'preview_xmp' => __('Aperçu du fichier XMP', 'iris-process-tokens')
            ),
            'max_file_size' => wp_max_upload_size()
        ));
        
        // Styles
        wp_enqueue_style('iris-preset-admin', 
            IRIS_PLUGIN_URL . 'admin/css/preset-admin.css', 
            array('wp-jquery-ui-dialog'), 
            IRIS_PLUGIN_VERSION
        );
    }
    
    /**
     * Enregistrement des paramètres WordPress
     */
    public function register_settings() {
        register_setting('iris_preset_settings', 'iris_default_preset');
        register_setting('iris_preset_settings', 'iris_auto_preprocessing');
        register_setting('iris_preset_settings', 'iris_save_intermediate');
        register_setting('iris_preset_settings', 'iris_cleanup_interval');
        register_setting('iris_preset_settings', 'iris_debug_preprocessing');
    }
    
    /**
     * Rendu de la page principale des presets
     */
    public function render_preset_page() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'list';
        $allowed_tabs = array('list', 'upload', 'settings', 'stats');
        
        if (!in_array($tab, $allowed_tabs)) {
            $tab = 'list';
        }
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php _e('Gestion des Presets Lightroom', 'iris-process-tokens'); ?>
                <span class="iris-version">v<?php echo IRIS_PLUGIN_VERSION; ?></span>
            </h1>
            
            <a href="?page=iris-presets&tab=upload" class="page-title-action">
                <?php _e('Ajouter un preset', 'iris-process-tokens'); ?>
            </a>
            
            <hr class="wp-header-end">
            
            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="?page=iris-presets&tab=list" 
                   class="nav-tab <?php echo $tab === 'list' ? 'nav-tab-active' : ''; ?>">
                   <span class="dashicons dashicons-list-view"></span>
                   <?php _e('Presets Existants', 'iris-process-tokens'); ?>
                </a>
                <a href="?page=iris-presets&tab=upload" 
                   class="nav-tab <?php echo $tab === 'upload' ? 'nav-tab-active' : ''; ?>">
                   <span class="dashicons dashicons-upload"></span>
                   <?php _e('Uploader un Preset', 'iris-process-tokens'); ?>
                </a>
                <a href="?page=iris-presets&tab=settings" 
                   class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                   <span class="dashicons dashicons-admin-settings"></span>
                   <?php _e('Paramètres', 'iris-process-tokens'); ?>
                </a>
                <a href="?page=iris-presets&tab=stats" 
                   class="nav-tab <?php echo $tab === 'stats' ? 'nav-tab-active' : ''; ?>">
                   <span class="dashicons dashicons-chart-bar"></span>
                   <?php _e('Statistiques', 'iris-process-tokens'); ?>
                </a>
            </nav>
            
            <div class="tab-content iris-tab-content">
                <?php
                switch ($tab) {
                    case 'upload':
                        $this->render_upload_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'stats':
                        $this->render_stats_tab();
                        break;
                    default:
                        $this->render_list_tab();
                        break;
                }
                ?>
            </div>
        </div>
        
        <!-- Modale pour prévisualisation XMP -->
        <div id="iris-xmp-preview-dialog" title="<?php _e('Aperçu du preset XMP', 'iris-process-tokens'); ?>" style="display: none;">
            <div id="iris-xmp-preview-content"></div>
        </div>
        <?php
    }
    
    /**
     * Onglet liste des presets
     */
    private function render_list_tab() {
        $presets = $this->get_all_presets();
        include IRIS_PLUGIN_PATH . 'admin/views/preset-list.php';
    }
    
    /**
     * Onglet upload de presets
     */
    private function render_upload_tab() {
        include IRIS_PLUGIN_PATH . 'admin/views/preset-upload.php';
    }
    
    /**
     * Onglet paramètres
     */
    private function render_settings_tab() {
        // Sauvegarde des paramètres
        if (isset($_POST['submit']) && check_admin_referer('iris_preset_settings', '_wpnonce')) {
            update_option('iris_default_preset', sanitize_text_field($_POST['iris_default_preset']));
            update_option('iris_auto_preprocessing', isset($_POST['iris_auto_preprocessing']) ? 1 : 0);
            update_option('iris_save_intermediate', isset($_POST['iris_save_intermediate']) ? 1 : 0);
            update_option('iris_cleanup_interval', sanitize_text_field($_POST['iris_cleanup_interval']));
            update_option('iris_debug_preprocessing', isset($_POST['iris_debug_preprocessing']) ? 1 : 0);
            
            echo '<div class="notice notice-success"><p>' . __('Paramètres sauvegardés !', 'iris-process-tokens') . '</p></div>';
        }
        
        $presets = $this->get_all_presets();
        include IRIS_PLUGIN_PATH . 'admin/views/preset-settings.php';
    }
    
    /**
     * Onglet statistiques
     */
    private function render_stats_tab() {
        $stats = $this->calculate_preset_statistics();
        include IRIS_PLUGIN_PATH . 'admin/views/preset-stats.php';
    }
    
    /**
     * Récupération de tous les presets
     */
    public function get_all_presets() {
        $presets = array();
        
        // Presets par défaut
        $default_presets = glob($this->presets_dir . '*.json');
        foreach ($default_presets as $preset_file) {
            if (strpos($preset_file, '/uploads/') === false) {
                $preset_data = $this->parse_preset_file($preset_file, 'default');
                if ($preset_data) {
                    $presets[] = $preset_data;
                }
            }
        }
        
        // Presets uploadés
        $uploaded_presets = glob($this->uploads_dir . '*.json');
        foreach ($uploaded_presets as $preset_file) {
            $preset_data = $this->parse_preset_file($preset_file, 'uploaded');
            if ($preset_data) {
                $presets[] = $preset_data;
            }
        }
        
        // Tri par nom
        usort($presets, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        
        return $presets;
    }
    
    /**
     * Analyse d'un fichier de preset
     */
    private function parse_preset_file($file_path, $type) {
        if (!file_exists($file_path)) {
            return null;
        }
        
        $content = file_get_contents($file_path);
        $preset_data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        $file_stats = stat($file_path);
        
        return array(
            'id' => basename($file_path, '.json'),
            'name' => $preset_data['name'] ?? basename($file_path, '.json'),
            'description' => $preset_data['description'] ?? '',
            'type' => $type,
            'file_path' => $file_path,
            'file_size' => $file_stats['size'],
            'camera_models' => $preset_data['camera_models'] ?? array(),
            'created_date' => date('Y-m-d H:i:s', $file_stats['ctime']),
            'modified_date' => date('Y-m-d H:i:s', $file_stats['mtime']),
            'author' => $preset_data['author'] ?? __('Inconnu', 'iris-process-tokens'),
            'version' => $preset_data['version'] ?? '1.0',
            'upload_info' => $preset_data['upload_info'] ?? null,
            'parameters_count' => $this->count_preset_parameters($preset_data),
            'is_valid' => $this->validate_preset_structure($preset_data)
        );
    }
    
    /**
     * Compte le nombre de paramètres dans un preset
     */
    private function count_preset_parameters($preset_data) {
        $count = 0;
        
        $sections_to_count = array('raw_params', 'tone_adjustments', 'color_adjustments', 'detail');
        
        foreach ($sections_to_count as $section) {
            if (isset($preset_data[$section]) && is_array($preset_data[$section])) {
                $count += count($preset_data[$section], COUNT_RECURSIVE) - count($preset_data[$section]);
            }
        }
        
        return $count;
    }
    
    /**
     * Validation de la structure d'un preset
     */
    private function validate_preset_structure($preset_data) {
        $required_fields = array('name', 'raw_params', 'tone_adjustments');
        
        foreach ($required_fields as $field) {
            if (!isset($preset_data[$field])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Gestionnaire d'upload de preset
     */
    public function handle_preset_upload() {
        check_ajax_referer('iris_preset_nonce', 'nonce');
        
        if (!current_user_can($this->capability)) {
            wp_send_json_error(__('Permissions insuffisantes', 'iris-process-tokens'));
        }
        
        if (!isset($_FILES['preset_file'])) {
            wp_send_json_error(__('Aucun fichier uploadé', 'iris-process-tokens'));
        }
        
        $file = $_FILES['preset_file'];
        
        // Validation du fichier
        $validation = $this->validate_uploaded_file($file);
        if (is_wp_error($validation)) {
            wp_send_json_error($validation->get_error_message());
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        try {
            if ($file_extension === 'xmp') {
                $result = $this->process_xmp_upload($file);
            } elseif ($file_extension === 'json') {
                $result = $this->process_json_upload($file);
            } else {
                throw new Exception(__('Format de fichier non supporté. Utilisez .xmp ou .json', 'iris-process-tokens'));
            }
            
            // Log de l'activité
            $this->log_preset_activity('upload', $result['preset_id'], array(
                'user_id' => get_current_user_id(),
                'original_file' => $file['name'],
                'file_type' => $file_extension
            ));
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Validation du fichier uploadé
     */
    private function validate_uploaded_file($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('Erreur lors de l\'upload', 'iris-process-tokens'));
        }
        
        $max_size = apply_filters('iris_preset_max_file_size', 2 * 1024 * 1024); // 2MB
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', 
                sprintf(__('Fichier trop volumineux. Taille maximum: %s', 'iris-process-tokens'), 
                        size_format($max_size)));
        }
        
        $allowed_extensions = array('xmp', 'json');
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            return new WP_Error('invalid_extension', 
                sprintf(__('Extension non autorisée. Extensions autorisées: %s', 'iris-process-tokens'),
                        implode(', ', $allowed_extensions)));
        }
        
        return true;
    }
    
    /**
     * Traitement d'un fichier XMP
     */
    private function process_xmp_upload($file) {
        require_once IRIS_PLUGIN_PATH . 'includes/class-xmp-parser.php';
        require_once IRIS_PLUGIN_PATH . 'includes/class-preset-converter.php';
        
        // Sauvegarde de l'XMP original dans les archives
        $xmp_filename = sanitize_file_name($file['name']);
        $xmp_archive_path = $this->archives_dir . time() . '_' . $xmp_filename;
        
        if (!move_uploaded_file($file['tmp_name'], $xmp_archive_path)) {
            throw new Exception(__('Erreur lors de la sauvegarde du fichier XMP', 'iris-process-tokens'));
        }
        
        // Parsing du fichier XMP
        $xmp_parser = new Iris_XMP_Parser();
        $xmp_data = $xmp_parser->parse_file($xmp_archive_path);
        
        // Conversion en format JSON
        $preset_converter = new Iris_Preset_Converter();
        $json_preset = $preset_converter->xmp_to_json($xmp_data);
        
        // Génération du nom de fichier
        $preset_name = $this->generate_preset_name($_POST['preset_name'] ?? '', $file['name']);
        
        // Ajout des métadonnées d'upload
        $json_preset['upload_info'] = array(
            'original_file' => $file['name'],
            'uploaded_by' => get_current_user_id(),
            'upload_date' => current_time('mysql'),
            'source' => 'lightroom_xmp',
            'file_size' => $file['size'],
            'ip_address' => $this->get_client_ip()
        );
        
        // Ajout des données du formulaire
        if (isset($_POST['camera_models']) && !empty($_POST['camera_models'])) {
            $camera_models = array_map('trim', explode(',', sanitize_textarea_field($_POST['camera_models'])));
            $json_preset['camera_models'] = array_filter($camera_models);
        }
        
        if (isset($_POST['description']) && !empty($_POST['description'])) {
            $json_preset['description'] = sanitize_textarea_field($_POST['description']);
        }
        
        // Sauvegarde du preset JSON
        $json_path = $this->uploads_dir . $preset_name . '.json';
        
        if (file_exists($json_path)) {
            throw new Exception(__('Un preset avec ce nom existe déjà', 'iris-process-tokens'));
        }
        
        if (!file_put_contents($json_path, json_encode($json_preset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            throw new Exception(__('Erreur lors de la sauvegarde du preset', 'iris-process-tokens'));
        }
        
        return array(
            'preset_id' => $preset_name,
            'preset_name' => $json_preset['name'],
            'file_path' => $json_path,
            'message' => __('Preset XMP converti et sauvegardé avec succès', 'iris-process-tokens'),
            'parameters_count' => $this->count_preset_parameters($json_preset)
        );
    }
    
    /**
     * Traitement d'un fichier JSON
     */
    private function process_json_upload($file) {
        // Lecture et validation du JSON
        $json_content = file_get_contents($file['tmp_name']);
        $preset_data = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(__('Fichier JSON invalide: ', 'iris-process-tokens') . json_last_error_msg());
        }
        
        // Validation de la structure
        if (!$this->validate_preset_structure($preset_data)) {
            throw new Exception(__('Structure JSON invalide. Vérifiez que le preset contient les champs requis', 'iris-process-tokens'));
        }
        
        // Génération du nom de fichier
        $preset_name = $this->generate_preset_name($_POST['preset_name'] ?? '', $file['name']);
        
        // Ajout des métadonnées d'upload
        $preset_data['upload_info'] = array(
            'original_file' => $file['name'],
            'uploaded_by' => get_current_user_id(),
            'upload_date' => current_time('mysql'),
            'source' => 'json_preset',
            'file_size' => $file['size'],
            'ip_address' => $this->get_client_ip()
        );
        
        // Ajout des données du formulaire
        if (isset($_POST['camera_models']) && !empty($_POST['camera_models'])) {
            $camera_models = array_map('trim', explode(',', sanitize_textarea_field($_POST['camera_models'])));
            $preset_data['camera_models'] = array_filter($camera_models);
        }
        
        if (isset($_POST['description']) && !empty($_POST['description'])) {
            $preset_data['description'] = sanitize_textarea_field($_POST['description']);
        }
        
        // Sauvegarde
        $json_path = $this->uploads_dir . $preset_name . '.json';
        if (file_exists($json_path)) {
           throw new Exception(__('Un preset avec ce nom existe déjà', 'iris-process-tokens'));
       }
       
       if (!file_put_contents($json_path, json_encode($preset_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
           throw new Exception(__('Erreur lors de la sauvegarde du preset', 'iris-process-tokens'));
       }
       
       return array(
           'preset_id' => $preset_name,
           'preset_name' => $preset_data['name'],
           'file_path' => $json_path,
           'message' => __('Preset JSON uploadé avec succès', 'iris-process-tokens'),
           'parameters_count' => $this->count_preset_parameters($preset_data)
       );
   }
   
   /**
    * Génération d'un nom de preset unique
    */
   private function generate_preset_name($user_input, $original_filename) {
       if (!empty($user_input)) {
           $base_name = sanitize_file_name($user_input);
       } else {
           $base_name = sanitize_file_name(pathinfo($original_filename, PATHINFO_FILENAME));
       }
       
       // Nettoyage du nom
       $base_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $base_name);
       $base_name = trim($base_name, '_-');
       
       if (empty($base_name)) {
           $base_name = 'preset_' . time();
       }
       
       // Vérification d'unicité
       $counter = 0;
       $preset_name = $base_name;
       
       while (file_exists($this->uploads_dir . $preset_name . '.json')) {
           $counter++;
           $preset_name = $base_name . '_' . $counter;
       }
       
       return $preset_name;
   }
   
   /**
    * Récupération de l'IP du client
    */
   private function get_client_ip() {
       $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
       
       foreach ($ip_keys as $key) {
           if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
               $ip = $_SERVER[$key];
               if (strpos($ip, ',') !== false) {
                   $ip = trim(explode(',', $ip)[0]);
               }
               if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                   return $ip;
               }
           }
       }
       
       return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
   }
   
   /**
    * Suppression d'un preset
    */
   public function handle_preset_delete() {
       check_ajax_referer('iris_preset_nonce', 'nonce');
       
       if (!current_user_can($this->capability)) {
           wp_send_json_error(__('Permissions insuffisantes', 'iris-process-tokens'));
       }
       
       $preset_id = sanitize_text_field($_POST['preset_id']);
       $preset_file = $this->uploads_dir . $preset_id . '.json';
       
       if (!file_exists($preset_file)) {
           wp_send_json_error(__('Preset non trouvé', 'iris-process-tokens'));
       }
       
       // Vérification que c'est bien un preset uploadé (pas un preset par défaut)
       if (file_exists($this->presets_dir . $preset_id . '.json')) {
           wp_send_json_error(__('Impossible de supprimer un preset par défaut', 'iris-process-tokens'));
       }
       
       // Sauvegarde des informations avant suppression pour le log
       $preset_data = json_decode(file_get_contents($preset_file), true);
       
       // Suppression du fichier
       if (unlink($preset_file)) {
           // Log de l'activité
           $this->log_preset_activity('delete', $preset_id, array(
               'user_id' => get_current_user_id(),
               'preset_name' => $preset_data['name'] ?? $preset_id
           ));
           
           wp_send_json_success(__('Preset supprimé avec succès', 'iris-process-tokens'));
       } else {
           wp_send_json_error(__('Erreur lors de la suppression', 'iris-process-tokens'));
       }
   }
   
   /**
    * Test d'un preset
    */
   public function handle_preset_test() {
       check_ajax_referer('iris_preset_nonce', 'nonce');
       
       if (!current_user_can($this->capability)) {
           wp_send_json_error(__('Permissions insuffisantes', 'iris-process-tokens'));
       }
       
       $preset_id = sanitize_text_field($_POST['preset_id']);
       
       try {
           $preset_file = $this->find_preset_file($preset_id);
           
           if (!$preset_file) {
               throw new Exception(__('Preset non trouvé', 'iris-process-tokens'));
           }
           
           $preset_data = json_decode(file_get_contents($preset_file), true);
           
           if (json_last_error() !== JSON_ERROR_NONE) {
               throw new Exception(__('Preset JSON invalide', 'iris-process-tokens'));
           }
           
           // Validation de la structure
           if (!$this->validate_preset_structure($preset_data)) {
               throw new Exception(__('Structure de preset invalide', 'iris-process-tokens'));
           }
           
           // Tests spécifiques
           $test_results = $this->run_preset_tests($preset_data);
           
           // Log du test
           $this->log_preset_activity('test', $preset_id, array(
               'user_id' => get_current_user_id(),
               'test_results' => $test_results
           ));
           
           wp_send_json_success(array(
               'message' => __('Preset valide', 'iris-process-tokens'),
               'preset_name' => $preset_data['name'],
               'parameters_count' => $this->count_preset_parameters($preset_data),
               'test_results' => $test_results
           ));
           
       } catch (Exception $e) {
           wp_send_json_error($e->getMessage());
       }
   }
   
   /**
    * Recherche d'un fichier de preset
    */
   private function find_preset_file($preset_id) {
       // Vérifier dans les presets uploadés
       $uploaded_file = $this->uploads_dir . $preset_id . '.json';
       if (file_exists($uploaded_file)) {
           return $uploaded_file;
       }
       
       // Vérifier dans les presets par défaut
       $default_file = $this->presets_dir . $preset_id . '.json';
       if (file_exists($default_file)) {
           return $default_file;
       }
       
       return null;
   }
   
   /**
    * Exécution des tests sur un preset
    */
   private function run_preset_tests($preset_data) {
       $tests = array();
       
       // Test 1: Présence des champs obligatoires
       $required_fields = array('name', 'raw_params', 'tone_adjustments');
       $tests['required_fields'] = array(
           'name' => 'Champs obligatoires',
           'status' => 'pass',
           'details' => array()
       );
       
       foreach ($required_fields as $field) {
           if (!isset($preset_data[$field])) {
               $tests['required_fields']['status'] = 'fail';
               $tests['required_fields']['details'][] = "Champ manquant: $field";
           }
       }
       
       // Test 2: Validation des paramètres RAW
       $tests['raw_params'] = array(
           'name' => 'Paramètres RAW',
           'status' => 'pass',
           'details' => array()
       );
       
       if (isset($preset_data['raw_params'])) {
           $raw_params = $preset_data['raw_params'];
           
           // Vérification de la température
           if (isset($raw_params['temperature']) && 
               ($raw_params['temperature'] < 2000 || $raw_params['temperature'] > 50000)) {
               $tests['raw_params']['status'] = 'warning';
               $tests['raw_params']['details'][] = 'Température inhabituelle: ' . $raw_params['temperature'] . 'K';
           }
           
           // Vérification du tint
           if (isset($raw_params['tint']) && 
               ($raw_params['tint'] < -150 || $raw_params['tint'] > 150)) {
               $tests['raw_params']['status'] = 'warning';
               $tests['raw_params']['details'][] = 'Tint extrême: ' . $raw_params['tint'];
           }
       }
       
       // Test 3: Validation des ajustements tonaux
       $tests['tone_adjustments'] = array(
           'name' => 'Ajustements tonaux',
           'status' => 'pass',
           'details' => array()
       );
       
       if (isset($preset_data['tone_adjustments'])) {
           $tone_params = $preset_data['tone_adjustments'];
           
           // Vérification des valeurs extrêmes
           $extreme_params = array();
           foreach ($tone_params as $param => $value) {
               if (is_numeric($value) && (abs($value) > 2.0)) {
                   $extreme_params[] = "$param: $value";
               }
           }
           
           if (!empty($extreme_params)) {
               $tests['tone_adjustments']['status'] = 'warning';
               $tests['tone_adjustments']['details'] = array('Valeurs extrêmes: ' . implode(', ', $extreme_params));
           }
       }
       
       // Test 4: Compatibilité des modèles de caméra
       $tests['camera_compatibility'] = array(
           'name' => 'Compatibilité caméras',
           'status' => 'pass',
           'details' => array()
       );
       
       if (isset($preset_data['camera_models']) && is_array($preset_data['camera_models'])) {
           $model_count = count($preset_data['camera_models']);
           if ($model_count === 0) {
               $tests['camera_compatibility']['details'][] = 'Preset universel (aucun modèle spécifique)';
           } else {
               $tests['camera_compatibility']['details'][] = "$model_count modèle(s) supporté(s)";
           }
       }
       
       return $tests;
   }
   
   /**
    * Vidage du cache des presets
    */
   public function handle_clear_cache() {
       check_ajax_referer('iris_preset_nonce', 'nonce');
       
       if (!current_user_can($this->capability)) {
           wp_send_json_error(__('Permissions insuffisantes', 'iris-process-tokens'));
       }
       
       // Suppression des caches WordPress liés aux presets
       wp_cache_delete('iris_presets_list');
       wp_cache_delete('iris_preset_mappings');
       
       // Suppression des transients
       delete_transient('iris_preset_statistics');
       delete_transient('iris_api_preset_test');
       
       wp_send_json_success(__('Cache vidé avec succès', 'iris-process-tokens'));
   }
   
   /**
    * Export d'un preset
    */
   public function handle_preset_export() {
       check_ajax_referer('iris_preset_nonce', 'nonce');
       
       if (!current_user_can($this->capability)) {
           wp_die(__('Permissions insuffisantes', 'iris-process-tokens'));
       }
       
       $preset_id = sanitize_text_field($_GET['preset_id']);
       $preset_file = $this->find_preset_file($preset_id);
       
       if (!$preset_file) {
           wp_die(__('Preset non trouvé', 'iris-process-tokens'));
       }
       
       $preset_data = json_decode(file_get_contents($preset_file), true);
       
       // Headers pour le téléchargement
       header('Content-Type: application/json');
       header('Content-Disposition: attachment; filename="' . $preset_id . '.json"');
       header('Content-Length: ' . filesize($preset_file));
       
       // Log de l'export
       $this->log_preset_activity('export', $preset_id, array(
           'user_id' => get_current_user_id()
       ));
       
       readfile($preset_file);
       exit;
   }
   
   /**
    * Prévisualisation d'un fichier XMP
    */
   public function handle_preview_xmp() {
       check_ajax_referer('iris_preset_nonce', 'nonce');
       
       if (!current_user_can($this->capability)) {
           wp_send_json_error(__('Permissions insuffisantes', 'iris-process-tokens'));
       }
       
       if (!isset($_FILES['xmp_file'])) {
           wp_send_json_error(__('Aucun fichier fourni', 'iris-process-tokens'));
       }
       
       $file = $_FILES['xmp_file'];
       
       try {
           require_once IRIS_PLUGIN_PATH . 'includes/class-xmp-parser.php';
           
           $xmp_parser = new Iris_XMP_Parser();
           $xmp_data = $xmp_parser->parse_file($file['tmp_name']);
           
           // Formatage pour l'affichage
           $preview_data = $this->format_xmp_preview($xmp_data);
           
           wp_send_json_success(array(
               'preview' => $preview_data,
               'filename' => $file['name']
           ));
           
       } catch (Exception $e) {
           wp_send_json_error($e->getMessage());
       }
   }
   
   /**
    * Formatage des données XMP pour prévisualisation
    */
   private function format_xmp_preview($xmp_data) {
       $preview = array();
       
       // Paramètres de base
       if (isset($xmp_data['basic'])) {
           $preview['Paramètres de base'] = array(
               'Balance des blancs' => $xmp_data['basic']['white_balance'] ?? 'Auto',
               'Température' => ($xmp_data['basic']['temperature'] ?? 'Auto') . 'K',
               'Teinte' => $xmp_data['basic']['tint'] ?? '0',
               'Exposition' => ($xmp_data['basic']['exposure'] ?? '0') . ' EV',
               'Contraste' => $xmp_data['basic']['contrast'] ?? '0',
               'Hautes lumières' => $xmp_data['basic']['highlights'] ?? '0',
               'Ombres' => $xmp_data['basic']['shadows'] ?? '0',
               'Texture' => $xmp_data['basic']['texture'] ?? '0',
               'Clarté' => $xmp_data['basic']['clarity'] ?? '0'
           );
       }
       
       // Ajustements couleur (seulement les non-nuls)
       if (isset($xmp_data['color']['saturation_adjustments'])) {
           $sat_adj = array_filter($xmp_data['color']['saturation_adjustments'], function($v) { return $v != 0; });
           if (!empty($sat_adj)) {
               $preview['Saturation'] = $sat_adj;
           }
       }
       
       if (isset($xmp_data['color']['hue_adjustments'])) {
           $hue_adj = array_filter($xmp_data['color']['hue_adjustments'], function($v) { return $v != 0; });
           if (!empty($hue_adj)) {
               $preview['Teinte'] = $hue_adj;
           }
       }
       
       // Détails
       if (isset($xmp_data['detail'])) {
           $detail_data = array_filter($xmp_data['detail'], function($v) { return $v != 0 && $v != 25; }); // 25 = valeur par défaut
           if (!empty($detail_data)) {
               $preview['Détails'] = $detail_data;
           }
       }
       
       return $preview;
   }
   
   /**
    * Calcul des statistiques des presets
    */
   public function calculate_preset_statistics() {
       $stats = get_transient('iris_preset_statistics');
       
       if ($stats === false) {
           $presets = $this->get_all_presets();
           
           $stats = array(
               'total_presets' => count($presets),
               'default_presets' => count(array_filter($presets, function($p) { return $p['type'] === 'default'; })),
               'uploaded_presets' => count(array_filter($presets, function($p) { return $p['type'] === 'uploaded'; })),
               'disk_usage' => $this->calculate_disk_usage(),
               'most_recent_upload' => $this->get_most_recent_upload(),
               'camera_coverage' => $this->calculate_camera_coverage($presets),
               'preset_activity' => $this->get_preset_activity_stats()
           );
           
           set_transient('iris_preset_statistics', $stats, HOUR_IN_SECONDS);
       }
       
       return $stats;
   }
   
   /**
    * Calcul de l'utilisation disque
    */
   public function calculate_disk_usage() {
       $total_size = 0;
       
       // Presets par défaut
       $default_files = glob($this->presets_dir . '*.json');
       foreach ($default_files as $file) {
           if (strpos($file, '/uploads/') === false) {
               $total_size += filesize($file);
           }
       }
       
       // Presets uploadés
       $uploaded_files = glob($this->uploads_dir . '*.json');
       foreach ($uploaded_files as $file) {
           $total_size += filesize($file);
       }
       
       // Archives XMP
       $archive_files = glob($this->archives_dir . '*');
       foreach ($archive_files as $file) {
           if (is_file($file)) {
               $total_size += filesize($file);
           }
       }
       
       return $total_size;
   }
   
   /**
    * Récupération du dernier upload
    */
   private function get_most_recent_upload() {
       $uploaded_files = glob($this->uploads_dir . '*.json');
       
       if (empty($uploaded_files)) {
           return null;
       }
       
       $most_recent = null;
       $most_recent_time = 0;
       
       foreach ($uploaded_files as $file) {
           $mtime = filemtime($file);
           if ($mtime > $most_recent_time) {
               $most_recent_time = $mtime;
               $most_recent = $file;
           }
       }
       
       if ($most_recent) {
           $preset_data = json_decode(file_get_contents($most_recent), true);
           return array(
               'name' => $preset_data['name'] ?? basename($most_recent, '.json'),
               'date' => date('Y-m-d H:i:s', $most_recent_time),
               'author' => $preset_data['upload_info']['uploaded_by'] ?? 'Inconnu'
           );
       }
       
       return null;
   }
   
   /**
    * Calcul de la couverture des modèles de caméra
    */
   private function calculate_camera_coverage($presets) {
       $camera_models = array();
       
       foreach ($presets as $preset) {
           if (!empty($preset['camera_models'])) {
               foreach ($preset['camera_models'] as $model) {
                   $normalized_model = strtolower(trim($model));
                   if (!isset($camera_models[$normalized_model])) {
                       $camera_models[$normalized_model] = array(
                           'original_name' => $model,
                           'preset_count' => 0
                       );
                   }
                   $camera_models[$normalized_model]['preset_count']++;
               }
           }
       }
       
       // Tri par nombre de presets
       uasort($camera_models, function($a, $b) {
           return $b['preset_count'] - $a['preset_count'];
       });
       
       return array_slice($camera_models, 0, 10); // Top 10
   }
   
   /**
    * Récupération des statistiques d'activité
    */
   private function get_preset_activity_stats() {
       $activity_log = get_option('iris_preset_activity_log', array());
       
       // Dernières 30 entrées
       $recent_activity = array_slice($activity_log, -30);
       
       // Comptage par type d'activité
       $activity_counts = array();
       foreach ($recent_activity as $entry) {
           $type = $entry['type'] ?? 'unknown';
           $activity_counts[$type] = ($activity_counts[$type] ?? 0) + 1;
       }
       
       return array(
           'recent_entries' => count($recent_activity),
           'activity_breakdown' => $activity_counts,
           'last_activity' => !empty($recent_activity) ? end($recent_activity) : null
       );
   }
   
   /**
    * Log d'une activité preset
    */
   private function log_preset_activity($type, $preset_id, $data = array()) {
       $activity_log = get_option('iris_preset_activity_log', array());
       
       $entry = array(
           'type' => $type,
           'preset_id' => $preset_id,
           'timestamp' => current_time('mysql'),
           'user_id' => get_current_user_id(),
           'data' => $data
       );
       
       $activity_log[] = $entry;
       
       // Garder seulement les 100 dernières entrées
       if (count($activity_log) > 100) {
           $activity_log = array_slice($activity_log, -100);
       }
       
       update_option('iris_preset_activity_log', $activity_log);
   }
   
   /**
    * Nettoyage périodique des anciens fichiers
    */
   public function cleanup_old_files() {
       $cleanup_interval = get_option('iris_cleanup_interval', 'weekly');
       
       if ($cleanup_interval === 'never') {
           return;
       }
       
       $cutoff_days = array(
           'daily' => 1,
           'weekly' => 7,
           'monthly' => 30
       );
       
       $days = $cutoff_days[$cleanup_interval] ?? 7;
       $cutoff_time = time() - ($days * 24 * 60 * 60);
       
       // Nettoyage des archives XMP anciennes
       $archive_files = glob($this->archives_dir . '*');
       $cleaned_count = 0;
       
       foreach ($archive_files as $file) {
           if (is_file($file) && filemtime($file) < $cutoff_time) {
               if (unlink($file)) {
                   $cleaned_count++;
               }
           }
       }
       
       // Log du nettoyage
       if ($cleaned_count > 0) {
           $this->log_preset_activity('cleanup', 'system', array(
               'files_cleaned' => $cleaned_count,
               'cutoff_days' => $days
           ));
       }
   }
   
   /**
    * Désactivation du plugin - nettoyage
    */
   public function deactivate() {
       // Suppression des tâches cron
       wp_clear_scheduled_hook('iris_preset_cleanup');
       
       // Nettoyage des transients
       delete_transient('iris_preset_statistics');
       delete_transient('iris_api_preset_test');
   }
}

// Auto-initialisation si inclus directement
if (!function_exists('get_iris_preset_manager')) {
   function get_iris_preset_manager() {
       global $iris_preset_manager;
       
       if (!$iris_preset_manager) {
           $iris_preset_manager = new Iris_Preset_Manager();
       }
       
       return $iris_preset_manager;
   }
   
   // Hook de désactivation
   register_deactivation_hook(IRIS_PLUGIN_PATH . 'image-processor-tokens.php', function() {
       $manager = get_iris_preset_manager();
       $manager->deactivate();
   });
}