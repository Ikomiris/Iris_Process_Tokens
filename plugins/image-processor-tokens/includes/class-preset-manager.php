<?php
/**
 * Gestionnaire des presets JSON
 * 
 * @package IrisProcessTokens
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe de gestion des presets JSON Iris Rawpy
 * 
 * @since 1.1.0
 */
class Preset_Manager {
    
    /**
     * Valider le format d'un preset Iris JSON
     * 
     * @since 1.1.0
     * @param array $preset_data Données du preset
     * @return bool True si le format est valide
     */
    public static function validate_format($preset_data) {
        // Vérifier la structure de base
        if (isset($preset_data['parameters'])) {
            $params = $preset_data['parameters'];
        } else if (isset($preset_data['tint']) || isset($preset_data['exposure'])) {
            $params = $preset_data; // Format direct (rétrocompatibilité)
        } else {
            return false;
        }
        
        // Paramètres attendus
        $expected_params = [
            'tint', 'exposure', 'contrast', 'highlights', 'shadows', 'vibrance',
            'texture', 'clarity', 'sharpening_amount', 'sharpening_radius', 'sharpening_detail'
        ];
        
        // Paramètres HSL
        $colors = ['red', 'orange', 'yellow', 'green', 'aqua', 'blue'];
        foreach ($colors as $color) {
            $expected_params[] = "hue_{$color}";
            $expected_params[] = "sat_{$color}";
            $expected_params[] = "lum_{$color}";
        }
        
        // Au moins 5 paramètres attendus doivent être présents
        $found_params = 0;
        foreach ($expected_params as $param) {
            if (isset($params[$param])) {
                $found_params++;
            }
        }
        
        return $found_params >= 5;
    }
    
    /**
     * Gérer l'upload d'un preset JSON
     * 
     * @since 1.1.0
     * @return int|WP_Error ID du preset créé ou erreur
     */
    public static function handle_upload() {
        if (!isset($_FILES['preset_file']) || $_FILES['preset_file']['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'Erreur lors de l\'upload du fichier');
        }
        
        $file = $_FILES['preset_file'];
        
        // Vérifier l'extension
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'json') {
            return new WP_Error('invalid_format', 'Seuls les fichiers JSON sont acceptés');
        }
        
        // Lire et valider le contenu JSON
        $json_content = file_get_contents($file['tmp_name']);
        $preset_data = json_decode($json_content, true);
        
        if (!$preset_data) {
            return new WP_Error('invalid_json', 'Fichier JSON invalide');
        }
        
        // Valider le format Iris
        if (!self::validate_format($preset_data)) {
            return new WP_Error('invalid_preset', 'Format de preset Iris invalide. Vérifiez que le fichier a été généré par Iris Rawpy.');
        }
        
        // Sauvegarder en base
        global $wpdb;
        $table = $wpdb->prefix . 'iris_admin_presets';
        
        // Si défini comme défaut, retirer le défaut des autres
        if (isset($_POST['is_default']) && $_POST['is_default'] == '1') {
            $wpdb->update($table, array('is_default' => 0), array('is_default' => 1));
        }
        
        $current_user = wp_get_current_user();
        $result = $wpdb->insert(
            $table,
            array(
                'preset_name' => sanitize_text_field($_POST['preset_name']),
                'description' => sanitize_textarea_field($_POST['preset_description']),
                'preset_data' => $json_content,
                'file_name' => sanitize_file_name($file['name']),
                'is_default' => isset($_POST['is_default']) ? 1 : 0,
                'created_by' => $current_user->display_name ?: $current_user->user_login
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s')
        );
        
        return $result ? $wpdb->insert_id : new WP_Error('db_error', 'Erreur lors de la sauvegarde');
    }
    
    /**
     * Supprimer un preset admin
     * 
     * @since 1.1.0
     * @param int $preset_id ID du preset
     * @return bool Succès de l'opération
     */
    public static function delete($preset_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'iris_admin_presets';
        
        return $wpdb->delete($table, array('id' => $preset_id), array('%d')) !== false;
   }
   
   /**
    * Récupérer le preset par défaut
    * 
    * @since 1.1.0
    * @return array|null Données du preset ou null
    */
   public static function get_default() {
       global $wpdb;
       $table = $wpdb->prefix . 'iris_admin_presets';
       
       $preset = $wpdb->get_row("SELECT * FROM $table WHERE is_default = 1 LIMIT 1");
       
       if ($preset) {
           return json_decode($preset->preset_data, true);
       }
       
       return null;
   }
   
   /**
    * Récupérer un preset par ID
    * 
    * @since 1.1.0
    * @param int $preset_id ID du preset
    * @return array|null Données du preset ou null
    */
   public static function get_by_id($preset_id) {
       global $wpdb;
       $table = $wpdb->prefix . 'iris_admin_presets';
       
       $preset = $wpdb->get_row($wpdb->prepare(
           "SELECT * FROM $table WHERE id = %d",
           $preset_id
       ));
       
       if ($preset) {
           // Incrémenter le compteur d'utilisation
           $wpdb->query($wpdb->prepare(
               "UPDATE $table SET usage_count = usage_count + 1 WHERE id = %d",
               $preset_id
           ));
           
           return json_decode($preset->preset_data, true);
       }
       
       return null;
   }
   
   /**
    * Lister tous les presets disponibles
    * 
    * @since 1.1.0
    * @return array Liste des presets
    */
   public static function list_all() {
       global $wpdb;
       $table = $wpdb->prefix . 'iris_admin_presets';
       
       return $wpdb->get_results("
           SELECT id, preset_name, description, is_default, usage_count, created_at 
           FROM $table 
           ORDER BY is_default DESC, usage_count DESC, preset_name ASC
       ");
   }
   
   /**
    * Sauvegarder les paramètres de traitement pour un job
    * 
    * @since 1.1.0
    * @param string $job_id ID du job
    * @param int|null $preset_id ID du preset utilisé
    * @param array|null $custom_params Paramètres personnalisés
    * @return bool Succès de l'opération
    */
   public static function save_processing_params($job_id, $preset_id = null, $custom_params = null) {
       global $wpdb;
       $table = $wpdb->prefix . 'iris_processing_params';
       
       return $wpdb->insert(
           $table,
           array(
               'job_id' => $job_id,
               'preset_id' => $preset_id,
               'custom_params' => $custom_params ? json_encode($custom_params) : null
           ),
           array('%s', '%d', '%s')
       ) !== false;
   }
}

// Fonctions de commodité pour compatibilité
function iris_validate_preset_format($preset_data) {
   return Preset_Manager::validate_format($preset_data);
}

function iris_handle_preset_upload() {
   return Preset_Manager::handle_upload();
}

function iris_delete_admin_preset($preset_id) {
   return Preset_Manager::delete($preset_id);
}

function iris_get_default_preset() {
   return Preset_Manager::get_default();
}

function iris_get_preset_by_id($preset_id) {
   return Preset_Manager::get_by_id($preset_id);
}

function iris_list_available_presets() {
   return Preset_Manager::list_all();
}

function iris_save_processing_params($job_id, $preset_id = null, $custom_params = null) {
   return Preset_Manager::save_processing_params($job_id, $preset_id, $custom_params);
}