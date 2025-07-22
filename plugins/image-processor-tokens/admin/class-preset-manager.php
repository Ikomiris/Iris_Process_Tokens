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
        
        $this->ensure_directories();
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
        global $wpdb;
        // Utilise la bonne table pour l'insertion
        $table_presets = $wpdb->prefix . 'iris_presets';
        // Validation du JSON
        $json_content = file_get_contents($file['tmp_name']);
        $preset_data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Fichier JSON invalide: ' . json_last_error_msg());
        }
        // Génération du nom de fichier
        $preset_name = sanitize_file_name(
            isset($_POST['preset_name']) && !empty($_POST['preset_name']) 
                ? $_POST['preset_name'] 
                : pathinfo($file['name'], PATHINFO_FILENAME)
        );
        if (empty($preset_name)) {
            $preset_name = pathinfo($file['name'], PATHINFO_FILENAME);
        }
        if (empty($description)) {
            $description = '';
        }
        // Log debug insertion
        if (function_exists('iris_log_error')) {
            iris_log_error('Insertion preset', [
                'file_name' => $preset_name . '.json',
                'photo_type' => $photo_type,
                'is_default' => $is_default,
                'preset_name' => $preset_data['name'] ?? $preset_name,
                'description' => $description,
            ]);
        }
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
        // Récupération des champs du formulaire
        if (!isset($_POST['photo_type']) || empty($_POST['photo_type'])) {
            throw new Exception('Le champ "Type de photo" est obligatoire.');
        }
        $photo_type = sanitize_text_field($_POST['photo_type']);
        $is_default = isset($_POST['is_default']) && $_POST['is_default'] == '1' ? 1 : 0;
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : (isset($_POST['preset_description']) ? sanitize_textarea_field($_POST['preset_description']) : '');
        // Si ce preset est par défaut, retirer le flag par défaut des autres
        if ($is_default) {
            $wpdb->query("UPDATE $table_presets SET is_default = 0 WHERE is_default = 1");
        }
        // Supprimer le preset existant pour ce type de photo
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_presets WHERE photo_type = %s", $photo_type));
        if ($existing) {
            // Supprimer le fichier JSON associé
            $existing_file = $this->uploads_dir . $existing->file_name;
            if (!file_exists($existing_file)) {
                $existing_file = $this->presets_dir . $existing->file_name;
            }
            if (file_exists($existing_file)) {
                unlink($existing_file);
            }
            // Supprimer l'entrée en base
            $wpdb->delete($table_presets, array('photo_type' => $photo_type));
        }
        // Insertion dans la table
        $result = $wpdb->insert($table_presets, array(
            'file_name' => $preset_name . '.json',
            'photo_type' => $photo_type,
            'is_default' => $is_default,
            'preset_name' => isset($preset_data['name']) && !empty($preset_data['name']) ? $preset_data['name'] : $preset_name,
            'description' => $description,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ));
        if ($result === false) {
            throw new Exception('Erreur lors de l\'insertion du preset en base : ' . $wpdb->last_error);
        }
        return array(
            'preset_id' => $preset_name,
            'preset_name' => $preset_data['name'],
            'file_path' => $json_path,
            'message' => 'Preset JSON uploadé avec succès'
        );
    }
    
    public function handle_preset_delete() {
        global $wpdb;
        $table_presets = $wpdb->prefix . 'iris_presets';
        check_ajax_referer('iris_preset_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes');
        }
        if (!isset($_POST['preset_id']) || empty($_POST['preset_id'])) {
            wp_send_json_error('ID du preset manquant.');
        }
        $preset_id = sanitize_text_field($_POST['preset_id']);
        $preset_file = $this->uploads_dir . $preset_id . '.json';
        if (!file_exists($preset_file)) {
            $preset_file = $this->presets_dir . $preset_id . '.json';
        }
        $file_deleted = false;
        if (file_exists($preset_file)) {
            $file_deleted = @unlink($preset_file); // @ pour éviter les warnings PHP
        }
        // Suppression de l'entrée dans la table
        $db_deleted = $wpdb->delete($table_presets, array('file_name' => $preset_id . '.json'));
        if ($file_deleted || $db_deleted) {
            wp_send_json_success('Preset supprimé avec succès');
        } else {
            wp_send_json_error('Erreur lors de la suppression (fichier ou base)');
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

    // Méthode statique pour compatibilité admin classique
    public static function handle_upload() {
        $instance = new self();
        return $instance->handle_preset_upload_form();
    }

    // Gère l'upload via POST direct (admin classique)
    public function handle_preset_upload_form() {
        if (!isset($_FILES['preset_file'])) {
            return new \WP_Error('no_file', 'Aucun fichier preset uploadé');
        }
        try {
            $file = $_FILES['preset_file'];
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($file_extension !== 'json') {
                throw new \Exception('Format de fichier non supporté. Utilisez .json');
            }
            return $this->process_json_upload($file);
        } catch (\Exception $e) {
            return new \WP_Error('upload_error', $e->getMessage());
        }
    }

    // Suppression d'un preset depuis le code PHP (hors AJAX)
    public function delete_preset($preset_id) {
        global $wpdb;
        $table_presets = $wpdb->prefix . 'iris_presets';
        $preset_id = intval($preset_id);
        // Récupérer le nom de fichier associé à cet ID
        $row = $wpdb->get_row($wpdb->prepare("SELECT file_name FROM $table_presets WHERE id = %d", $preset_id));
        if ($row && !empty($row->file_name)) {
            $preset_file = $this->uploads_dir . $row->file_name;
            if (!file_exists($preset_file)) {
                $preset_file = $this->presets_dir . $row->file_name;
            }
            if (file_exists($preset_file)) {
                @unlink($preset_file);
            }
        }
        $wpdb->delete($table_presets, array('id' => $preset_id));
    }
}

// Initialisation
new Iris_Preset_Manager();