<?php
if (!defined('ABSPATH')) {
    exit;
}

class Iris_Process_Image_Processor {
    
    public function process_upload($file, $user_id) {
        // Validation fichier
        $validation = $this->validate_file($file);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Sauvegarde fichier original
        $original_file_path = $this->save_file($file, $user_id, 'original');
        if (is_wp_error($original_file_path)) {
            return $original_file_path;
        }
        
        // Traitement RawPy (avec fichier XMP)
        $processed_file_path = $this->apply_rawpy_processing($original_file_path, $user_id);
        if (is_wp_error($processed_file_path)) {
            return $processed_file_path;
        }
        
        // Utiliser le fichier traité ou original selon disponibilité
        $file_to_send = $processed_file_path ?: $original_file_path;
        
        // Création enregistrement
        $process_id = $this->create_process_record($user_id, $file['name'], $file_to_send);
        
        // Envoi vers API ExtractIris
        $api_result = $this->send_to_api($file_to_send, $user_id, $process_id);
        
        if (is_wp_error($api_result)) {
            return $api_result;
        }
        
        return array(
            'message' => 'Fichier uploadé et traité avec succès !',
            'process_id' => $process_id,
            'job_id' => $api_result['job_id'],
            'rawpy_applied' => ($processed_file_path !== null),
            'remaining_tokens' => Token_Manager::get_user_balance($user_id)
        );
    }
    
    /**
     * Valider le fichier uploadé
     */
    private function validate_file($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'Erreur lors de l\'upload');
        }
        
        $allowed_extensions = array('jpg', 'jpeg', 'tif', 'tiff', 'cr3', 'cr2', 'nef', 'arw', 'dng', 'orf', 'raf', 'rw2', 'png');
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowed_extensions)) {
            return new WP_Error('invalid_format', 'Format non supporté. Formats acceptés : ' . implode(', ', $allowed_extensions));
        }
        
        return true;
    }
    
    /**
     * Sauvegarder le fichier avec suffixe
     */
    private function save_file($file, $user_id, $suffix = '') {
        $upload_dir = wp_upload_dir();
        $iris_dir = $upload_dir['basedir'] . '/iris-process';
        
        if (!file_exists($iris_dir)) {
            wp_mkdir_p($iris_dir);
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $prefix = $suffix ? $suffix . '_' : '';
        $file_name = uniqid($prefix . $user_id . '_') . '.' . $extension;
        $file_path = $iris_dir . '/' . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            return $file_path;
        } else {
            return new WP_Error('save_error', 'Erreur de sauvegarde');
        }
    }
    
    /**
     * Appliquer le traitement RawPy avec fichier XMP
     */
    private function apply_rawpy_processing($file_path, $user_id) {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // Chercher le fichier XMP spécifique au format
        $xmp_file = Iris_Process_XMP_Manager::get_xmp_file_for_extension($extension);
        
        // Si pas de fichier spécifique, utiliser le fichier par défaut
        if (!$xmp_file) {
            $xmp_file = Iris_Process_XMP_Manager::ensure_default_xmp_exists();
            iris_log_error("Utilisation du XMP par défaut pour l'extension: $extension");
        } else {
            iris_log_error("Utilisation du XMP spécifique pour l'extension: $extension");
        }
        
        // Préparer les paramètres pour l'API Python
        $api_params = array(
            'input_file' => $file_path,
            'xmp_file' => $xmp_file,
            'output_format' => 'tiff',
            'bit_depth' => 16,
            'color_space' => 'adobe_rgb',
            'dpi' => 240,
            'preserve_metadata' => true,
            'keep_original_size' => true
        );
        
        // Générer le nom du fichier de sortie
        $upload_dir = wp_upload_dir();
        $iris_dir = $upload_dir['basedir'] . '/iris-process';
        $output_filename = uniqid('processed_' . $user_id . '_') . '.tiff';
        $output_path = $iris_dir . '/' . $output_filename;
        
        $api_params['output_file'] = $output_path;
        
        // Appeler l'API RawPy
        $result = $this->call_rawpy_api($api_params);
        
        if (is_wp_error($result)) {
            iris_log_error("Erreur RawPy processing: " . $result->get_error_message() . " - Traitement sans XMP");
            return null; // En cas d'erreur, on utilisera le fichier original
        }
        
        if (file_exists($output_path) && filesize($output_path) > 0) {
            iris_log_error("RawPy processing réussi: $output_path");
            return $output_path;
        } else {
            iris_log_error("Fichier de sortie RawPy invalide - Traitement sans XMP");
            return null;
        }
    }
    
    /**
     * Appeler l'API Python pour le traitement RawPy
     */
    private function call_rawpy_api($params) {
        $api_url = IRIS_API_URL . '/rawpy-process';
        
        try {
            $response = wp_remote_post($api_url, array(
                'body' => json_encode($params),
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ),
                'timeout' => 120, // 2 minutes pour le traitement RAW
                'sslverify' => false
            ));
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($http_code !== 200) {
                return new WP_Error('api_error', "Erreur HTTP $http_code: $body");
            }
            
            $result = json_decode($body, true);
            
            if (!$result || !isset($result['success'])) {
                return new WP_Error('api_error', 'Réponse API invalide');
            }
            
            if (!$result['success']) {
                return new WP_Error('processing_error', $result['error'] ?? 'Erreur de traitement');
            }
            
            return $result;
            
        } catch (Exception $e) {
            return new WP_Error('exception', 'Exception RawPy: ' . $e->getMessage());
        }
    }
    
    /**
     * Création d'un enregistrement de traitement
     */
    private function create_process_record($user_id, $file_name, $file_path) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'iris_image_processes';
        
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
     * Envoi vers l'API ExtractIris
     */
    private function send_to_api($file_path, $user_id, $process_id) {
        global $wpdb;
        
        $api_url = IRIS_API_URL . '/process';
        $callback_url = home_url('/wp-json/iris/v1/callback');
        
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'Fichier non trouvé: ' . $file_path);
        }
        
        $curl_file = new CURLFile($file_path, mime_content_type($file_path), basename($file_path));
        
        $post_data = array(
            'file' => $curl_file,
            'user_id' => $user_id,
            'callback_url' => $callback_url,
            'processing_options' => json_encode(array())
        );
        
        try {
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $api_url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post_data,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTPHEADER => array('Accept: application/json')
            ));
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception('Erreur cURL: ' . $error);
            }
            
            if ($http_code !== 200) {
                throw new Exception('Erreur HTTP: ' . $http_code . ' - ' . $response);
            }
            
            $result = json_decode($response, true);
            if (!$result) {
                throw new Exception('Réponse JSON invalide');
            }
            
            // Enregistrer le job en base de données
            $job_id = $result['job_id'];
            $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
            $wpdb->insert(
                $table_jobs,
                array(
                    'job_id' => $job_id,
                    'user_id' => $user_id,
                    'status' => 'pending',
                    'original_file' => basename($file_path),
                    'created_at' => current_time('mysql'),
                    'api_response' => $response
                ),
                array('%s', '%d', '%s', '%s', '%s', '%s')
            );
            
            iris_log_error("Job $job_id créé pour utilisateur $user_id");
            
            return array(
                'success' => true,
                'job_id' => $job_id,
                'message' => $result['message']
            );
            
        } catch (Exception $e) {
            iris_log_error('Iris API Error: ' . $e->getMessage());
            return new WP_Error('api_error', 'Erreur API: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtenir le statut d'un processus
     */
    public function get_process_status($process_id, $user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'iris_image_processes';
        
        $process = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
            $process_id, $user_id
        ));
        
        if (!$process) {
            return array('error' => 'Traitement non trouvé');
        }
        
        return array(
            'status' => $process->status,
            'process_id' => $process->id,
            'created_at' => $process->created_at,
            'updated_at' => $process->updated_at
        );
    }
    
    /**
     * Gérer le callback de l'API
     */
    public function handle_api_callback($data) {
        global $wpdb;
        
        $job_id = sanitize_text_field($data['job_id']);
        $status = sanitize_text_field($data['status']);
        $user_id = intval($data['user_id']);
        
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        if ($status === 'completed') {
            $update_data['completed_at'] = current_time('mysql');
            if (isset($data['result_files'])) {
                $update_data['result_files'] = json_encode($data['result_files']);
            }
            
            Token_Manager::use_token($user_id, 0);
            do_action('iris_job_completed', $user_id, $job_id, $status);
            iris_log_error("Job $job_id terminé pour utilisateur $user_id");
            
        } elseif ($status === 'failed') {
            $update_data['error_message'] = isset($data['error']) ? sanitize_text_field($data['error']) : 'Erreur inconnue';
            iris_log_error("Job $job_id échoué - " . $update_data['error_message']);
        }
        
        $wpdb->update(
            $table_jobs,
            $update_data,
            array('job_id' => $job_id),
            array('%s', '%s'),
            array('%s')
        );
        
        return rest_ensure_response(array('status' => 'ok', 'message' => 'Callback traité'));
    }
    
    /**
     * Obtenir le statut d'un job
     */
    public function get_job_status($job_id) {
        global $wpdb;
        
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_jobs WHERE job_id = %s",
            $job_id
        ));
        
        if (!$job) {
            return new WP_Error('job_not_found', 'Job non trouvé', array('status' => 404));
        }
        
        return rest_ensure_response(array(
            'job_id' => $job->job_id,
            'status' => $job->status,
            'created_at' => $job->created_at,
            'completed_at' => $job->completed_at,
            'result_files' => $job->result_files ? json_decode($job->result_files, true) : []
        ));
    }
}