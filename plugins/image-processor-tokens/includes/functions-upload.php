<?php
/**
 * Fonctions de gestion de l'upload et du traitement
 * 
 * @package IrisProcessTokens
 * @since 1.0.0
 * @version 1.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestionnaire d'upload d'images (MODIFIÉ v1.1.0 pour presets)
 * 
 * @since 1.0.0
 * @since 1.1.0 Ajout support presets JSON
 * @since 1.1.1 Validation sécurisée renforcée
 * @return void
 */
function iris_handle_image_upload() {
    // Vérification du nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'iris_upload_nonce')) {
        wp_send_json_error('Erreur de sécurité - nonce invalide');
        return;
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('Utilisateur non connecté');
        return;
    }
    
    // Vérification du solde de jetons
    if (!class_exists('Token_Manager')) {
        wp_send_json_error('Gestionnaire de jetons non disponible');
        return;
    }
    
    $current_balance = Token_Manager::get_user_balance($user_id);
    if ($current_balance < 1) {
        wp_send_json_error('Solde de jetons insuffisant. Solde actuel: ' . $current_balance);
        return;
    }
    
    // Récupération du preset sélectionné (v1.1.0)
    $preset_id = isset($_POST['preset_id']) ? intval($_POST['preset_id']) : null;
    
    // Vérification du fichier uploadé
    if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
        $error_messages = array(
            UPLOAD_ERR_INI_SIZE => 'Fichier trop volumineux (limite serveur)',
            UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux (limite formulaire)',
            UPLOAD_ERR_PARTIAL => 'Upload partiel',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier sélectionné',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Erreur d\'écriture',
            UPLOAD_ERR_EXTENSION => 'Extension bloquée'
        );
        
        $error_code = $_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $error_msg = $error_messages[$error_code] ?? 'Erreur d\'upload inconnue';
        
        wp_send_json_error('Erreur lors de l\'upload du fichier: ' . $error_msg);
        return;
    }
    
    $file = $_FILES['image_file'];
    
    // Validation sécurisée du fichier
    $validation_result = iris_validate_uploaded_file($file);
    if (is_wp_error($validation_result)) {
        wp_send_json_error($validation_result->get_error_message());
        return;
    }
    
    // Création du répertoire d'upload sécurisé
    $upload_result = iris_create_secure_upload_directory();
    if (is_wp_error($upload_result)) {
        wp_send_json_error($upload_result->get_error_message());
        return;
    }
    
    $iris_dir = $upload_result['path'];
    
    // Génération d'un nom de fichier unique et sécurisé
    $file_info = iris_generate_secure_filename($file, $user_id);
    $file_path = $iris_dir . '/' . $file_info['filename'];
    
    // Déplacement du fichier de manière sécurisée
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        wp_send_json_error('Erreur lors de la sauvegarde du fichier');
        return;
    }
    
    // Validation finale du fichier sauvegardé
    $final_validation = iris_validate_saved_file($file_path);
    if (is_wp_error($final_validation)) {
        unlink($file_path); // Nettoyer le fichier défaillant
        wp_send_json_error($final_validation->get_error_message());
        return;
    }
    
    // Création de l'enregistrement de traitement
    $process_id = iris_create_process_record($user_id, $file['name'], $file_path);
    if (!$process_id) {
        unlink($file_path);
        wp_send_json_error('Erreur lors de la création de l\'enregistrement');
        return;
    }
    
    // Envoi vers l'API Python avec preset (v1.1.0)
    $api_result = iris_send_to_python_api($file_path, $user_id, $process_id, $preset_id);
    
    if (is_wp_error($api_result)) {
        wp_send_json_error($api_result->get_error_message());
        return;
    }
    
    // Succès - réponse avec toutes les informations
    wp_send_json_success(array(
        'message' => 'Fichier uploadé avec succès ! Traitement en cours...',
        'process_id' => $process_id,
        'job_id' => $api_result['job_id'],
        'file_name' => $file['name'],
        'file_size' => size_format($file['size']),
        'preset_applied' => $api_result['preset_applied'] ?? false,
        'remaining_tokens' => Token_Manager::get_user_balance($user_id)
    ));
}

/**
 * Validation sécurisée d'un fichier uploadé
 * 
 * @since 1.1.1
 * @param array $file Informations du fichier uploadé
 * @return true|WP_Error Validation réussie ou erreur
 */
function iris_validate_uploaded_file($file) {
    // Extensions autorisées
    $allowed_extensions = array('jpg', 'jpeg', 'tif', 'tiff', 'cr3', 'cr2', 'nef', 'arw', 'raw', 'dng', 'orf', 'raf', 'rw2', 'png');
    
    // Vérification de l'extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_extensions)) {
        return new WP_Error('invalid_extension', 'Format de fichier non supporté. Formats acceptés : ' . implode(', ', array_map('strtoupper', $allowed_extensions)));
    }
    
    // Vérification de la taille
    $max_size = wp_max_upload_size();
    if ($file['size'] > $max_size) {
        return new WP_Error('file_too_large', 'Fichier trop volumineux. Taille maximum : ' . size_format($max_size));
    }
    
    // Vérification du nom de fichier (sécurité)
    if (!iris_is_safe_filename($file['name'])) {
        return new WP_Error('unsafe_filename', 'Nom de fichier non sécurisé. Utilisez uniquement des lettres, chiffres, tirets et points.');
    }
    
    // Vérification MIME type (sécurité renforcée)
    $allowed_mimes = array(
        'image/jpeg',
        'image/tiff',
        'image/x-canon-cr3',
        'image/x-canon-cr2', 
        'image/x-nikon-nef',
        'image/x-sony-arw',
        'image/x-adobe-dng',
        'image/x-olympus-orf',
        'image/x-fuji-raf',
        'image/x-panasonic-rw2',
        'image/png',
        'application/octet-stream' // Pour certains RAW
    );
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if ($mime_type && !in_array($mime_type, $allowed_mimes)) {
            // Log pour debug mais pas de blocage strict pour les RAW
            iris_log_error("MIME type non standard détecté: {$mime_type} pour {$file['name']}");
        }
    }
    
    return true;
}

/**
 * Vérification de la sécurité d'un nom de fichier
 * 
 * @since 1.1.1
 * @param string $filename Nom du fichier
 * @return bool Fichier sûr
 */
function iris_is_safe_filename($filename) {
    // Caractères autorisés : lettres, chiffres, tirets, underscores, points, espaces
    $safe_pattern = '/^[a-zA-Z0-9._\-\s]+$/';
    
    // Vérifier le pattern
    if (!preg_match($safe_pattern, $filename)) {
        return false;
    }
    
    // Vérifier qu'il n'y a pas de double extensions dangereuses
    if (preg_match('/\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)(\.|$)/i', $filename)) {
        return false;
    }
    
    return true;
}

/**
 * Création d'un répertoire d'upload sécurisé
 * 
 * @since 1.1.1
 * @return array|WP_Error Informations du répertoire ou erreur
 */
function iris_create_secure_upload_directory() {
    $upload_dir = wp_upload_dir();
    
    if ($upload_dir['error']) {
        return new WP_Error('upload_dir_error', 'Erreur du répertoire d\'upload WordPress: ' . $upload_dir['error']);
    }
    
    $iris_dir = $upload_dir['basedir'] . '/iris-process';
    
    // Créer le répertoire s'il n'existe pas
    if (!file_exists($iris_dir)) {
        if (!wp_mkdir_p($iris_dir)) {
            return new WP_Error('mkdir_failed', 'Impossible de créer le répertoire iris-process');
        }
        
        // Créer un fichier .htaccess pour la sécurité
        $htaccess_content = "# Iris Process Security\n";
        $htaccess_content .= "Options -Indexes\n";
        $htaccess_content .= "Options -ExecCGI\n";
        $htaccess_content .= "<Files \"*.php\">\n";
        $htaccess_content .= "    Order allow,deny\n";
        $htaccess_content .= "    Deny from all\n";
        $htaccess_content .= "</Files>\n";
        
        file_put_contents($iris_dir . '/.htaccess', $htaccess_content);
        
        // Créer un index.php vide pour la sécurité
        file_put_contents($iris_dir . '/index.php', '<?php // Silence is golden');
    }
    
    // Vérifier les permissions
    if (!is_writable($iris_dir)) {
        return new WP_Error('not_writable', 'Le répertoire iris-process n\'est pas accessible en écriture');
    }
    
    return array(
        'path' => $iris_dir,
        'url' => $upload_dir['baseurl'] . '/iris-process'
    );
}

/**
 * Génération d'un nom de fichier unique et sécurisé
 * 
 * @since 1.1.1
 * @param array $file Informations du fichier
 * @param int $user_id ID de l'utilisateur
 * @return array Informations du fichier sécurisé
 */
function iris_generate_secure_filename($file, $user_id) {
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $base_name = pathinfo($file['name'], PATHINFO_FILENAME);
    
    // Nettoyer le nom de base
    $safe_base = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $base_name);
    $safe_base = substr($safe_base, 0, 50); // Limiter la longueur
    
    // Générer un nom unique
    $timestamp = time();
    $random = wp_generate_password(8, false);
    $unique_name = "iris_{$user_id}_{$timestamp}_{$random}_{$safe_base}.{$extension}";
    
    return array(
        'filename' => $unique_name,
        'original' => $file['name'],
        'extension' => $extension,
        'size' => $file['size']
    );
}

/**
 * Validation finale d'un fichier sauvegardé
 * 
 * @since 1.1.1
 * @param string $file_path Chemin du fichier sauvegardé
 * @return true|WP_Error Validation réussie ou erreur
 */
function iris_validate_saved_file($file_path) {
    // Vérifier que le fichier existe
    if (!file_exists($file_path)) {
        return new WP_Error('file_not_saved', 'Le fichier n\'a pas été sauvegardé correctement');
    }
    
    // Vérifier la taille
    $file_size = filesize($file_path);
    if ($file_size === false || $file_size === 0) {
        return new WP_Error('empty_file', 'Le fichier sauvegardé est vide');
    }
    
    // Vérifier que ce n'est pas un fichier PHP déguisé
    $file_start = file_get_contents($file_path, false, null, 0, 10);
    if (strpos($file_start, '<?php') === 0) {
        return new WP_Error('php_file_detected', 'Fichier PHP détecté - upload refusé pour sécurité');
    }
    
    return true;
}

/**
 * Vérification du statut d'un traitement
 * 
 * @since 1.0.0
 * @since 1.1.1 Validation sécurisée
 * @return void
 */
function iris_check_process_status() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'iris_upload_nonce')) {
        wp_send_json_error('Erreur de sécurité');
        return;
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('Utilisateur non connecté');
        return;
    }
    
    $process_id = isset($_POST['process_id']) ? intval($_POST['process_id']) : 0;
    if ($process_id <= 0) {
        wp_send_json_error('ID de processus invalide');
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'iris_image_processes';
    
    $process = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d AND user_id = %d",
        $process_id, 
        $user_id
    ));
    
    if (!$process) {
        wp_send_json_error('Traitement non trouvé');
        return;
    }
    
    wp_send_json_success(array(
        'status' => $process->status,
        'process_id' => $process->id,
        'original_filename' => $process->original_filename,
        'created_at' => $process->created_at,
        'updated_at' => $process->updated_at,
        'processing_start_time' => $process->processing_start_time,
        'processing_end_time' => $process->processing_end_time,
        'error_message' => $process->error_message
    ));
}

/**
 * Gestionnaire de téléchargement sécurisé
 * 
 * @since 1.0.0
 * @since 1.1.1 Sécurité renforcée
 * @return void
 */
function iris_handle_download() {
    $process_id = isset($_GET['process_id']) ? intval($_GET['process_id']) : 0;
    $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';
    
    if (!$process_id || !$nonce) {
        wp_die('Paramètres manquants', 'Erreur de téléchargement', array('response' => 400));
    }
    
    if (!wp_verify_nonce($nonce, 'iris_download_' . $process_id)) {
        wp_die('Erreur de sécurité', 'Accès non autorisé', array('response' => 403));
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_die('Utilisateur non connecté', 'Connexion requise', array('response' => 401));
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'iris_image_processes';
    
    $process = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d AND user_id = %d",
        $process_id, 
        $user_id
    ));
    
    if (!$process) {
        wp_die('Traitement non trouvé', 'Fichier introuvable', array('response' => 404));
    }
    
    if (!$process->processed_file_path || !file_exists($process->processed_file_path)) {
        wp_die('Fichier traité non disponible', 'Fichier introuvable', array('response' => 404));
    }
    
    // Vérification de sécurité supplémentaire
    $upload_dir = wp_upload_dir();
    $allowed_dir = $upload_dir['basedir'] . '/iris-process';
    
    if (strpos(realpath($process->processed_file_path), realpath($allowed_dir)) !== 0) {
        wp_die('Accès non autorisé au fichier', 'Sécurité', array('response' => 403));
    }
    
    // Préparation du téléchargement
    $file_size = filesize($process->processed_file_path);
    $file_name = 'iris_processed_' . basename($process->original_filename);
    
    // Headers pour le téléchargement
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Nettoyer le buffer de sortie
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Lire et envoyer le fichier par chunks pour éviter les problèmes de mémoire
    $chunk_size = 8192;
    $handle = fopen($process->processed_file_path, 'rb');
    
    if ($handle) {
        while (!feof($handle)) {
            echo fread($handle, $chunk_size);
            flush();
        }
        fclose($handle);
    }
    
    exit;
}

/**
 * Enqueue des scripts frontend avec chargement conditionnel
 * 
 * @since 1.1.1
 * @return void
 */
function iris_enqueue_upload_scripts() {
    // Chargement conditionnel - seulement si nécessaire
    if (!iris_should_load_upload_scripts()) {
        return;
    }
    
    wp_enqueue_script('jquery');
    
    // Styles
    wp_enqueue_style(
        'iris-upload', 
        IRIS_PLUGIN_URL . 'assets/iris-upload.css', 
        array(), 
        IRIS_PLUGIN_VERSION
    );
    
    // JavaScript
    wp_enqueue_script(
        'iris-upload', 
        IRIS_PLUGIN_URL . 'assets/iris-upload.js', 
        array('jquery'), 
        IRIS_PLUGIN_VERSION, 
        true
    );
    
    // Localisation avec toutes les données nécessaires
    wp_localize_script('iris-upload', 'iris_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('iris_upload_nonce'),
        'max_file_size' => wp_max_upload_size(),
        'max_file_size_human' => size_format(wp_max_upload_size()),
        'allowed_extensions' => array('cr3', 'cr2', 'nef', 'arw', 'raw', 'dng', 'orf', 'raf', 'rw2', 'jpg', 'jpeg', 'tif', 'tiff', 'png'),
        'strings' => array(
            'select_file' => 'Veuillez sélectionner un fichier',
            'upload_error' => 'Erreur lors de l\'upload',
            'processing' => 'Traitement en cours...',
            'completed' => 'Traitement terminé',
            'failed' => 'Traitement échoué'
        )
    ));
}

/**
 * Détermine si les scripts d'upload doivent être chargés
 * 
 * @since 1.1.1
 * @return bool
 */
function iris_should_load_upload_scripts() {
    global $post;
    
    // Charger sur les pages avec shortcodes Iris
    if ($post && (
        has_shortcode($post->post_content, 'iris_upload_zone') ||
        has_shortcode($post->post_content, 'iris_process_page')
    )) {
        return true;
    }
    
    // Charger sur les pages templates spécifiques
    if (is_page_template('iris-process.php') || is_page_template('page-iris.php')) {
        return true;
    }
    
    // Charger si URL contient iris (pages dédiées)
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'iris') !== false) {
        return true;
    }
    
    // Charger sur les pages d'administration Iris
    if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'iris') === 0) {
        return true;
    }
    
    return false;
}