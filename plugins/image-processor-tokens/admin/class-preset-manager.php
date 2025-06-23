<?php
if (!defined('ABSPATH')) {
    exit;
}

class Iris_Preset_Manager {
    
    private $presets_dir;
    private $uploads_dir;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->presets_dir = $upload_dir['basedir'] . '/iris-presets/';
        $this->uploads_dir = $this->presets_dir . 'uploads/';
        
        $this->init_hooks();
        $this->ensure_directories();
    }
    
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_preset_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_iris_upload_preset', array($this, 'handle_preset_upload'));
        add_action('wp_ajax_iris_delete_preset', array($this, 'handle_preset_delete'));
        add_action('wp_ajax_iris_test_preset', array($this, 'handle_preset_test'));
    }
    
    private function ensure_directories() {
        if (!file_exists($this->presets_dir)) {
            wp_mkdir_p($this->presets_dir);
            wp_mkdir_p($this->uploads_dir);
            wp_mkdir_p($this->uploads_dir . 'archives/');
            
            // Création du fichier .htaccess pour sécuriser
            file_put_contents($this->presets_dir . '.htaccess', 
                "Options -Indexes\n<Files \"*.json\">\nOrder allow,deny\nAllow from all\n</Files>");
        }
    }
    
    public function add_preset_menu() {
        add_submenu_page(
            'iris-process',
            'Gestion des Presets',
            'Presets Lightroom',
            'manage_options',
            'iris-presets',
            array($this, 'render_preset_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'iris-presets') === false) {
            return;
        }
        
        wp_enqueue_media(); // Pour l'uploader de fichiers
        wp_enqueue_script('iris-preset-manager', 
            IRIS_PLUGIN_URL . 'admin/js/preset-manager.js', 
            array('jquery', 'wp-util'), 
            IRIS_PLUGIN_VERSION, 
            true
        );
        
        wp_localize_script('iris-preset-manager', 'iris_preset_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('iris_preset_nonce'),
            'strings' => array(
                'upload_success' => 'Preset uploadé avec succès',
                'upload_error' => 'Erreur lors de l\'upload',
                'delete_confirm' => 'Êtes-vous sûr de vouloir supprimer ce preset ?',
                'test_success' => 'Test du preset réussi',
                'test_error' => 'Erreur lors du test'
            )
        ));
        
        wp_enqueue_style('iris-preset-admin', 
            IRIS_PLUGIN_URL . 'admin/css/preset-admin.css', 
            array(), 
            IRIS_PLUGIN_VERSION
        );
    }
    
    public function render_preset_page() {
        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'list';
        
        ?>
        <div class="wrap">
            <h1>Gestion des Presets Lightroom</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=iris-presets&tab=list" 
                   class="nav-tab <?php echo $tab === 'list' ? 'nav-tab-active' : ''; ?>">
                   Presets Existants
                </a>
                <a href="?page=iris-presets&tab=upload" 
                   class="nav-tab <?php echo $tab === 'upload' ? 'nav-tab-active' : ''; ?>">
                   Uploader un Preset
                </a>
                <a href="?page=iris-presets&tab=settings" 
                   class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                   Paramètres
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($tab) {
                    case 'upload':
                        $this->render_upload_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    default:
                        $this->render_list_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_list_tab() {
        $presets = $this->get_all_presets();
        include IRIS_PLUGIN_PATH . 'admin/views/preset-list.php';
    }
    
    private function render_upload_tab() {
        include IRIS_PLUGIN_PATH . 'admin/views/preset-upload.php';
    }
    
    private function render_settings_tab() {
        include IRIS_PLUGIN_PATH . 'admin/views/preset-settings.php';
    }
    
    public function get_all_presets() {
        $presets = array();
        
        // Presets par défaut
        $default_presets = glob($this->presets_dir . '*.json');
        foreach ($default_presets as $preset_file) {
            if (strpos($preset_file, '/uploads/') === false) {
                $presets[] = $this->parse_preset_file($preset_file, 'default');
            }
        }
        
        // Presets uploadés
        $uploaded_presets = glob($this->uploads_dir . '*.json');
        foreach ($uploaded_presets as $preset_file) {
            $presets[] = $this->parse_preset_file($preset_file, 'uploaded');
        }
        
        return $presets;
    }
    
    private function parse_preset_file($file_path, $type) {
        $content = json_decode(file_get_contents($file_path), true);
        
        return array(
            'id' => basename($file_path, '.json'),
            'name' => $content['name'] ?? basename($file_path, '.json'),
            'type' => $type,
            'file_path' => $file_path,
            'camera_models' => $content['camera_models'] ?? array(),
            'created_date' => date('Y-m-d H:i:s', filemtime($file_path)),
            'description' => $content['description'] ?? '',
            'author' => $content['author'] ?? 'Inconnu'
        );
    }
    
    public function handle_preset_upload() {
        check_ajax_referer('iris_preset_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes');
        }
        
        if (!isset($_FILES['preset_file'])) {
            wp_send_json_error('Aucun fichier uploadé');
        }
        
        $file = $_FILES['preset_file'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        try {
            if ($file_extension === 'xmp') {
                $result = $this->process_xmp_upload($file);
            } elseif ($file_extension === 'json') {
                $result = $this->process_json_upload($file);
            } else {
                throw new Exception('Format de fichier non supporté. Utilisez .xmp ou .json');
            }
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    private function process_xmp_upload($file) {
        require_once IRIS_PLUGIN_PATH . 'includes/class-xmp-parser.php';
        require_once IRIS_PLUGIN_PATH . 'includes/class-preset-converter.php';
        
        // Sauvegarde de l'XMP original
        $xmp_archive_path = $this->uploads_dir . 'archives/' . sanitize_file_name($file['name']);
        move_uploaded_file($file['tmp_name'], $xmp_archive_path);
        
        // Parsing du fichier XMP
        $xmp_parser = new Iris_XMP_Parser();
        $xmp_data = $xmp_parser->parse_file($xmp_archive_path);
        
        // Conversion en format JSON
        $preset_converter = new Iris_Preset_Converter();
        $json_preset = $preset_converter->xmp_to_json($xmp_data);
        
        // Génération du nom de fichier
        $preset_name = sanitize_file_name(
            isset($_POST['preset_name']) && !empty($_POST['preset_name']) 
                ? $_POST['preset_name'] 
                : pathinfo($file['name'], PATHINFO_FILENAME)
        );
        
        // Ajout des métadonnées
        $json_preset['upload_info'] = array(
            'original_file' => $file['name'],
            'uploaded_by' => get_current_user_id(),
            'upload_date' => current_time('mysql'),
            'source' => 'lightroom_xmp'
        );
        
        if (isset($_POST['camera_models'])) {
            $json_preset['camera_models'] = array_map('sanitize_text_field', 
                explode(',', $_POST['camera_models']));
        }
        
        if (isset($_POST['description'])) {
            $json_preset['description'] = sanitize_textarea_field($_POST['description']);
        }
        
        // Sauvegarde du preset JSON
        $json_path = $this->uploads_dir . $preset_name . '.json';
        file_put_contents($json_path, json_encode($json_preset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return array(
            'preset_id' => $preset_name,
            'preset_name' => $json_preset['name'],
            'file_path' => $json_path,
            'message' => 'Preset XMP converti et sauvegardé avec succès'
        );
    }
    
    private function process_json_upload($file) {
        // Validation du JSON
        $json_content = file_get_contents($file['tmp_name']);
        $preset_data = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Fichier JSON invalide: ' . json_last_error_msg());
        }
        
        // Validation de la structure
        if (!isset($preset_data['name']) || !isset($preset_data['raw_params'])) {
            throw new Exception('Structure JSON invalide. Vérifiez que le preset contient "name" et "raw_params"');
        }
        
        // Génération du nom de fichier
        $preset_name = sanitize_file_name(
            isset($_POST['preset_name']) && !empty($_POST['preset_name']) 
                ? $_POST['preset_name'] 
                : pathinfo($file['name'], PATHINFO_FILENAME)
        );
        
        // Ajout des métadonnées d'upload
        $preset_data['upload_info'] = array(
            'original_file' => $file['name'],
            'uploaded_by' => get_current_user_id(),
            'upload_date' => current_time('mysql'),
            'source' => 'json_preset'
        );
        
        // Sauvegarde
        $json_path = $this->uploads_dir . $preset_name . '.json';
        file_put_contents($json_path, json_encode($preset_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return array(
            'preset_id' => $preset_name,
            'preset_name' => $preset_data['name'],
            'file_path' => $json_path,
            'message' => 'Preset JSON uploadé avec succès'
        );
    }
    
    public function handle_preset_delete() {
        check_ajax_referer('iris_preset_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes');
        }
        
        $preset_id = sanitize_text_field($_POST['preset_id']);
        $preset_file = $this->uploads_dir . $preset_id . '.json';
        
        if (!file_exists($preset_file)) {
            wp_send_json_error('Preset non trouvé');
        }
        
        // Suppression du fichier
        if (unlink($preset_file)) {
            wp_send_json_success('Preset supprimé avec succès');
        } else {
            wp_send_json_error('Erreur lors de la suppression');
        }
    }
    
    public function handle_preset_test() {
        check_ajax_referer('iris_preset_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes');
        }
        
        $preset_id = sanitize_text_field($_POST['preset_id']);
        
        // Test de chargement du preset
        try {
            $preset_file = $this->uploads_dir . $preset_id . '.json';
            if (!file_exists($preset_file)) {
                $preset_file = $this->presets_dir . $preset_id . '.json';
            }
            
            if (!file_exists($preset_file)) {
                throw new Exception('Preset non trouvé');
            }
            
            $preset_data = json_decode(file_get_contents($preset_file), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Preset JSON invalide');
            }
            
            // Validation de la structure
            $required_fields = array('name', 'raw_params', 'tone_adjustments');
            foreach ($required_fields as $field) {
                if (!isset($preset_data[$field])) {
                    throw new Exception("Champ requis manquant: $field");
                }
            }
            
            wp_send_json_success(array(
                'message' => 'Preset valide',
                'preset_name' => $preset_data['name'],
                'parameters_count' => count($preset_data, COUNT_RECURSIVE)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}

// Initialisation
new Iris_Preset_Manager();