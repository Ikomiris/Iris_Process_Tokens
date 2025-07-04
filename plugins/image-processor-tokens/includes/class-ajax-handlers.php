<?php
/**
 * Gestionnaire des requêtes AJAX
 * 
 * @package IrisProcessTokens
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe de gestion des handlers AJAX
 * 
 * Centralise tous les endpoints AJAX du plugin avec sécurité renforcée
 * 
 * @since 1.0.0
 */
class Iris_Process_Ajax_Handlers {
    
    /**
     * Constructeur - Enregistrement des hooks AJAX
     * 
     * @since 1.0.0
     */
    public function __construct() {
        // Handlers pour utilisateurs connectés
        add_action('wp_ajax_iris_upload_image', array($this, 'handle_upload'));
        add_action('wp_ajax_iris_check_process_status', array($this, 'check_status'));
        add_action('wp_ajax_iris_download', array($this, 'handle_download'));
        add_action('wp_ajax_iris_get_user_history', array($this, 'get_user_history'));
        add_action('wp_ajax_iris_cancel_job', array($this, 'cancel_job'));
        
        // Handlers pour utilisateurs non connectés (si nécessaire)
        add_action('wp_ajax_nopriv_iris_upload_image', array($this, 'handle_upload_nopriv'));
        
        // Handlers admin uniquement
        add_action('wp_ajax_iris_test_api', array($this, 'test_api'));
        add_action('wp_ajax_iris_get_system_stats', array($this, 'get_system_stats'));
        add_action('wp_ajax_iris_cleanup_files', array($this, 'cleanup_files'));
        add_action('wp_ajax_iris_reset_user_tokens', array($this, 'reset_user_tokens'));
        
        // Handlers pour les presets (admin)
        add_action('wp_ajax_iris_upload_preset', array($this, 'upload_preset'));
        add_action('wp_ajax_iris_delete_preset', array($this, 'delete_preset'));
        add_action('wp_ajax_iris_test_preset', array($this, 'test_preset'));
        add_action('wp_ajax_iris_get_preset_list', array($this, 'get_preset_list'));
    }
    
    /**
     * Handler principal pour l'upload d'images
     * 
     * @since 1.0.0
     * @return void
     */
    public function handle_upload() {
        try {
            // Vérification du nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iris_upload_nonce')) {
                wp_send_json_error('Erreur de sécurité - Nonce invalide');
                return;
            }
            
            // Vérification utilisateur connecté
            $user_id = get_current_user_id();
            if (!$user_id) {
                wp_send_json_error('Utilisateur non connecté');
                return;
            }
            
            // Vérification des capacités utilisateur
            if (!current_user_can('read')) {
                wp_send_json_error('Permissions insuffisantes');
                return;
            }
            
            // Limitation de taux (rate limiting)
            if (!$this->check_rate_limit($user_id)) {
                wp_send_json_error('Trop de requêtes. Veuillez patienter avant de réessayer.');
                return;
            }
            
            // Vérification du solde de jetons
            $token_balance = Token_Manager::get_user_balance($user_id);
            if ($token_balance < 1) {
                wp_send_json_error('Solde de jetons insuffisant. Solde actuel : ' . $token_balance);
                return;
            }
            
            // Vérification du fichier uploadé
            if (!isset($_FILES['image_file'])) {
                wp_send_json_error('Aucun fichier uploadé');
                return;
            }
            
            // Récupération du preset sélectionné (optionnel)
            $preset_id = isset($_POST['preset_id']) ? intval($_POST['preset_id']) : null;
            if ($preset_id && $preset_id <= 0) {
                $preset_id = null; // Réinitialiser si valeur invalide
            }
            
            // Validation supplémentaire du preset
            if ($preset_id && class_exists('Preset_Manager')) {
                $preset_data = Preset_Manager::get_by_id($preset_id);
                if (!$preset_data) {
                    iris_log_error("Preset ID $preset_id non trouvé pour utilisateur $user_id");
                    $preset_id = null; // Utiliser le preset par défaut
                }
            }
            
            // Traitement du fichier
            $processor = new Iris_Process_Image_Processor();
            $result = $processor->process_upload($_FILES['image_file'], $user_id, $preset_id);
            
            if (is_wp_error($result)) {
                iris_log_error('Erreur upload pour utilisateur ' . $user_id . ': ' . $result->get_error_message());
                wp_send_json_error($result->get_error_message());
                return;
            }
            
            // Mise à jour du rate limiting
            $this->update_rate_limit($user_id);
            
            // Log de succès
            iris_log_error("Upload réussi pour utilisateur $user_id - Job: " . $result['job_id']);
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            iris_log_error('Exception dans handle_upload: ' . $e->getMessage());
            wp_send_json_error('Erreur serveur lors du traitement');
        }
    }
    
    /**
     * Handler pour utilisateurs non connectés (redirection)
     * 
     * @since 1.0.0
     * @return void
     */
    public function handle_upload_nopriv() {
        wp_send_json_error('Connexion requise pour utiliser cette fonctionnalité');
    }
    
    /**
     * Vérifier le statut d'un traitement
     * 
     * @since 1.0.0
     * @return void
     */
    public function check_status() {
        try {
            // Vérification du nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iris_upload_nonce')) {
                wp_send_json_error('Erreur de sécurité');
                return;
            }
            
            $user_id = get_current_user_id();
            if (!$user_id) {
                wp_send_json_error('Utilisateur non connecté');
                return;
            }
            
            $process_id = intval($_POST['process_id'] ?? 0);
            if ($process_id <= 0) {
                wp_send_json_error('ID de processus invalide');
                return;
            }
            
            $processor = new Iris_Process_Image_Processor();
            $status = $processor->get_process_status($process_id, $user_id);
            
            if (isset($status['error'])) {
                wp_send_json_error($status['error']);
                return;
            }
            
            wp_send_json_success($status);
            
        } catch (Exception $e) {
            iris_log_error('Exception dans check_status: ' . $e->getMessage());
            wp_send_json_error('Erreur lors de la vérification du statut');
        }
    }
    
    /**
     * Gestionnaire de téléchargement sécurisé
     * 
     * @since 1.0.0
     * @return void
     */
    public function handle_download() {
        try {
            $process_id = intval($_GET['process_id'] ?? 0);
            $nonce = $_GET['nonce'] ?? '';
            
            // Vérification du nonce spécifique au téléchargement
            if (!wp_verify_nonce($nonce, 'iris_download_' . $process_id)) {
                wp_die('Erreur de sécurité', 'Accès refusé', array('response' => 403));
                return;
            }
            
            $user_id = get_current_user_id();
            if (!$user_id) {
                wp_die('Utilisateur non connecté', 'Accès refusé', array('response' => 401));
                return;
            }
            
            if ($process_id <= 0) {
                wp_die('ID de processus invalide', 'Erreur', array('response' => 400));
                return;
            }
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'iris_image_processes';
            
            // Récupérer le processus avec vérification de propriété
            $process = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
                $process_id, $user_id
            ));
            
            if (!$process) {
                wp_die('Traitement non trouvé ou accès non autorisé', 'Erreur', array('response' => 404));
                return;
            }
            
            if (!$process->processed_file_path || !file_exists($process->processed_file_path)) {
                wp_die('Fichier traité non disponible', 'Erreur', array('response' => 404));
                return;
            }
            
            // Vérification de l'intégrité du fichier
            if (filesize($process->processed_file_path) === 0) {
                wp_die('Fichier corrompu', 'Erreur', array('response' => 500));
                return;
            }
            
            // Préparation du téléchargement
            $file_path = $process->processed_file_path;
            $file_name = 'processed_' . sanitize_file_name($process->original_filename);
            $file_size = filesize($file_path);
            $mime_type = mime_content_type($file_path) ?: 'application/octet-stream';
            
            // Headers pour le téléchargement
            header('Content-Type: ' . $mime_type);
            header('Content-Disposition: attachment; filename="' . $file_name . '"');
            header('Content-Length: ' . $file_size);
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
            header('Pragma: public');
            
            // Nettoyage du buffer de sortie
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Lecture et envoi du fichier par chunks pour économiser la mémoire
            $chunk_size = 8192;
            $handle = fopen($file_path, 'rb');
            
            if ($handle === false) {
                wp_die('Impossible de lire le fichier', 'Erreur', array('response' => 500));
                return;
            }
            
            while (!feof($handle)) {
                echo fread($handle, $chunk_size);
                flush();
            }
            
            fclose($handle);
            
            // Log du téléchargement
            iris_log_error("Téléchargement réussi: $file_name par utilisateur $user_id");
            
            exit;
            
        } catch (Exception $e) {
            iris_log_error('Exception dans handle_download: ' . $e->getMessage());
            wp_die('Erreur serveur lors du téléchargement', 'Erreur', array('response' => 500));
        }
    }
    
    /**
     * Récupérer l'historique utilisateur
     * 
     * @since 1.0.0
     * @return void
     */
    public function get_user_history() {
        try {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iris_upload_nonce')) {
                wp_send_json_error('Erreur de sécurité');
                return;
            }
            
            $user_id = get_current_user_id();
            if (!$user_id) {
                wp_send_json_error('Utilisateur non connecté');
                return;
            }
            
            $limit = min(50, max(1, intval($_POST['limit'] ?? 10)));
            
            if (function_exists('iris_get_user_process_history')) {
                $history = iris_get_user_process_history($user_id, $limit);
                wp_send_json_success(array('history' => $history));
            } else {
                wp_send_json_error('Fonction d\'historique non disponible');
            }
            
        } catch (Exception $e) {
            iris_log_error('Exception dans get_user_history: ' . $e->getMessage());
            wp_send_json_error('Erreur lors de la récupération de l\'historique');
        }
    }
    
    /**
     * Annuler un job en cours
     * 
     * @since 1.0.0
     * @return void
     */
    public function cancel_job() {
        try {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iris_upload_nonce')) {
                wp_send_json_error('Erreur de sécurité');
                return;
            }
            
            $user_id = get_current_user_id();
            if (!$user_id) {
                wp_send_json_error('Utilisateur non connecté');
                return;
            }
            
            $job_id = sanitize_text_field($_POST['job_id'] ?? '');
            if (empty($job_id)) {
                wp_send_json_error('ID de job manquant');
                return;
            }
            
            global $wpdb;
            $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
            
            // Vérifier que le job appartient à l'utilisateur
            $job = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_jobs WHERE job_id = %s AND user_id = %d",
                $job_id, $user_id
            ));
            
            if (!$job) {
                wp_send_json_error('Job non trouvé ou accès non autorisé');
                return;
            }
            
            if ($job->status === 'completed') {
                wp_send_json_error('Impossible d\'annuler un job terminé');
                return;
            }
            
            if ($job->status === 'cancelled') {
                wp_send_json_error('Job déjà annulé');
                return;
            }
            
            // Marquer le job comme annulé
            $update_result = $wpdb->update(
                $table_jobs,
                array(
                    'status' => 'cancelled',
                    'updated_at' => current_time('mysql'),
                    'error_message' => 'Annulé par l\'utilisateur'
                ),
                array('job_id' => $job_id),
                array('%s', '%s', '%s'),
                array('%s')
            );
            
            if ($update_result === false) {
                wp_send_json_error('Erreur lors de l\'annulation');
                return;
            }
            
            iris_log_error("Job $job_id annulé par utilisateur $user_id");
            wp_send_json_success('Job annulé avec succès');
            
        } catch (Exception $e) {
            iris_log_error('Exception dans cancel_job: ' . $e->getMessage());
            wp_send_json_error('Erreur lors de l\'annulation');
        }
    }
    
    /**
     * Tester la connexion API (admin uniquement)
     * 
     * @since 1.0.0
     * @return void
     */
    public function test_api() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permission insuffisante');
                return;
            }
            
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'iris_admin_nonce')) {
                wp_send_json_error('Erreur de sécurité');
                return;
            }
            
            if (function_exists('iris_test_api_connection')) {
                $result = iris_test_api_connection();
                
                if ($result['success']) {
                    wp_send_json_success($result['message']);
                } else {
                    wp_send_json_error($result['message']);
                }
            } else {
                wp_send_json_error('Fonction de test API non disponible');
            }
            
        } catch (Exception $e) {
            iris_log_error('Exception dans test_api: ' . $e->getMessage());
            wp_send_json_error('Erreur lors du test API');
        }
    }
    
    /**
     * Obtenir les statistiques système (admin)
     * 
     * @since 1.0.0
     * @return void
     */
    public function get_system_stats() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permission insuffisante');
                return;
            }
            
            $stats = array();
            
            // Statistiques des jetons
            if (class_exists('Token_Manager')) {
                $stats['tokens'] = Token_Manager::get_global_stats();
            }
            
            // Statistiques du processeur
            $processor = new Iris_Process_Image_Processor();
            $stats['processor'] = $processor->get_processor_stats();
            
            // Statistiques de la base de données
            if (class_exists('Iris_Process_Database')) {
                $db = new Iris_Process_Database();
                $stats['database'] = $db->get_database_stats();
            }
            
            // Statistiques système
            $stats['system'] = array(
                'php_version' => PHP_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'plugin_version' => IRIS_PLUGIN_VERSION,
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            );
            
            wp_send_json_success($stats);
            
        } catch (Exception $e) {
            iris_log_error('Exception dans get_system_stats: ' . $e->getMessage());
            wp_send_json_error('Erreur lors de la récupération des statistiques');
        }
    }
    
    /**
     * Nettoyer les fichiers temporaires (admin)
     * 
     * @since 1.0.0
     * @return void
     */
    public function cleanup_files() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permission insuffisante');
                return;
            }
            
            if (function_exists('iris_cleanup_old_jobs')) {
                iris_cleanup_old_jobs();
                wp_send_json_success('Nettoyage effectué avec succès');
            } else {
                wp_send_json_error('Fonction de nettoyage non disponible');
            }
            
        } catch (Exception $e) {
            iris_log_error('Exception dans cleanup_files: ' . $e->getMessage());
            wp_send_json_error('Erreur lors du nettoyage');
        }
    }
    
    /**
     * Réinitialiser les jetons d'un utilisateur (admin)
     * 
     * @since 1.0.0
     * @return void
     */
    public function reset_user_tokens() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permission insuffisante');
                return;
            }
            
            $user_id = intval($_POST['user_id'] ?? 0);
            $new_balance = intval($_POST['new_balance'] ?? 0);
            
            if ($user_id <= 0) {
                wp_send_json_error('ID utilisateur invalide');
                return;
            }
            
            if ($new_balance < 0) {
                wp_send_json_error('Le solde ne peut pas être négatif');
                return;
            }
            
            // Réinitialisation via la base de données
            global $wpdb;
            $table_tokens = $wpdb->prefix . 'iris_user_tokens';
            
            $result = $wpdb->update(
                $table_tokens,
                array(
                    'token_balance' => $new_balance,
                    'updated_at' => current_time('mysql')
                ),
                array('user_id' => $user_id),
                array('%d', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                iris_log_error("Solde réinitialisé pour utilisateur $user_id: $new_balance jetons");
                wp_send_json_success("Solde mis à jour: $new_balance jetons");
            } else {
                wp_send_json_error('Erreur lors de la mise à jour');
            }
            
        } catch (Exception $e) {
            iris_log_error('Exception dans reset_user_tokens: ' . $e->getMessage());
            wp_send_json_error('Erreur lors de la réinitialisation');
        }
    }
    
    /**
     * Uploader un preset (admin)
     * 
     * @since 1.1.0
     * @return void
     */
    public function upload_preset() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permission insuffisante');
                return;
            }
            
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iris_preset_nonce')) {
                wp_send_json_error('Erreur de sécurité');
                return;
            }
            
            if (class_exists('Preset_Manager')) {
                $result = Preset_Manager::handle_upload();
                
                if (is_wp_error($result)) {
                    wp_send_json_error($result->get_error_message());
                } else {
                    wp_send_json_success($result);
                }
            } else {
                wp_send_json_error('Gestionnaire de presets non disponible');
            }
            
        } catch (Exception $e) {
            iris_log_error('Exception dans upload_preset: ' . $e->getMessage());
            wp_send_json_error('Erreur lors de l\'upload du preset');
        }
    }
    
    /**
     * Supprimer un preset (admin)
     * 
     * @since 1.1.0
     * @return void
     */
    public function delete_preset() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permission insuffisante');
                return;
            }
            
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iris_preset_nonce')) {
                wp_send_json_error('Erreur de sécurité');
                return;
            }
            
            $preset_id = intval($_POST['preset_id'] ?? 0);
            if ($preset_id <= 0) {
                wp_send_json_error('ID de preset invalide');
                return;
            }
            
            if (class_exists('Preset_Manager')) {
                $result = Preset_Manager::delete($preset_id);
                
                if ($result) {
                    wp_send_json_success('Preset supprimé avec succès');
                } else {
                    wp_send_json_error('Erreur lors de la suppression');
                }
            } else {
                wp_send_json_error('Gestionnaire de presets non disponible');
            }
            
        } catch (Exception $e) {
            iris_log_error('Exception dans delete_preset: ' . $e->getMessage());
            wp_send_json_error('Erreur lors de la suppression du preset');
        }
    }
    
    /**
     * Tester un preset (admin)
     * 
     * @since 1.1.0
     * @return void
     */
    public function test_preset() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permission insuffisante');
                return;
            }
            
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iris_preset_nonce')) {
                wp_send_json_error('Erreur de sécurité');
                return;
            }
            
            $preset_id = intval($_POST['preset_id'] ?? 0);
            if ($preset_id <= 0) {
                wp_send_json_error('ID de preset invalide');
                return;
            }
            
            if (class_exists('Preset_Manager')) {
                $preset_data = Preset_Manager::get_by_id($preset_id);
                
                if ($preset_data) {
                    wp_send_json_success(array(
                        'message' => 'Preset valide et fonctionnel',
                        'preset_name' => $preset_data['name'] ?? 'Sans nom',
                        'parameters_count' => count($preset_data, COUNT_RECURSIVE)
                    ));
                } else {
                    wp_send_json_error('Preset non trouvé ou invalide');
                }
            } else {
                wp_send_json_error('Gestionnaire de presets non disponible');
            }
            
        } catch (Exception $e) {
            iris_log_error('Exception dans test_preset: ' . $e->getMessage());
            wp_send_json_error('Erreur lors du test du preset');
        }
    }
    
    /**
     * Obtenir la liste des presets
     * 
     * @since 1.1.0
     * @return void
     */
    public function get_preset_list() {
        try {
            if (!current_user_can('read')) {
                wp_send_json_error('Permission insuffisante');
                return;
            }
            
            if (class_exists('Preset_Manager')) {
                $presets = Preset_Manager::list_all();
                wp_send_json_success($presets);
            } else {
                wp_send_json_success(array()); // Liste vide si pas de gestionnaire
            }
            
        } catch (Exception $e) {
            iris_log_error('Exception dans get_preset_list: ' . $e->getMessage());
            wp_send_json_error('Erreur lors de la récupération des presets');
        }
    }
    
    /**
     * Vérifier le rate limiting pour un utilisateur
     * 
     * @since 1.0.0
     * @param int $user_id ID de l'utilisateur
     * @return bool True si la limite n'est pas atteinte
     */
    private function check_rate_limit($user_id) {
        $transient_key = 'iris_rate_limit_' . $user_id;
        $attempts = get_transient($transient_key);
        
        // Limite : 10 uploads par heure
        $max_attempts = 10;
        $time_window = 3600; // 1 heure
        
        if ($attempts === false) {
            return true; // Première tentative
        }
        
        return intval($attempts) < $max_attempts;
    }
    
    /**
     * Mettre à jour le compteur de rate limiting
     * 
     * @since 1.0.0
     * @param int $user_id ID de l'utilisateur
     * @return void
     */
    private function update_rate_limit($user_id) {
        $transient_key = 'iris_rate_limit_' . $user_id;
        $attempts = get_transient($transient_key);
        
        $new_attempts = $attempts === false ? 1 : intval($attempts) + 1;
        set_transient($transient_key, $new_attempts, 3600); // 1 heure
    }
}