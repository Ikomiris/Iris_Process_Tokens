<?php
/**
 * Fonctions de gestion de l'upload et du traitement - VERSION SÉCURISÉE
 * 
 * @package IrisProcessTokens
 * @since 1.0.0
 * @since 1.1.1 Gestion d'erreurs renforcée
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestionnaire d'upload d'images SÉCURISÉ
 * 
 * @since 1.0.0
 * @since 1.1.1 Vérifications de sécurité renforcées
 * @return void
 */
function iris_handle_image_upload() {
    // Log de démarrage
    iris_log_error('IRIS UPLOAD: Début traitement upload');
    
    // Vérifications de sécurité critiques
    try {
        // 1. Vérification du nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'iris_upload_nonce')) {
            throw new Exception('Erreur de sécurité - Nonce invalide');
        }
        
        // 2. Vérification utilisateur connecté
        $user_id = get_current_user_id();
        if (!$user_id) {
            throw new Exception('Utilisateur non connecté');
        }
        
        // 3. Vérification que Token_Manager existe
        if (!class_exists('Token_Manager')) {
            throw new Exception('Système de jetons non disponible');
        }
        
        // 4. Vérification du solde de jetons
        $balance = Token_Manager::get_user_balance($user_id);
        if ($balance < 1) {
            throw new Exception('Solde de jetons insuffisant (' . $balance . ' disponible)');
        }
        
        // 5. Vérification du fichier uploadé
        if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            $error_codes = array(
                UPLOAD_ERR_INI_SIZE => 'Fichier trop volumineux (limite serveur)',
                UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux (limite formulaire)',
                UPLOAD_ERR_PARTIAL => 'Upload partiel',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier sélectionné',
                UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
                UPLOAD_ERR_CANT_WRITE => 'Erreur d\'écriture disque',
                UPLOAD_ERR_EXTENSION => 'Extension PHP bloquante'
            );
            
            $error_code = isset($_FILES['image_file']) ? $_FILES['image_file']['error'] : UPLOAD_ERR_NO_FILE;
            $error_message = isset($error_codes[$error_code]) ? $error_codes[$error_code] : 'Erreur upload inconnue';
            throw new Exception('Erreur lors de l\'upload: ' . $error_message);
        }
        
        // 6. Récupération sécurisée du preset
        $preset_file_path = iris_get_preset_for_file($_FILES['image_file']['name']);
        
        // 7. Validation du fichier
        $file = $_FILES['image_file'];
        $validation_result = iris_validate_uploaded_file($file);
        if (is_wp_error($validation_result)) {
            throw new Exception($validation_result->get_error_message());
        }
        
        // 8. Sauvegarde sécurisée du fichier
        $file_path = iris_save_uploaded_file($file, $user_id);
        if (is_wp_error($file_path)) {
            throw new Exception($file_path->get_error_message());
        }
        
        // 9. Création de l'enregistrement de traitement
        $process_id = iris_create_process_record($user_id, $file['name'], $file_path);
        if (!$process_id) {
            throw new Exception('Erreur création enregistrement traitement');
        }
        
        // 10. Envoi vers l'API Python
        $api_result = iris_send_to_python_api($file_path, $user_id, $process_id, $preset_file_path);
        if (is_wp_error($api_result)) {
            throw new Exception('Erreur API: ' . $api_result->get_error_message());
        }
        
        // Succès !
        iris_log_error('IRIS UPLOAD: Succès pour utilisateur ' . $user_id);
        
        wp_send_json_success(array(
            'message' => 'Fichier uploadé avec succès ! Traitement en cours...',
            'process_id' => $process_id,
            'job_id' => $api_result['job_id'],
            'file_name' => basename($file_path),
            'preset_applied' => isset($api_result['preset_applied']) ? $api_result['preset_applied'] : false,
            'remaining_tokens' => Token_Manager::get_user_balance($user_id)
        ));
        
    } catch (Exception $e) {
        iris_log_error('IRIS UPLOAD ERROR: ' . $e->getMessage());
        wp_send_json_error($e->getMessage());
    }
}

/**
 * Valide un fichier uploadé
 * 
 * @since 1.1.1
 * @param array $file Données du fichier $_FILES
 * @return true|WP_Error True si valide, WP_Error sinon
 */
function iris_validate_uploaded_file($file) {
    // Vérifications de base
    if (!isset($file['name']) || !isset($file['size']) || !isset($file['tmp_name'])) {
        return new WP_Error('invalid_file', 'Données de fichier incomplètes');
    }
    
    // Extensions autorisées
    $allowed_extensions = array('jpg', 'jpeg', 'tif', 'tiff', 'cr3', 'cr2', 'nef', 'arw', 'raw', 'dng', 'orf', 'raf', 'rw2', 'png');
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowed_extensions)) {
        return new WP_Error('invalid_format', 'Format de fichier non supporté: ' . strtoupper($extension) . '. Formats acceptés: ' . implode(', ', array_map('strtoupper', $allowed_extensions)));
    }
    
    // Taille du fichier
    $max_size = wp_max_upload_size();
    if ($file['size'] > $max_size) {
        return new WP_Error('file_too_large', 'Fichier trop volumineux. Taille maximum: ' . size_format($max_size));
    }
    
    // Vérification que le fichier temporaire existe
    if (!file_exists($file['tmp_name'])) {
        return new WP_Error('temp_file_missing', 'Fichier temporaire non trouvé');
    }
    
    // Vérification MIME type basique
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = array(
            'image/jpeg', 'image/tiff', 'image/x-canon-cr3', 'image/x-canon-cr2',
            'image/x-nikon-nef', 'image/x-sony-arw', 'image/x-adobe-dng', 'image/png'
        );
        
        // Note: Certains formats RAW peuvent avoir des MIME types génériques
        if (!in_array($mime_type, $allowed_mimes) && !in_array($mime_type, array('application/octet-stream', 'image/x-dcraw'))) {
            iris_log_error('IRIS WARNING: MIME type suspect: ' . $mime_type . ' pour fichier ' . $file['name']);
        }
    }
    
    return true;
}

/**
 * Sauvegarde sécurisée d'un fichier uploadé
 * 
 * @since 1.1.1
 * @param array $file Données du fichier
 * @param int $user_id ID de l'utilisateur
 * @return string|WP_Error Chemin du fichier ou erreur
 */
function iris_save_uploaded_file($file, $user_id) {
    // Création du répertoire d'upload sécurisé
    $upload_dir = wp_upload_dir();
    $iris_dir = $upload_dir['basedir'] . '/iris-process';
    
    // Créer le répertoire avec permissions sécurisées
    if (!file_exists($iris_dir)) {
        if (!wp_mkdir_p($iris_dir)) {
            return new WP_Error('dir_creation_failed', 'Impossible de créer le répertoire d\'upload');
        }
        
        // Ajouter un fichier .htaccess pour sécuriser
        $htaccess_content = "Options -Indexes\n";
        $htaccess_content .= "Order deny,allow\n";
        $htaccess_content .= "Deny from all\n";
        $htaccess_content .= "<Files ~ \"\\.(jpg|jpeg|png|tif|tiff|cr3|cr2|nef|arw|raw|dng|orf|raf|rw2)$\">\n";
        $htaccess_content .= "Allow from all\n";
        $htaccess_content .= "</Files>\n";
        
        file_put_contents($iris_dir . '/.htaccess', $htaccess_content);
    }
    
    // Générer un nom de fichier sécurisé et unique
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safe_filename = sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME));
    $unique_filename = uniqid('iris_' . $user_id . '_' . substr($safe_filename, 0, 20) . '_') . '.' . $extension;
    
    // Chemin de destination
    $destination_path = $iris_dir . '/' . $unique_filename;
    
    // Vérifier que le fichier de destination n'existe pas déjà
    if (file_exists($destination_path)) {
        $unique_filename = uniqid('iris_' . $user_id . '_' . time() . '_') . '.' . $extension;
        $destination_path = $iris_dir . '/' . $unique_filename;
    }
    
    // Déplacer le fichier de manière sécurisée
    if (move_uploaded_file($file['tmp_name'], $destination_path)) {
        // Définir les permissions appropriées
        chmod($destination_path, 0644);
        
        iris_log_error('IRIS UPLOAD: Fichier sauvegardé - ' . $unique_filename);
        return $destination_path;
    } else {
        return new WP_Error('move_failed', 'Erreur lors de la sauvegarde du fichier');
    }
}

/**
 * Vérification du statut d'un traitement - SÉCURISÉE
 * 
 * @since 1.0.0
 * @since 1.1.1 Vérifications renforcées
 * @return void
 */
function iris_check_process_status() {
    try {
        // Vérifications de sécurité
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'iris_upload_nonce')) {
            throw new Exception('Erreur de sécurité');
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            throw new Exception('Utilisateur non connecté');
        }
        
        if (!isset($_POST['process_id'])) {
            throw new Exception('ID de traitement manquant');
        }
        
        $process_id = intval($_POST['process_id']);
        if ($process_id <= 0) {
            throw new Exception('ID de traitement invalide');
        }
        
        // Récupération sécurisée du statut
        global $wpdb;
        $table_name = $wpdb->prefix . 'iris_image_processes';
        
        $process = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
            $process_id, $user_id
        ));
        
        if (!$process) {
            throw new Exception('Traitement non trouvé ou accès non autorisé');
        }
        
        wp_send_json_success(array(
            'status' => $process->status,
            'process_id' => $process->id,
            'created_at' => $process->created_at,
            'updated_at' => $process->updated_at,
            'progress' => iris_get_process_progress($process->status)
        ));
        
    } catch (Exception $e) {
        iris_log_error('IRIS STATUS ERROR: ' . $e->getMessage());
        wp_send_json_error($e->getMessage());
    }
}

/**
 * Calcule le pourcentage de progression d'un traitement
 * 
 * @since 1.1.1
 * @param string $status Statut du traitement
 * @return int Pourcentage de progression
 */
function iris_get_process_progress($status) {
    $progress_map = array(
        'uploaded' => 10,
        'processing' => 50,
        'completed' => 100,
        'failed' => 0
    );
    
    return isset($progress_map[$status]) ? $progress_map[$status] : 0;
}

/**
 * Gestionnaire de téléchargement sécurisé
 * 
 * @since 1.0.0
 * @since 1.1.1 Sécurité renforcée
 * @return void
 */
function iris_handle_download() {
    try {
        // Vérifications de sécurité
        if (!isset($_GET['process_id']) || !isset($_GET['nonce'])) {
            throw new Exception('Paramètres manquants');
        }
        
        $process_id = intval($_GET['process_id']);
        $nonce = sanitize_text_field($_GET['nonce']);
        
        if (!wp_verify_nonce($nonce, 'iris_download_' . $process_id)) {
            throw new Exception('Erreur de sécurité');
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            throw new Exception('Utilisateur non connecté');
        }
        
        // Récupération sécurisée du traitement
        global $wpdb;
        $table_name = $wpdb->prefix . 'iris_image_processes';
        
        $process = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
            $process_id, $user_id
        ));
        
        if (!$process) {
            throw new Exception('Traitement non trouvé');
        }
        
        if (empty($process->processed_file_path) || !file_exists($process->processed_file_path)) {
            throw new Exception('Fichier traité non disponible');
        }
        
        // Vérification que le fichier est dans un répertoire autorisé
        $upload_dir = wp_upload_dir();
        $allowed_dir = $upload_dir['basedir'] . '/iris-process';
        
        if (strpos(realpath($process->processed_file_path), realpath($allowed_dir)) !== 0) {
            throw new Exception('Accès au fichier non autorisé');
        }
        
        // Log du téléchargement
        iris_log_error('IRIS DOWNLOAD: Fichier téléchargé par utilisateur ' . $user_id . ' - ' . basename($process->processed_file_path));
        
        // Téléchargement sécurisé
        $file_size = filesize($process->processed_file_path);
        $file_name = 'iris_processed_' . sanitize_file_name(pathinfo($process->original_filename, PATHINFO_FILENAME)) . '_' . date('Y-m-d') . '.' . pathinfo($process->processed_file_path, PATHINFO_EXTENSION);
        
        // Headers sécurisés
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . $file_size);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Lecture et envoi du fichier par chunks pour éviter les problèmes de mémoire
        $handle = fopen($process->processed_file_path, 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
        }
        exit;
        
    } catch (Exception $e) {
        iris_log_error('IRIS DOWNLOAD ERROR: ' . $e->getMessage());
        wp_die('Erreur de téléchargement: ' . $e->getMessage());
    }
}

/**
 * Nettoyage automatique des fichiers temporaires
 * 
 * @since 1.1.1
 * @return void
 */
function iris_cleanup_temp_files() {
    $upload_dir = wp_upload_dir();
    $iris_dir = $upload_dir['basedir'] . '/iris-process/';
    
    if (!is_dir($iris_dir)) {
        return;
    }
    
    $files_cleaned = 0;
    $files = glob($iris_dir . '*');
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > (7 * 24 * 3600)) { // 7 jours
            if (unlink($file)) {
                $files_cleaned++;
            }
        }
    }
    
    if ($files_cleaned > 0) {
        iris_log_error("IRIS CLEANUP: $files_cleaned fichiers temporaires supprimés");
    }
}

// Programmer le nettoyage automatique
if (!wp_next_scheduled('iris_cleanup_temp_files')) {
    wp_schedule_event(time(), 'daily', 'iris_cleanup_temp_files');
}
add_action('iris_cleanup_temp_files', 'iris_cleanup_temp_files');

/**
 * Sélectionne le preset à utiliser pour un fichier donné (par extension)
 * @param string $file_name
 * @return string|null Chemin du preset ou null
 */
function iris_get_preset_for_file($file_name) {
    global $wpdb;
    $table_presets = $wpdb->prefix . 'iris_presets';
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    // Chercher un preset pour ce type
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_presets WHERE photo_type = %s LIMIT 1", $ext), ARRAY_A);
    if ($row && !empty($row['file_name'])) {
        $upload_dir = wp_upload_dir();
        $preset_path = $upload_dir['basedir'] . '/iris-presets/uploads/' . $row['file_name'];
        if (!file_exists($preset_path)) {
            $preset_path = $upload_dir['basedir'] . '/iris-presets/' . $row['file_name'];
        }
        if (file_exists($preset_path)) {
            return $preset_path;
        }
    }
    // Sinon, preset par défaut
    $row = $wpdb->get_row("SELECT * FROM $table_presets WHERE is_default = 1 LIMIT 1", ARRAY_A);
    if ($row && !empty($row['file_name'])) {
        $upload_dir = wp_upload_dir();
        $preset_path = $upload_dir['basedir'] . '/iris-presets/uploads/' . $row['file_name'];
        if (!file_exists($preset_path)) {
            $preset_path = $upload_dir['basedir'] . '/iris-presets/' . $row['file_name'];
        }
        if (file_exists($preset_path)) {
            return $preset_path;
        }
    }
    return null;
}