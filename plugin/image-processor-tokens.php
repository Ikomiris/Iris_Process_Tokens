<?php
/**
 * Plugin Name: Iris Process - Image Processor with Tokens
 * Description: Application WordPress de traitement d'images avec système de jetons et intégration SureCart
 * Version: 1.0.0
 * Author: Ikomiris
 */

// Sécurité - Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Activation du plugin
register_activation_hook(__FILE__, 'iris_process_activate');

function iris_process_activate() {
    iris_create_tables();
    flush_rewrite_rules();
}

/**
 * Création des tables de base de données
 */
function iris_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Table des jetons utilisateur
    $table_tokens = $wpdb->prefix . 'iris_user_tokens';
    $sql_tokens = "CREATE TABLE IF NOT EXISTS $table_tokens (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        token_balance int(11) DEFAULT 0,
        total_purchased int(11) DEFAULT 0,
        total_used int(11) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) $charset_collate;";
    
    // Table des transactions de jetons
    $table_transactions = $wpdb->prefix . 'iris_token_transactions';
    $sql_transactions = "CREATE TABLE IF NOT EXISTS $table_transactions (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        transaction_type varchar(50) NOT NULL,
        tokens_amount int(11) NOT NULL,
        order_id varchar(100) NULL,
        image_process_id int(11) NULL,
        description text NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // Table des traitements d'images
    $table_processes = $wpdb->prefix . 'iris_image_processes';
    $sql_processes = "CREATE TABLE IF NOT EXISTS $table_processes (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        original_filename varchar(255) NOT NULL,
        file_path varchar(500) NOT NULL,
        status varchar(50) DEFAULT 'uploaded',
        processed_file_path varchar(500) NULL,
        processing_start_time datetime NULL,
        processing_end_time datetime NULL,
        error_message text NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_tokens);
    dbDelta($sql_transactions);
    dbDelta($sql_processes);
}

/**
 * Classe de gestion des jetons
 */
class Token_Manager {
    
    /**
     * Obtenir le solde de jetons d'un utilisateur
     */
    public static function get_user_balance($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'iris_user_tokens';
        
        $balance = $wpdb->get_var($wpdb->prepare(
            "SELECT token_balance FROM $table_name WHERE user_id = %d",
            $user_id
        ));
        
        return $balance ? intval($balance) : 0;
    }
    
    /**
     * Ajouter des jetons à un utilisateur
     */
    public static function add_tokens($user_id, $amount, $order_id = null) {
        global $wpdb;
        $table_tokens = $wpdb->prefix . 'iris_user_tokens';
        $table_transactions = $wpdb->prefix . 'iris_token_transactions';
        
        // Mise à jour ou création du solde
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table_tokens (user_id, token_balance, total_purchased) 
             VALUES (%d, %d, %d) 
             ON DUPLICATE KEY UPDATE 
             token_balance = token_balance + %d, 
             total_purchased = total_purchased + %d",
            $user_id, $amount, $amount, $amount, $amount
        ));
        
        // Enregistrement de la transaction
        $wpdb->insert(
            $table_transactions,
            array(
                'user_id' => $user_id,
                'transaction_type' => 'purchase',
                'tokens_amount' => $amount,
                'order_id' => $order_id,
                'description' => 'Achat de jetons'
            ),
            array('%d', '%s', '%d', '%s', '%s')
        );
        
        return true;
    }
    
    /**
     * Utiliser un jeton
     */
    public static function use_token($user_id, $image_process_id) {
        global $wpdb;
        $table_tokens = $wpdb->prefix . 'iris_user_tokens';
        $table_transactions = $wpdb->prefix . 'iris_token_transactions';
        
        // Vérifier le solde
        $current_balance = self::get_user_balance($user_id);
        if ($current_balance < 1) {
            return false;
        }
        
        // Déduire le jeton
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_tokens 
             SET token_balance = token_balance - 1, total_used = total_used + 1 
             WHERE user_id = %d",
            $user_id
        ));
        
        // Enregistrer la transaction
        $wpdb->insert(
            $table_transactions,
            array(
                'user_id' => $user_id,
                'transaction_type' => 'usage',
                'tokens_amount' => -1,
                'image_process_id' => $image_process_id,
                'description' => 'Traitement d\'image'
            ),
            array('%d', '%s', '%d', '%d', '%s')
        );
        
        return true;
    }
}

/**
 * Intégration SureCart (à compléter selon vos besoins)
 */
class SureCart_Integration {
    
    public static function init() {
        add_action('init', array(__CLASS__, 'handle_webhook'));
    }
    
    public static function handle_webhook() {
        if ($_SERVER['REQUEST_URI'] === '/webhook/surecart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if ($data && isset($data['type'])) {
                switch ($data['type']) {
                    case 'order.completed':
                        self::handle_order_completed($data);
                        break;
                    case 'order.refunded':
                        self::handle_order_refunded($data);
                        break;
                }
            }
            
            http_response_code(200);
            exit('OK');
        }
    }
    
    private static function handle_order_completed($data) {
        // Logique d'attribution des jetons selon le produit acheté
        $products = get_option('iris_process_products', array());
        // À implémenter selon votre structure SureCart
    }
    
    private static function handle_order_refunded($data) {
        // Logique de déduction des jetons en cas de remboursement
    }
}

// Initialisation de l'intégration SureCart
SureCart_Integration::init();

// Hooks WordPress
add_action('wp_enqueue_scripts', 'iris_enqueue_upload_scripts');
add_action('wp_ajax_iris_upload_image', 'iris_handle_image_upload');
add_action('wp_ajax_nopriv_iris_upload_image', 'iris_handle_image_upload');
add_action('wp_ajax_iris_check_process_status', 'iris_check_process_status');
add_action('wp_ajax_iris_download', 'iris_handle_download');
add_action('init', 'iris_handle_callback_webhook');
add_action('admin_menu', 'iris_add_admin_menu');

/**
 * Enqueue des scripts et styles pour l'upload
 */
function iris_enqueue_upload_scripts() {
    wp_enqueue_script('iris-upload', plugin_dir_url(__FILE__) . 'assets/iris-upload.js', array('jquery'), '1.0.4', true);
    wp_enqueue_style('iris-upload', plugin_dir_url(__FILE__) . 'assets/iris-upload.css', array(), '1.0.4');
    
    // Variables JavaScript
    wp_localize_script('iris-upload', 'iris_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('iris_upload_nonce'),
        'max_file_size' => wp_max_upload_size(),
        'allowed_types' => array('image/jpeg', 'image/tiff', 'image/x-canon-cr3', 'image/x-nikon-nef', 'image/x-sony-arw')
    ));
}

/**
 * Gestionnaire d'upload d'images
 */
function iris_handle_image_upload() {
    // Vérification du nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iris_upload_nonce')) {
        wp_die('Erreur de sécurité');
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('Utilisateur non connecté');
    }
    
    // Vérification du solde de jetons
    if (Token_Manager::get_user_balance($user_id) < 1) {
        wp_send_json_error('Solde de jetons insuffisant');
    }
    
    // Vérification du fichier uploadé
    if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('Erreur lors de l\'upload du fichier');
    }
    
    $file = $_FILES['image_file'];
    $allowed_extensions = array('jpg', 'jpeg', 'tif', 'tiff', 'cr3', 'nef', 'arw');
    
    // Vérification de l'extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowed_extensions)) {
        wp_send_json_error('Format de fichier non supporté. Formats acceptés : CR3, NEF, ARW, JPG, TIF');
    }
    
    // Création du répertoire d'upload spécifique
    $upload_dir = wp_upload_dir();
    $iris_dir = $upload_dir['basedir'] . '/iris-process';
    
    if (!file_exists($iris_dir)) {
        wp_mkdir_p($iris_dir);
    }
    
    // Génération d'un nom de fichier unique
    $file_name = uniqid('iris_' . $user_id . '_') . '.' . $extension;
    $file_path = $iris_dir . '/' . $file_name;
    
    // Déplacement du fichier
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Création de l'enregistrement de traitement
        $process_id = iris_create_process_record($user_id, $file_name, $file_path);
        
        // Utilisation d'un jeton
        Token_Manager::use_token($user_id, $process_id);
        
        // Envoi vers l'API Python (décommenter quand l'API sera prête)
        // iris_send_to_python_api($file_path, $process_id);
        
        wp_send_json_success(array(
            'message' => 'Fichier uploadé avec succès ! Traitement en cours...',
            'process_id' => $process_id,
            'file_name' => $file_name,
            'remaining_tokens' => Token_Manager::get_user_balance($user_id)
        ));
    } else {
        wp_send_json_error('Erreur lors de la sauvegarde du fichier');
    }
}

/**
 * Création d'un enregistrement de traitement
 */
function iris_create_process_record($user_id, $file_name, $file_path) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'iris_image_processes';
    
    // Insertion de l'enregistrement
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
 * Vérification du statut d'un traitement
 */
function iris_check_process_status() {
    if (!wp_verify_nonce($_POST['nonce'], 'iris_upload_nonce')) {
        wp_die('Erreur de sécurité');
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('Utilisateur non connecté');
    }
    
    $process_id = intval($_POST['process_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'iris_image_processes';
    
    $process = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
        $process_id, $user_id
    ));
    
    if (!$process) {
        wp_send_json_error('Traitement non trouvé');
    }
    
    wp_send_json_success(array(
        'status' => $process->status,
        'process_id' => $process->id,
        'created_at' => $process->created_at,
        'updated_at' => $process->updated_at
    ));
}

/**
 * Shortcode de la zone d'upload
 */
function iris_upload_zone_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<div class="iris-login-required">
                    <h3>Connexion requise</h3>
                    <p>Vous devez être connecté pour utiliser cette fonctionnalité.</p>
                    <a href="' . wp_login_url(get_permalink()) . '" class="iris-login-btn">Se connecter</a>
                </div>';
    }
    
    $user_id = get_current_user_id();
    $token_balance = Token_Manager::get_user_balance($user_id);
    
    ob_start();
    ?>
    <div id="iris-upload-container">
        <div class="iris-token-info">
            <h3>Vos jetons disponibles : <span id="token-balance"><?php echo $token_balance; ?></span></h3>
            <?php if ($token_balance < 1): ?>
                <p class="iris-warning">Vous n'avez pas assez de jetons. <a href="/boutique">Achetez des jetons</a></p>
            <?php endif; ?>
        </div>
        
        <div class="iris-upload-zone" <?php echo $token_balance < 1 ? 'style="opacity: 0.5; pointer-events: none;"' : ''; ?>>
            <form id="iris-upload-form" enctype="multipart/form-data">
                <div class="iris-drop-zone" id="iris-drop-zone">
                    <div class="iris-drop-content">
                        <div class="iris-upload-icon">
                            <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <line x1="16" y1="52" x2="48" y2="52" stroke="#3de9f4" stroke-width="3" stroke-linecap="round"/>
                                <path d="M32 12 L32 44" stroke="#3de9f4" stroke-width="3" stroke-linecap="round"/>
                                <path d="M24 36 L32 44 L40 36" stroke="#3de9f4" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                                <circle cx="32" cy="32" r="28" stroke="#3de9f4" stroke-width="1" opacity="0.2" fill="none"/>
                            </svg>
                        </div>
                        <h4>Glissez votre image ici ou cliquez pour sélectionner</h4>
                        <p>Formats supportés : CR3, NEF, ARW, JPG, TIF</p>
                        <p>Taille maximum : <?php echo size_format(wp_max_upload_size()); ?></p>
                    </div>
                    <input type="file" id="iris-file-input" name="image_file" accept=".cr3,.nef,.arw,.jpg,.jpeg,.tif,.tiff" style="display: none;">
                </div>
                
                <div id="iris-file-preview" style="display: none;">
                    <div class="iris-file-info">
                        <span id="iris-file-name"></span>
                        <span id="iris-file-size"></span>
                        <button type="button" id="iris-remove-file">×</button>
                    </div>
                </div>
                
                <div class="iris-upload-actions">
                    <button type="submit" id="iris-upload-btn" disabled>
                        <span class="iris-btn-text">Traiter l'image (1 jeton)</span>
                        <span class="iris-btn-loading" style="display: none;">⏳ Traitement en cours...</span>
                    </button>
                </div>
            </form>
        </div>
        
        <div id="iris-upload-result" style="display: none;"></div>
        
        <div id="iris-process-history">
            <h3>Historique des traitements</h3>
            <div id="iris-history-list">
                <?php echo iris_get_user_process_history($user_id); ?>
            </div>
        </div>
    </div>
    
    <style>
    .iris-login-required {
        background: #0C2D39;
        color: #F4F4F2;
        padding: 40px;
        border-radius: 12px;
        text-align: center;
        border: none;
        font-family: 'Lato', sans-serif;
    }
    
    .iris-login-required h3 {
        color: #F4F4F2;
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 16px;
        text-transform: uppercase;
    }
    
    .iris-login-btn {
        display: inline-block;
        background: #F05A28;
        color: #F4F4F2;
        padding: 12px 24px;
        border-radius: 24px;
        text-decoration: none;
        font-weight: 700;
        text-transform: uppercase;
        transition: all 0.3s ease;
        margin-top: 16px;
    }
    
    .iris-login-btn:hover {
        background: #3de9f4;
        color: #0C2D39;
        transform: translateY(-2px);
        text-decoration: none;
    }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('iris_upload_zone', 'iris_upload_zone_shortcode');

/**
 * Récupération de l'historique des traitements utilisateur
 */
function iris_get_user_process_history($user_id, $limit = 10) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'iris_image_processes';
    $processes = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
        $user_id, $limit
    ));
    
    if (empty($processes)) {
        return '<p style="color: #124C58; text-align: center; padding: 20px; font-family: \'Lato\', sans-serif;">Aucun traitement effectué pour le moment.</p>';
    }
    
    $output = '<div class="iris-history-items">';
    foreach ($processes as $process) {
        $status_class = 'iris-status-' . $process->status;
        $status_text = iris_get_status_text($process->status);
        
        $output .= '<div class="iris-history-item ' . $status_class . '">';
        $output .= '<div class="iris-history-info">';
        $output .= '<strong>' . esc_html($process->original_filename) . '</strong>';
        $output .= '<span class="iris-status">' . $status_text . '</span>';
        $output .= '<span class="iris-date">' . date('d/m/Y H:i', strtotime($process->created_at)) . '</span>';
        $output .= '</div>';
        
        if ($process->status === 'completed' && $process->processed_file_path) {
            $output .= '<div class="iris-download">';
            $output .= '<a href="' . iris_get_download_url($process->id) . '" class="iris-download-btn">Télécharger</a>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
    }
    $output .= '</div>';
    
    return $output;
}

/**
 * Conversion du statut en texte lisible
 */
function iris_get_status_text($status) {
    $statuses = array(
        'uploaded' => 'Uploadé',
        'processing' => 'En cours de traitement',
        'completed' => 'Terminé',
        'error' => 'Erreur'
    );
    
    return isset($statuses[$status]) ? $statuses[$status] : $status;
}

/**
 * URL de téléchargement sécurisée
 */
function iris_get_download_url($process_id) {
    return add_query_arg(array(
        'action' => 'iris_download',
        'process_id' => $process_id,
        'nonce' => wp_create_nonce('iris_download_' . $process_id)
    ), admin_url('admin-ajax.php'));
}

/**
 * Gestionnaire de téléchargement sécurisé
 */
function iris_handle_download() {
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

/**
 * Fonction pour envoyer vers l'API Python (à décommenter quand l'API sera prête)
 */
function iris_send_to_python_api($file_path, $process_id) {
    // URL de votre API Python (à configurer)
    $python_api_url = get_option('iris_python_api_url', 'https://votre-api-python.com/process-image');
    
    $curl_data = array(
        'file_path' => $file_path,
        'process_id' => $process_id,
        'callback_url' => home_url('/webhook/iris-callback')
    );
    
    // Configuration cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $python_api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($curl_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . get_option('iris_api_token', '')
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        iris_update_process_status($process_id, 'processing');
        return true;
    } else {
        iris_update_process_status($process_id, 'error', null, 'Erreur lors de l\'envoi vers l\'API Python');
        return false;
    }
}

/**
 * Mise à jour du statut d'un traitement
 */
function iris_update_process_status($process_id, $status, $processed_file_path = null, $error_message = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'iris_image_processes';
    
    $update_data = array(
        'status' => $status,
        'updated_at' => current_time('mysql')
    );
    
    if ($processed_file_path) {
        $update_data['processed_file_path'] = $processed_file_path;
    }
    
    if ($error_message) {
        $update_data['error_message'] = $error_message;
    }
    
    if ($status === 'processing') {
        $update_data['processing_start_time'] = current_time('mysql');
    }
    
    if ($status === 'completed' || $status === 'error') {
        $update_data['processing_end_time'] = current_time('mysql');
    }
    
    $wpdb->update(
        $table_name,
        $update_data,
        array('id' => $process_id),
        null,
        array('%d')
    );
}

/**
 * Webhook de callback depuis l'API Python
 */
function iris_handle_callback_webhook() {
    if ($_SERVER['REQUEST_URI'] === '/webhook/iris-callback' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data || !isset($data['process_id'])) {
            http_response_code(400);
            exit('Invalid data');
        }
        
        $process_id = intval($data['process_id']);
        $status = sanitize_text_field($data['status']);
        $processed_file_path = isset($data['processed_file_path']) ? sanitize_text_field($data['processed_file_path']) : null;
        $error_message = isset($data['error_message']) ? sanitize_text_field($data['error_message']) : null;
        
        iris_update_process_status($process_id, $status, $processed_file_path, $error_message);
        
        // Optionnel : envoyer une notification email à l'utilisateur
        if ($status === 'completed') {
            iris_send_completion_notification($process_id);
        }
        
        http_response_code(200);
        exit('OK');
    }
}

/**
 * Envoi d'une notification de fin de traitement
 */
function iris_send_completion_notification($process_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'iris_image_processes';
    
    $process = $wpdb->get_row($wpdb->prepare(
        "SELECT p.*, u.user_email, u.display_name 
         FROM $table_name p 
         JOIN {$wpdb->users} u ON p.user_id = u.ID 
         WHERE p.id = %d",
        $process_id
    ));
    
    if (!$process) {
        return false;
    }
    
    $subject = 'Votre image Iris Process est prête !';
    $download_url = iris_get_download_url($process_id);
    
    $message = "
    Bonjour {$process->display_name},
    
    Votre image '{$process->original_filename}' a été traitée avec succès !
    
    Vous pouvez la télécharger en cliquant sur le lien suivant :
    {$download_url}
    
    Cordialement,
    L'équipe Iris Process
    ";
    
    return wp_mail($process->user_email, $subject, $message);
}

/**
 * Pages d'administration
 */
function iris_add_admin_menu() {
    add_menu_page(
        'Iris Process',
        'Iris Process',
        'manage_options',
        'iris-process',
        'iris_admin_page',
        'dashicons-images-alt2',
        30
    );
    
    
    add_submenu_page(
        'iris-process',
        'Configuration',
        'Configuration',
        'manage_options',
        'iris-config',
        'iris_config_admin_page'
    );
}

/**
 * Page d'administration principale
 */
function iris_admin_page() {
    global $wpdb;
    
    // Statistiques générales
    $table_tokens = $wpdb->prefix . 'iris_user_tokens';
    $table_processes = $wpdb->prefix . 'iris_image_processes';
    
    $total_users = $wpdb->get_var("SELECT COUNT(*) FROM $table_tokens");
    $total_processes = $wpdb->get_var("SELECT COUNT(*) FROM $table_processes");
    $pending_processes = $wpdb->get_var("SELECT COUNT(*) FROM $table_processes WHERE status IN ('uploaded', 'processing')");
    $completed_processes = $wpdb->get_var("SELECT COUNT(*) FROM $table_processes WHERE status = 'completed'");
    $error_processes = $wpdb->get_var("SELECT COUNT(*) FROM $table_processes WHERE status = 'error'");
    $total_tokens_used = $wpdb->get_var("SELECT SUM(total_used) FROM $table_tokens");
    $total_tokens_purchased = $wpdb->get_var("SELECT SUM(total_purchased) FROM $table_tokens");
    
    // Processus récents
    $recent_processes = $wpdb->get_results("
        SELECT p.*, u.display_name, u.user_email 
        FROM $table_processes p 
        JOIN {$wpdb->users} u ON p.user_id = u.ID 
        ORDER BY p.created_at DESC 
        LIMIT 10
    ");
    
    ?>
    <div class="wrap">
        <h1>Iris Process - Tableau de bord</h1>
        
        <div class="iris-admin-stats">
            <div class="iris-stat-card iris-stat-primary">
                <h3>Utilisateurs actifs</h3>
                <p class="iris-stat-number"><?php echo number_format($total_users); ?></p>
                <span class="iris-stat-label">Comptes avec jetons</span>
            </div>
            
            <div class="iris-stat-card iris-stat-success">
                <h3>Traitements réussis</h3>
                <p class="iris-stat-number"><?php echo number_format($completed_processes); ?></p>
                <span class="iris-stat-label">Images traitées</span>
            </div>
            
            <div class="iris-stat-card iris-stat-warning">
                <h3>En cours de traitement</h3>
                <p class="iris-stat-number"><?php echo number_format($pending_processes); ?></p>
                <span class="iris-stat-label">Files d'attente</span>
            </div>
            
            <div class="iris-stat-card iris-stat-info">
                <h3>Jetons utilisés</h3>
                <p class="iris-stat-number"><?php echo number_format($total_tokens_used); ?></p>
                <span class="iris-stat-label">Total consommé</span>
            </div>
        </div>
        
        <div class="iris-admin-grid">
            <div class="iris-admin-section">
                <h2>Activité récente</h2>
                <div class="iris-recent-activity">
                    <?php if (empty($recent_processes)): ?>
                        <p>Aucune activité récente.</p>
                    <?php else: ?>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th>Utilisateur</th>
                                    <th>Fichier</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_processes as $process): ?>
                                <tr>
                                    <td><?php echo esc_html($process->display_name); ?></td>
                                    <td><?php echo esc_html($process->original_filename); ?></td>
                                    <td>
                                        <span class="iris-status-badge iris-status-<?php echo $process->status; ?>">
                                            <?php echo iris_get_status_text($process->status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($process->created_at)); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="iris-admin-section">
                <h2>Statistiques détaillées</h2>
                <div class="iris-detailed-stats">
                    <div class="iris-stat-item">
                        <span class="iris-stat-title">Total des traitements</span>
                        <span class="iris-stat-value"><?php echo number_format($total_processes); ?></span>
                    </div>
                    <div class="iris-stat-item">
                        <span class="iris-stat-title">Taux de réussite</span>
                        <span class="iris-stat-value">
                            <?php 
                            $success_rate = $total_processes > 0 ? round(($completed_processes / $total_processes) * 100, 1) : 0;
                            echo $success_rate . '%';
                            ?>
                        </span>
                    </div>
                    <div class="iris-stat-item">
                        <span class="iris-stat-title">Erreurs</span>
                        <span class="iris-stat-value"><?php echo number_format($error_processes); ?></span>
                    </div>
                    <div class="iris-stat-item">
                        <span class="iris-stat-title">Jetons achetés</span>
                        <span class="iris-stat-value"><?php echo number_format($total_tokens_purchased); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .iris-admin-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .iris-stat-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #3de9f4;
        }
        
        .iris-stat-card.iris-stat-primary { border-left-color: #0C2D39; }
        .iris-stat-card.iris-stat-success { border-left-color: #3de9f4; }
        .iris-stat-card.iris-stat-warning { border-left-color: #F05A28; }
        .iris-stat-card.iris-stat-info { border-left-color: #124C58; }
        
        .iris-stat-card h3 {
            margin: 0 0 10px 0;
            color: #0C2D39;
            font-size: 16px;
            font-weight: 600;
        }
        
        .iris-stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #3de9f4;
            margin: 10px 0;
            line-height: 1;
        }
        
        .iris-stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .iris-admin-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-top: 30px;
        }
        
        .iris-admin-section {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
        }
        
        .iris-admin-section h2 {
            margin: 0 0 20px 0;
            color: #0C2D39;
            font-size: 20px;
            border-bottom: 2px solid #3de9f4;
            padding-bottom: 10px;
        }
        
        .iris-status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .iris-status-badge.iris-status-completed {
            background: #3de9f4;
            color: #0C2D39;
        }
        
        .iris-status-badge.iris-status-processing {
            background: #F05A28;
            color: white;
        }
        
        .iris-status-badge.iris-status-error {
            background: #dc3545;
            color: white;
        }
        
        .iris-status-badge.iris-status-uploaded {
            background: #124C58;
            color: white;
        }
        
        .iris-detailed-stats {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .iris-stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .iris-stat-title {
            color: #0C2D39;
            font-weight: 500;
        }
        
        .iris-stat-value {
            color: #3de9f4;
            font-weight: 700;
            font-size: 18px;
        }
        
        @media (max-width: 768px) {
            .iris-admin-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
    </div>
    <?php
}

/**
 * Page d'administration des traitements
 */
function iris_processes_admin_page() {
    global $wpdb;
    
    // Pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Filtres
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $where_clause = '';
    if ($status_filter) {
        $where_clause = $wpdb->prepare(" WHERE p.status = %s", $status_filter);
    }
    
    $table_name = $wpdb->prefix . 'iris_image_processes';
    
    // Comptage total
    $total_items = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM $table_name p 
        JOIN {$wpdb->users} u ON p.user_id = u.ID 
        $where_clause
    ");
    
    // Récupération des données
    $processes = $wpdb->get_results($wpdb->prepare("
        SELECT p.*, u.display_name, u.user_email 
        FROM $table_name p 
        JOIN {$wpdb->users} u ON p.user_id = u.ID 
        $where_clause
        ORDER BY p.created_at DESC 
        LIMIT %d OFFSET %d
    ", $per_page, $offset));
    
    $total_pages = ceil($total_items / $per_page);
    
    ?>
    <div class="wrap">
        <h1>Traitements d'images</h1>
        
        <!-- Filtres -->
        <div class="iris-filters">
            <form method="get">
                <input type="hidden" name="page" value="iris-processes">
                <select name="status">
                    <option value="">Tous les statuts</option>
                    <option value="uploaded" <?php selected($status_filter, 'uploaded'); ?>>Uploadé</option>
                    <option value="processing" <?php selected($status_filter, 'processing'); ?>>En cours</option>
                    <option value="completed" <?php selected($status_filter, 'completed'); ?>>Terminé</option>
                    <option value="error" <?php selected($status_filter, 'error'); ?>>Erreur</option>
                </select>
                <input type="submit" class="button" value="Filtrer">
                <a href="<?php echo admin_url('admin.php?page=iris-processes'); ?>" class="button">Réinitialiser</a>
            </form>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Utilisateur</th>
                    <th>Fichier original</th>
                    <th>Statut</th>
                    <th>Date de création</th>
                    <th>Durée de traitement</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($processes)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px;">
                        Aucun traitement trouvé.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($processes as $process): ?>
                    <tr>
                        <td><?php echo $process->id; ?></td>
                        <td>
                            <strong><?php echo esc_html($process->display_name); ?></strong><br>
                            <small><?php echo esc_html($process->user_email); ?></small>
                        </td>
                        <td>
                            <strong><?php echo esc_html($process->original_filename); ?></strong><br>
                            <small>Taille: <?php echo iris_get_file_size($process->file_path); ?></small>
                        </td>
                        <td>
                            <span class="iris-status-badge iris-status-<?php echo $process->status; ?>">
                                <?php echo iris_get_status_text($process->status); ?>
                            </span>
                            <?php if ($process->error_message): ?>
                                <br><small style="color: #dc3545;"><?php echo esc_html($process->error_message); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($process->created_at)); ?></td>
                        <td>
                            <?php 
                            if ($process->processing_start_time && $process->processing_end_time) {
                                $start = new DateTime($process->processing_start_time);
                                $end = new DateTime($process->processing_end_time);
                                $duration = $start->diff($end);
                                echo $duration->format('%H:%I:%S');
                            } elseif ($process->processing_start_time) {
                                echo 'En cours...';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <div class="iris-action-buttons">
                                <?php if ($process->status === 'completed' && $process->processed_file_path): ?>
                                    <a href="<?php echo iris_get_download_url($process->id); ?>" class="button button-small">Télécharger</a>
                                <?php endif; ?>
                                
                                <?php if ($process->status === 'error'): ?>
                                    <button class="button button-small iris-retry-btn" data-process-id="<?php echo $process->id; ?>">Relancer</button>
                                <?php endif; ?>
                                
                                <button class="button button-small iris-view-details" data-process-id="<?php echo $process->id; ?>">Détails</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="iris-pagination">
            <?php
            $pagination_args = array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo; Précédent',
                'next_text' => 'Suivant &raquo;',
                'total' => $total_pages,
                'current' => $current_page,
                'show_all' => false,
                'type' => 'plain',
            );
            echo paginate_links($pagination_args);
            ?>
        </div>
        <?php endif; ?>
        
        <style>
        .iris-filters {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .iris-filters form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .iris-action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .iris-pagination {
            margin: 20px 0;
            text-align: center;
        }
        
        .iris-pagination .page-numbers {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 2px;
            text-decoration: none;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .iris-pagination .page-numbers.current {
            background: #3de9f4;
            color: #0C2D39;
            border-color: #3de9f4;
        }
        
        .iris-view-details, .iris-retry-btn {
            cursor: pointer;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.iris-view-details').on('click', function() {
                var processId = $(this).data('process-id');
                // TODO: Implémenter la modal de détails
                alert('Détails du traitement #' + processId + ' (à implémenter)');
            });
            
            $('.iris-retry-btn').on('click', function() {
                var processId = $(this).data('process-id');
                if (confirm('Êtes-vous sûr de vouloir relancer ce traitement ?')) {
                    // TODO: Implémenter la fonction de relance
                    alert('Relance du traitement #' + processId + ' (à implémenter)');
                }
            });
        });
        </script>
    </div>
    <?php
}

/**
 * Page de configuration
 */
function iris_config_admin_page() {
    // Sauvegarde des paramètres
    if (isset($_POST['submit'])) {
        check_admin_referer('iris_config_save');
        
        update_option('iris_python_api_url', sanitize_url($_POST['python_api_url']));
        update_option('iris_api_token', sanitize_text_field($_POST['api_token']));
        update_option('iris_max_file_size', intval($_POST['max_file_size']));
        update_option('iris_email_notifications', isset($_POST['email_notifications']));
        
        echo '<div class="notice notice-success"><p>Configuration sauvegardée avec succès !</p></div>';
    }
    
    // Récupération des valeurs actuelles
    $python_api_url = get_option('iris_python_api_url', '');
    $api_token = get_option('iris_api_token', '');
    $max_file_size = get_option('iris_max_file_size', 100);
    $email_notifications = get_option('iris_email_notifications', true);
    
    ?>
    <div class="wrap">
        <h1>Configuration Iris Process</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('iris_config_save'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">URL de l'API Python</th>
                    <td>
                        <input type="url" name="python_api_url" value="<?php echo esc_attr($python_api_url); ?>" class="regular-text" />
                        <p class="description">URL complète de votre API Python pour le traitement des images.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Token d'authentification API</th>
                    <td>
                        <input type="password" name="api_token" value="<?php echo esc_attr($api_token); ?>" class="regular-text" />
                        <p class="description">Token Bearer pour l'authentification avec l'API Python.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Taille maximum des fichiers (MB)</th>
                    <td>
                        <input type="number" name="max_file_size" value="<?php echo esc_attr($max_file_size); ?>" min="1" max="500" />
                        <p class="description">Taille maximum autorisée pour les uploads d'images.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Notifications email</th>
                    <td>
                        <label>
                            <input type="checkbox" name="email_notifications" <?php checked($email_notifications); ?> />
                            Envoyer un email à l'utilisateur quand le traitement est terminé
                        </label>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Sauvegarder la configuration'); ?>
        </form>
        
        <hr>
        
        <h2>Test de connexion API</h2>
        <div class="iris-api-test">
            <button type="button" id="test-api-connection" class="button button-secondary">Tester la connexion</button>
            <div id="api-test-result" style="margin-top: 10px;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-api-connection').on('click', function() {
                var $button = $(this);
                var $result = $('#api-test-result');
                
                $button.prop('disabled', true).text('Test en cours...');
                $result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'iris_test_api_connection',
                        nonce: '<?php echo wp_create_nonce('iris_test_api'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-success inline"><p>✅ Connexion réussie !</p></div>');
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>❌ Erreur: ' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error inline"><p>❌ Erreur de connexion</p></div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Tester la connexion');
                    }
                });
            });
        });
        </script>
    </div>
    <?php
}

/**
 * Fonction utilitaire pour obtenir la taille d'un fichier
 */
function iris_get_file_size($file_path) {
    if (file_exists($file_path)) {
        return size_format(filesize($file_path));
    }
    return 'N/A';
}

/**
 * AJAX pour tester la connexion API
 */
add_action('wp_ajax_iris_test_api_connection', 'iris_test_api_connection_ajax');

function iris_test_api_connection_ajax() {
    check_ajax_referer('iris_test_api', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission insuffisante');
    }
    
    $api_url = get_option('iris_python_api_url');
    $api_token = get_option('iris_api_token');
    
    if (empty($api_url)) {
        wp_send_json_error('URL de l\'API non configurée');
    }
    
    // Test de ping vers l'API
    $test_url = rtrim($api_url, '/') . '/health';
    
    $args = array(
        'timeout' => 10,
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_token,
            'Content-Type' => 'application/json'
        )
    );
    
    $response = wp_remote_get($test_url, $args);
    
    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    
    if ($response_code === 200) {
        wp_send_json_success('Connexion établie avec succès');
    } else {
        wp_send_json_error('Code de réponse: ' . $response_code);
    }
}

// Shortcodes disponibles
add_shortcode('user_token_balance', 'iris_user_token_balance_shortcode');
add_shortcode('token_history', 'iris_token_history_shortcode');
add_shortcode('iris_process_page', 'iris_upload_zone_shortcode'); // Alias pour compatibilité

/**
 * Shortcode pour afficher le solde de jetons
 */
function iris_user_token_balance_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<span class="iris-login-required">Connexion requise</span>';
    }
    
    $user_id = get_current_user_id();
    $balance = Token_Manager::get_user_balance($user_id);
    
    return '<span class="iris-token-balance">' . $balance . '</span>';
}

/**
 * Shortcode pour l'historique des jetons
 */
function iris_token_history_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limit' => 10
    ), $atts);
    
    if (!is_user_logged_in()) {
        return '<p class="iris-login-required">Connexion requise pour voir l\'historique.</p>';
    }
    
    $user_id = get_current_user_id();
    $limit = intval($atts['limit']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'iris_token_transactions';
    
    $transactions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
        $user_id, $limit
    ));
    
    if (empty($transactions)) {
        return '<p>Aucune transaction trouvée.</p>';
    }
    
    $output = '<div class="iris-token-history">';
    foreach ($transactions as $transaction) {
        $type_class = $transaction->transaction_type === 'purchase' ? 'purchase' : 'usage';
        $sign = $transaction->tokens_amount > 0 ? '+' : '';
        
        $output .= '<div class="iris-transaction-item iris-' . $type_class . '">';
        $output .= '<span class="iris-transaction-amount">' . $sign . $transaction->tokens_amount . '</span>';
        $output .= '<span class="iris-transaction-desc">' . esc_html($transaction->description) . '</span>';
        $output .= '<span class="iris-transaction-date">' . date('d/m/Y', strtotime($transaction->created_at)) . '</span>';
        $output .= '</div>';
    }
    $output .= '</div>';
    
    return $output;
}

?>