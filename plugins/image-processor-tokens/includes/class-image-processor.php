<?php
/**
 * Processeur d'images avec système de jetons
 * 
 * @package IrisProcessTokens
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe de traitement des images
 * 
 * Gère l'upload, la validation, le traitement et l'intégration avec l'API Python
 * 
 * @since 1.0.0
 */
class Iris_Process_Image_Processor {
    
    /**
     * Extensions de fichiers autorisées
     * 
     * @since 1.0.0
     * @var array
     */
    private $allowed_extensions = array(
        'jpg', 'jpeg', 'tif', 'tiff', 'png',
        'cr3', 'cr2', 'nef', 'arw', 'raw', 'dng', 'orf', 'raf', 'rw2'
    );
    
    /**
     * Taille maximale par défaut (en octets)
     * 
     * @since 1.0.0
     * @var int
     */
    private $max_file_size = 104857600; // 100MB
    
    /**
     * Constructeur
     * 
     * @since 1.0.0
     */
    public function __construct() {
        // Récupérer la taille max depuis les options WordPress
        $this->max_file_size = get_option('iris_max_file_size', 100) * 1024 * 1024;
    }
    
    /**
     * Traiter un upload d'image
     * 
     * @since 1.0.0
     * @param array $file Fichier uploadé ($_FILES)
     * @param int $user_id ID de l'utilisateur
     * @param int|null $preset_id ID du preset à appliquer
     * @return array|WP_Error Résultat du traitement ou erreur
     */
    public function process_upload($file, $user_id, $preset_id = null) {
        try {
            // Validation du fichier
            $validation = $this->validate_file($file);
            if (is_wp_error($validation)) {
                return $validation;
            }
            
            // Vérification du solde utilisateur
            if (Token_Manager::get_user_balance($user_id) < 1) {
                return new WP_Error('insufficient_tokens', 'Solde de jetons insuffisant');
            }
            
            // Sauvegarde du fichier original
            $file_path = $this->save_uploaded_file($file, $user_id);
            if (is_wp_error($file_path)) {
                return $file_path;
            }
            
            // Création de l'enregistrement de traitement
            $process_id = $this->create_process_record($user_id, $file['name'], $file_path);
            if (!$process_id) {
                $this->cleanup_file($file_path);
                return new WP_Error('db_error', 'Erreur lors de la création de l\'enregistrement');
            }
            
            // Envoi vers l'API ExtractIris avec preset
            $api_result = $this->send_to_api($file_path, $user_id, $process_id, $preset_id);
            if (is_wp_error($api_result)) {
                $this->cleanup_file($file_path);
                return $api_result;
            }
            
            return array(
                'success' => true,
                'message' => 'Fichier uploadé et traité avec succès !',
                'process_id' => $process_id,
                'job_id' => $api_result['job_id'],
                'preset_applied' => $preset_id !== null,
                'file_name' => $file['name'],
                'remaining_tokens' => Token_Manager::get_user_balance($user_id)
            );
            
        } catch (Exception $e) {
            iris_log_error('Erreur Image_Processor::process_upload: ' . $e->getMessage());
            return new WP_Error('processing_error', 'Erreur lors du traitement: ' . $e->getMessage());
        }
    }
    
    /**
     * Valider le fichier uploadé
     * 
     * @since 1.0.0
     * @param array $file Fichier uploadé
     * @return bool|WP_Error True si valide, WP_Error sinon
     */
    private function validate_file($file) {
        // Vérifier les erreurs d'upload
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = array(
                UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximum autorisée par le serveur',
                UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximum du formulaire',
                UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement uploadé',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été uploadé',
                UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
                UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier sur le disque',
                UPLOAD_ERR_EXTENSION => 'Upload arrêté par une extension PHP'
            );
            
            $error_code = isset($file['error']) ? $file['error'] : UPLOAD_ERR_NO_FILE;
            $error_message = isset($error_messages[$error_code]) 
                ? $error_messages[$error_code] 
                : 'Erreur d\'upload inconnue';
                
            return new WP_Error('upload_error', $error_message);
        }
        
        // Vérifier la présence du fichier
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('invalid_file', 'Fichier non valide ou corrompu');
        }
        
        // Vérifier l'extension
        if (!isset($file['name']) || empty($file['name'])) {
            return new WP_Error('invalid_filename', 'Nom de fichier manquant');
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowed_extensions)) {
            return new WP_Error(
                'invalid_format', 
                'Format non supporté. Formats acceptés : ' . implode(', ', array_map('strtoupper', $this->allowed_extensions))
            );
        }
        
        // Vérifier la taille
        if (!isset($file['size']) || $file['size'] > $this->max_file_size) {
            return new WP_Error(
                'file_too_large', 
                'Fichier trop volumineux. Taille maximum : ' . size_format($this->max_file_size)
            );
        }
        
        // Vérifier le type MIME si disponible
        if (isset($file['type']) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            $allowed_mimes = array(
                'image/jpeg', 'image/tiff', 'image/png',
                'image/x-canon-cr3', 'image/x-canon-cr2',
                'image/x-nikon-nef', 'image/x-sony-arw',
                'application/octet-stream' // Pour les formats RAW non reconnus
            );
            
            // Log du type détecté pour debug
            iris_log_error("Type MIME détecté pour {$file['name']}: $detected_type");
        }
        
        return true;
    }
    
    /**
     * Sauvegarder le fichier uploadé
     * 
     * @since 1.0.0
     * @param array $file Fichier uploadé
     * @param int $user_id ID de l'utilisateur
     * @return string|WP_Error Chemin du fichier ou erreur
     */
    private function save_uploaded_file($file, $user_id) {
        // Créer le répertoire d'upload spécifique
        $upload_dir = wp_upload_dir();
        $iris_dir = $upload_dir['basedir'] . '/iris-process';
        
        if (!file_exists($iris_dir)) {
            if (!wp_mkdir_p($iris_dir)) {
                return new WP_Error('dir_creation_failed', 'Impossible de créer le répertoire d\'upload');
            }
            
            // Créer un fichier .htaccess pour la sécurité
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files \"*.php\">\nOrder allow,deny\nDeny from all\n</Files>\n";
            file_put_contents($iris_dir . '/.htaccess', $htaccess_content);
        }
        
        // Générer un nom de fichier unique et sécurisé
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safe_filename = sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME));
        $unique_filename = uniqid('iris_' . $user_id . '_') . '_' . $safe_filename . '.' . $extension;
        $file_path = $iris_dir . '/' . $unique_filename;
        
        // Déplacer le fichier
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            return new WP_Error('save_error', 'Erreur lors de la sauvegarde du fichier');
        }
        
        // Vérifier que le fichier a bien été sauvegardé
        if (!file_exists($file_path) || filesize($file_path) === 0) {
            return new WP_Error('save_verification_failed', 'Échec de la vérification du fichier sauvegardé');
        }
        
        // Définir les permissions appropriées
        chmod($file_path, 0644);
        
        iris_log_error("Fichier sauvegardé avec succès: $file_path");
        return $file_path;
    }
    
    /**
     * Créer un enregistrement de traitement
     * 
     * @since 1.0.0
     * @param int $user_id ID de l'utilisateur
     * @param string $file_name Nom du fichier
     * @param string $file_path Chemin du fichier
     * @return int|false ID de l'enregistrement créé ou false
     */
    private function create_process_record($user_id, $file_name, $file_path) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'iris_image_processes';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'original_filename' => sanitize_text_field($file_name),
                'file_path' => $file_path,
                'status' => 'uploaded',
                'processing_start_time' => current_time('mysql'),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            iris_log_error('Erreur création process_record: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Envoyer vers l'API ExtractIris
     * 
     * @since 1.0.0
     * @param string $file_path Chemin du fichier
     * @param int $user_id ID de l'utilisateur
     * @param int $process_id ID du processus
     * @param int|null $preset_id ID du preset
     * @return array|WP_Error Résultat de l'API ou erreur
     */
    private function send_to_api($file_path, $user_id, $process_id, $preset_id = null) {
        global $wpdb;
        
        $api_url = IRIS_API_URL . '/process';
        $callback_url = home_url('/wp-json/iris/v1/callback');
        
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'Fichier non trouvé: ' . $file_path);
        }
        
        // Récupérer le preset s'il est spécifié
        $preset_data = null;
        if ($preset_id && class_exists('Preset_Manager')) {
            $preset_data = Preset_Manager::get_by_id($preset_id);
            if (!$preset_data) {
                iris_log_error("Preset ID $preset_id non trouvé, utilisation du preset par défaut");
                $preset_data = Preset_Manager::get_default();
            }
        } elseif (class_exists('Preset_Manager')) {
            // Utiliser le preset par défaut
            $preset_data = Preset_Manager::get_default();
        }
        
        try {
            // Préparer le fichier pour l'upload
            if (!class_exists('CURLFile')) {
                return new WP_Error('curl_not_available', 'Extension cURL non disponible');
            }
            
            $curl_file = new CURLFile($file_path, mime_content_type($file_path), basename($file_path));
            
            // Données pour l'API
            $post_data = array(
                'file' => $curl_file,
                'user_id' => $user_id,
                'process_id' => $process_id,
                'callback_url' => $callback_url,
                'processing_options' => json_encode(array(
                    'use_preset' => $preset_data !== null,
                    'preset_data' => $preset_data,
                    'preset_format' => 'iris_json_v2'
                ))
            );
            
            // Configuration cURL
            $ch = curl_init();
            if (!$ch) {
                return new WP_Error('curl_init_failed', 'Impossible d\'initialiser cURL');
            }
            
            curl_setopt_array($ch, array(
                CURLOPT_URL => $api_url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post_data,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_HTTPHEADER => array(
                    'Accept: application/json',
                    'User-Agent: WordPress-IrisProcess/' . IRIS_PLUGIN_VERSION
                ),
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_SSL_VERIFYPEER => false, // Pour les environnements de dev
                CURLOPT_SSL_VERIFYHOST => false
            ));
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            // Vérifier les erreurs cURL
            if ($curl_error) {
                return new WP_Error('curl_error', 'Erreur cURL: ' . $curl_error);
            }
            
            if ($http_code !== 200) {
                iris_log_error("Erreur API HTTP $http_code: $response");
                return new WP_Error('api_http_error', "Erreur HTTP $http_code de l'API");
            }
            
            // Décoder la réponse JSON
            $result = json_decode($response, true);
            if (!$result) {
                iris_log_error("Réponse API invalide: $response");
                return new WP_Error('invalid_api_response', 'Réponse API invalide');
            }
            
            if (!isset($result['success']) || !$result['success']) {
                $error_msg = isset($result['error']) ? $result['error'] : 'Erreur API inconnue';
                return new WP_Error('api_error', $error_msg);
            }
            
            // Enregistrer le job en base de données
            $job_id = $result['job_id'];
            $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
            
            $job_insert = $wpdb->insert(
                $table_jobs,
                array(
                    'job_id' => $job_id,
                    'user_id' => $user_id,
                    'status' => 'pending',
                    'original_file' => basename($file_path),
                    'preset_id' => $preset_id,
                    'created_at' => current_time('mysql'),
                    'api_response' => $response
                ),
                array('%s', '%d', '%s', '%s', '%d', '%s', '%s')
            );
            
            if ($job_insert === false) {
                iris_log_error('Erreur insertion job: ' . $wpdb->last_error);
                // Continuer quand même, le job sera traité
            }
            
            // Sauvegarder les paramètres de traitement si preset utilisé
            if ($preset_data && class_exists('Preset_Manager')) {
                Preset_Manager::save_processing_params($job_id, $preset_id, $preset_data);
            }
            
            iris_log_error("Job $job_id créé pour utilisateur $user_id avec preset: " . ($preset_data ? 'Oui' : 'Non'));
            
            return array(
                'success' => true,
                'job_id' => $job_id,
                'message' => $result['message'] ?? 'Traitement démarré',
                'preset_applied' => $preset_data !== null
            );
            
        } catch (Exception $e) {
            iris_log_error('Exception send_to_api: ' . $e->getMessage());
            return new WP_Error('api_exception', 'Erreur API: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtenir le statut d'un processus
     * 
     * @since 1.0.0
     * @param int $process_id ID du processus
     * @param int $user_id ID de l'utilisateur
     * @return array Statut du processus
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
            'updated_at' => $process->updated_at,
            'error_message' => $process->error_message
        );
    }
    
    /**
     * Gérer le callback de l'API
     * 
     * @since 1.0.0
     * @param array $data Données du callback
     * @return WP_REST_Response Réponse REST
     */
    public function handle_api_callback($data) {
        global $wpdb;
        
        if (!isset($data['job_id'])) {
            return rest_ensure_response(array(
                'status' => 'error',
                'message' => 'Job ID manquant'
            ));
        }
        
        $job_id = sanitize_text_field($data['job_id']);
        $status = sanitize_text_field($data['status'] ?? 'unknown');
        $user_id = intval($data['user_id'] ?? 0);
        
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        try {
            if ($status === 'completed') {
                $update_data['completed_at'] = current_time('mysql');
                
                if (isset($data['result_files'])) {
                    $update_data['result_files'] = json_encode($data['result_files']);
                }
                
                // Décompter un jeton pour l'utilisateur
                if ($user_id > 0) {
                    Token_Manager::use_token($user_id, 0);
                }
                
                // Déclencher les hooks de completion
                do_action('iris_job_completed', $user_id, $job_id, $status);
                
                iris_log_error("Job $job_id terminé pour utilisateur $user_id");
                
            } elseif ($status === 'failed') {
                $error_message = isset($data['error']) ? sanitize_text_field($data['error']) : 'Erreur inconnue';
                $update_data['error_message'] = $error_message;
                
                iris_log_error("Job $job_id échoué - $error_message");
            }
            
            // Mettre à jour le job
            $update_result = $wpdb->update(
                $table_jobs,
                $update_data,
                array('job_id' => $job_id),
                array('%s', '%s'),
                array('%s')
            );
            
            if ($update_result === false) {
                iris_log_error("Erreur mise à jour job $job_id: " . $wpdb->last_error);
            }
            
            return rest_ensure_response(array(
                'status' => 'ok',
                'message' => 'Callback traité avec succès'
            ));
            
        } catch (Exception $e) {
            iris_log_error('Erreur handle_api_callback: ' . $e->getMessage());
            return rest_ensure_response(array(
                'status' => 'error',
                'message' => 'Erreur lors du traitement du callback'
            ));
        }
    }
    
    /**
     * Obtenir le statut d'un job
     * 
     * @since 1.0.0
     * @param string $job_id ID du job
     * @return WP_REST_Response|WP_Error Réponse ou erreur
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
            'error_message' => $job->error_message,
            'result_files' => $job->result_files ? json_decode($job->result_files, true) : array()
        ));
    }
    
    /**
     * Nettoyer un fichier en cas d'erreur
     * 
     * @since 1.0.0
     * @param string $file_path Chemin du fichier à supprimer
     * @return void
     */
    private function cleanup_file($file_path) {
        if (file_exists($file_path)) {
            unlink($file_path);
            iris_log_error("Fichier nettoyé: $file_path");
        }
    }
    
    /**
     * Obtenir les statistiques du processeur
     * 
     * @since 1.0.0
     * @return array Statistiques
     */
    public function get_processor_stats() {
        global $wpdb;
        
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        $table_processes = $wpdb->prefix . 'iris_image_processes';
        
        $stats = array(
            'total_jobs' => 0,
            'completed_jobs' => 0,
            'failed_jobs' => 0,
            'pending_jobs' => 0,
            'average_processing_time' => 0
        );
        
        try {
            // Statistiques des jobs
            $job_stats = $wpdb->get_row("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status IN ('pending', 'processing') THEN 1 ELSE 0 END) as pending
                FROM $table_jobs
            ");
            
            if ($job_stats) {
                $stats['total_jobs'] = intval($job_stats->total);
                $stats['completed_jobs'] = intval($job_stats->completed);
                $stats['failed_jobs'] = intval($job_stats->failed);
                $stats['pending_jobs'] = intval($job_stats->pending);
            }
            
            // Temps de traitement moyen (en minutes)
            $avg_time = $wpdb->get_var("
                SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at))
                FROM $table_jobs 
                WHERE status = 'completed' AND completed_at IS NOT NULL
            ");
            
            $stats['average_processing_time'] = $avg_time ? round(floatval($avg_time), 2) : 0;
            
        } catch (Exception $e) {
            iris_log_error('Erreur get_processor_stats: ' . $e->getMessage());
        }
        
        return $stats;
    }
}