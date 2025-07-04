<?php
if (!defined('ABSPATH')) {
    exit;
}

class Iris_Process_Ajax_Handlers {
    
    public function __construct() {
        add_action('wp_ajax_iris_upload_image', array($this, 'handle_upload'));
        add_action('wp_ajax_nopriv_iris_upload_image', array($this, 'handle_upload'));
        add_action('wp_ajax_iris_check_process_status', array($this, 'check_status'));
        add_action('wp_ajax_iris_test_api', array($this, 'test_api'));
        add_action('wp_ajax_iris_download', array($this, 'handle_download'));
    }
    
    public function handle_upload() {
        // Vérification nonce
        if (!wp_verify_nonce($_POST['nonce'], 'iris_upload_nonce')) {
            wp_send_json_error('Erreur de sécurité');
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Utilisateur non connecté');
        }
        
        // Vérification solde
        if (Token_Manager::get_user_balance($user_id) < 1) {
            wp_send_json_error('Solde de jetons insuffisant');
        }
        
        // Vérification du fichier uploadé
        if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Erreur lors de l\'upload du fichier');
        }
        
        // Traitement fichier
        $processor = new Iris_Process_Image_Processor();
        $result = $processor->process_upload($_FILES['image_file'], $user_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function check_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'iris_upload_nonce')) {
            wp_send_json_error('Erreur de sécurité');
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Utilisateur non connecté');
        }
        
        $process_id = intval($_POST['process_id']);
        
        $processor = new Iris_Process_Image_Processor();
        $status = $processor->get_process_status($process_id, $user_id);
        
        if (isset($status['error'])) {
            wp_send_json_error($status['error']);
        } else {
            wp_send_json_success($status);
        }
    }
    
    public function test_api() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission insuffisante');
        }
        
        $result = iris_test_api_connection();
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    public function handle_download() {
        $process_id = intval($_GET['process_id']);
        $nonce = $_GET['nonce'];
        
        if (!wp_verify_nonce($nonce, 'iris_download_' . $process_id)) {
            wp_die('Erreur de sécurité');
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_die('Utilisateur non connecté');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'iris_image_processes';
        
        $process = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
            $process_id, $user_id
        ));
        
        if (!$process || !file_exists($process->processed_file_path)) {
            wp_die('Fichier non trouvé');
        }
        
        // Téléchargement du fichier
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="processed_' . basename($process->original_filename) . '"');
        header('Content-Length: ' . filesize($process->processed_file_path));
        
        readfile($process->processed_file_path);
        exit;
    }
}