<?php
/**
 * Plugin Name: Iris Process - Image Processor with Tokens
 * Plugin URI: https://iris4pro.com
 * Description: Application WordPress de traitement d'images avec système de jetons et intégration SureCart. Permet le traitement d'images RAW via API Python avec gestion complète des tokens utilisateur.
 * Version: 1.0.6
 * Author: Ikomiris
 * Author URI: https://iris4pro.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: iris-process-tokens
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * Network: false
 * 
 * @package IrisProcessTokens
 * @version 1.0.6
 * @author Ikomiris
 * @copyright 2025 iris4pro.com
 * 
 * Développé avec l'assistance de Claude.ai (Anthropic)
 * Environnement de développement : VS Code + SFTP + GitHub
 * Serveur de production : Hostinger.com
 */

// Sécurité - Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit('Accès direct interdit.');
}

/**
 * Définition des constantes du plugin
 * 
 * @since 1.0.0
 */
define('IRIS_PLUGIN_VERSION', '1.0.6');
define('IRIS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IRIS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('IRIS_API_URL', 'http://54.155.119.226:8000');

/**
 * Configuration des formats de fichiers supportés
 * 
 * @since 1.0.6
 */
define('IRIS_MAX_FILE_SIZE', 500 * 1024 * 1024); // 500 MB en bytes
define('IRIS_ALLOWED_EXTENSIONS', array(
    // Formats RAW
    'cr2', 'cr3', 'crw',           // Canon
    'nef', 'nrw',                 // Nikon
    'arw', 'srf', 'sr2',          // Sony
    'raw', 'rw2', 'rwl',          // Panasonic/Leica
    'ptx', 'pef',                 // Pentax
    'orf',                        // Olympus
    'raf',                        // Fujifilm
    'srw',                        // Samsung
    'dng',                        // Adobe/Standard ouvert
    // Formats d'images standards
    'jpg', 'jpeg',                // JPEG
    'tif', 'tiff',                // TIFF
    'png',                        // PNG
    'bmp',                        // Bitmap
    'webp'                        // WebP
));
define('IRIS_ALLOWED_MIME_TYPES', array(
    // RAW formats (détection basique)
    'application/octet-stream',    // Format générique pour RAW
    'image/x-canon-cr2',
    'image/x-canon-cr3', 
    'image/x-canon-crw',
    'image/x-nikon-nef',
    'image/x-sony-arw',
    'image/x-panasonic-raw',
    'image/x-olympus-orf',
    'image/x-fuji-raf',
    'image/x-adobe-dng',
    // Formats standards
    'image/jpeg',
    'image/tiff',
    'image/png',
    'image/bmp',
    'image/webp'
));

/**
 * Classe principale du plugin Iris Process
 * 
 * Gère l'initialisation du plugin, les hooks WordPress,
 * et coordonne tous les modules du système de traitement d'images.
 * 
 * @since 1.0.0
 */
class IrisProcessTokens {
    
    /**
     * Instance unique de la classe (Singleton)
     * 
     * @since 1.0.0
     * @var IrisProcessTokens|null
     */
    private static $instance = null;
    
    /**
     * Constructeur privé pour le pattern Singleton
     * 
     * @since 1.0.0
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Récupère l'instance unique de la classe
     * 
     * @since 1.0.0
     * @return IrisProcessTokens
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialise les hooks WordPress
     * 
     * @since 1.0.0
     * @return void
     */
    private function init_hooks() {
        // Hooks d'activation et de désactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, 'iris_process_deactivate');
        
        // Hooks d'initialisation
        add_action('init', array($this, 'plugin_init'));
        add_action('plugins_loaded', 'iris_maybe_update_database');
        
        // Scripts et styles
        add_action('wp_enqueue_scripts', 'iris_enqueue_upload_scripts');
        add_action('admin_enqueue_scripts', 'iris_admin_enqueue_scripts');
        
        // AJAX handlers
        add_action('wp_ajax_iris_upload_image', 'iris_handle_image_upload');
        add_action('wp_ajax_nopriv_iris_upload_image', 'iris_handle_image_upload');
        add_action('wp_ajax_iris_check_process_status', 'iris_check_process_status');
        add_action('wp_ajax_iris_download', 'iris_handle_download');
        add_action('wp_ajax_iris_test_api', 'iris_ajax_test_api');
        
        // REST API
        add_action('rest_api_init', 'iris_register_rest_routes');
        
        // Administration
        add_action('admin_menu', 'iris_add_admin_menu');
        add_action('wp_dashboard_setup', 'iris_add_dashboard_widget');
        
        // Capacités et rôles
        add_action('init', 'iris_add_custom_capabilities');
        
        // Nettoyage automatique
        add_action('iris_daily_cleanup', 'iris_cleanup_old_jobs');
        
        // Intégration SureCart
        add_action('surecart/order_completed', 'iris_handle_surecart_order', 10, 1);
        add_action('iris_job_completed', 'iris_send_completion_email', 10, 3);
        
        // Log d'initialisation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            iris_log_error('Plugin Iris Process initialisé - Version ' . IRIS_PLUGIN_VERSION);
        }
    }
    
    /**
     * Charge les dépendances du plugin
     * 
     * @since 1.0.0
     * @return void
     */
    private function load_dependencies() {
        // Initialiser les classes principales
        if (class_exists('Token_Manager')) {
            // Token_Manager déjà défini dans le code existant
        }
        
        if (class_exists('SureCart_Integration')) {
            SureCart_Integration::init();
        }
    }
    
    /**
     * Actions lors de l'activation du plugin
     * 
     * @since 1.0.0
     * @return void
     */
    public function activate() {
        // Créer les tables de base de données
        iris_create_tables();
        
        // Programmer le nettoyage automatique
        if (!wp_next_scheduled('iris_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'iris_daily_cleanup');
        }
        
        // Vider le cache des règles de réécriture
        flush_rewrite_rules();
        
        // Définir la version de la base de données
        update_option('iris_process_db_version', IRIS_PLUGIN_VERSION);
        
        // Log d'activation
        iris_log_error('Plugin Iris Process activé - Version ' . IRIS_PLUGIN_VERSION);
    }
    
    /**
     * Initialisation du plugin après le chargement de WordPress
     * 
     * @since 1.0.0
     * @return void
     */
    public function plugin_init() {
        // Chargement des traductions
        load_plugin_textdomain(
            'iris-process-tokens',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
        
        // Initialisation des modules
        if (defined('WP_DEBUG') && WP_DEBUG) {
            iris_log_error('Iris Process: Plugin init hook exécuté');
        }
    }
}

// Initialiser le plugin
IrisProcessTokens::get_instance();

/**
 * Fonction helper pour récupérer l'instance du plugin
 * 
 * @since 1.0.0
 * @return IrisProcessTokens
 */
function iris_process_tokens() {
    return IrisProcessTokens::get_instance();
}

/**
 * Activation du plugin
 * 
 * @since 1.0.0
 */
function iris_process_activate() {
    iris_create_tables();
    flush_rewrite_rules();
}

/**
 * Création des tables de base de données
 * 
 * @since 1.0.0
 * @return void
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
    
    // Table des jobs de traitement API
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    $sql_jobs = "CREATE TABLE IF NOT EXISTS $table_jobs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        job_id varchar(100) NOT NULL,
        user_id bigint(20) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        original_file varchar(255) NOT NULL,
        result_files longtext,
        error_message text,
        api_response longtext,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        completed_at datetime,
        PRIMARY KEY (id),
        UNIQUE KEY job_id (job_id),
        KEY user_id (user_id),
        KEY status (status)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_tokens);
    dbDelta($sql_transactions);
    dbDelta($sql_processes);
    dbDelta($sql_jobs);
}

/**
 * Classe de gestion des jetons
 * 
 * Gère toutes les opérations liées aux jetons utilisateur :
 * - Consultation des soldes
 * - Ajout de jetons (achats)
 * - Utilisation de jetons (traitements)
 * - Historique des transactions
 * 
 * @since 1.0.0
 */
class Token_Manager {
    
    /**
     * Obtenir le solde de jetons d'un utilisateur
     * 
     * @since 1.0.0
     * @param int $user_id ID de l'utilisateur
     * @return int Nombre de jetons disponibles
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
     * 
     * @since 1.0.0
     * @param int $user_id ID de l'utilisateur
     * @param int $amount Nombre de jetons à ajouter
     * @param string|null $order_id ID de la commande (optionnel)
     * @return bool Succès de l'opération
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
     * Utiliser un jeton pour un traitement
     * 
     * @since 1.0.0
     * @param int $user_id ID de l'utilisateur
     * @param int $image_process_id ID du traitement d'image
     * @return bool Succès de l'opération
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
 * Intégration SureCart
 * 
 * Gère l'intégration avec SureCart pour l'attribution automatique
 * de jetons lors des achats et la gestion des remboursements.
 * 
 * @since 1.0.0
 */
class SureCart_Integration {
    
    /**
     * Initialise l'intégration SureCart
     * 
     * @since 1.0.0
     * @return void
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'handle_webhook'));
    }
    
    /**
     * Gère les webhooks SureCart
     * 
     * @since 1.0.0
     * @return void
     */
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
    
    /**
     * Traite une commande terminée
     * 
     * @since 1.0.0
     * @param array $data Données de la commande
     * @return void
     */
    private static function handle_order_completed($data) {
        // Logique d'attribution des jetons selon le produit acheté
        $products = get_option('iris_process_products', array());
        // À implémenter selon votre structure SureCart
    }
    
    /**
     * Traite un remboursement de commande
     * 
     * @since 1.0.0
     * @param array $data Données du remboursement
     * @return void
     */
    private static function handle_order_refunded($data) {
        // Logique de déduction des jetons en cas de remboursement
    }
}

// Initialisation de l'intégration SureCart
SureCart_Integration::init();

/**
 * Enqueue des scripts et styles pour l'upload
 * 
 * @since 1.0.0
 * @return void
 */
function iris_enqueue_upload_scripts() {
    // S'assurer que jQuery est chargé
    wp_enqueue_script('jquery');
    
    // Charger le CSS seulement
    wp_enqueue_style('iris-upload', IRIS_PLUGIN_URL . 'assets/iris-upload.css', array(), IRIS_PLUGIN_VERSION);
    
    // Variables JavaScript pour AJAX
    wp_localize_script('jquery', 'iris_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('iris_upload_nonce'),
        'max_file_size' => wp_max_upload_size(),
        'allowed_types' => array('image/jpeg', 'image/tiff', 'image/x-canon-cr3', 'image/x-nikon-nef', 'image/x-sony-arw')
    ));
}

/**
 * Enqueue des scripts et styles pour l'administration
 * 
 * @since 1.0.0
 * @param string $hook_suffix Le suffixe de la page courante
 * @return void
 */
function iris_admin_enqueue_scripts($hook) {
    if (strpos($hook, 'iris') === false) {
        return;
    }
    
    wp_enqueue_style('iris-admin', IRIS_PLUGIN_URL . 'assets/iris-admin.css', array(), IRIS_PLUGIN_VERSION);
    wp_enqueue_script('iris-admin', IRIS_PLUGIN_URL . 'assets/iris-admin.js', array('jquery'), IRIS_PLUGIN_VERSION, true);
}

/**
 * Enregistrement des endpoints REST API
 * 
 * @since 1.0.0
 * @return void
 */
function iris_register_rest_routes() {
    register_rest_route('iris/v1', '/callback', array(
        'methods' => 'POST',
        'callback' => 'iris_handle_api_callback',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('iris/v1', '/status/(?P<job_id>[a-zA-Z0-9_]+)', array(
        'methods' => 'GET',
        'callback' => 'iris_get_job_status_api',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('iris/v1', '/stats', array(
        'methods' => 'GET',
        'callback' => 'iris_get_stats_api',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
}

/**
 * Callback depuis l'API Python
 * 
 * @since 1.0.0
 * @param WP_REST_Request $request Requête REST
 * @return WP_REST_Response|WP_Error Réponse ou erreur
 */
function iris_handle_api_callback($request) {
    global $wpdb;
    
    $data = $request->get_json_params();
    
    if (!isset($data['job_id'])) {
        return new WP_Error('missing_data', 'Job ID manquant', array('status' => 400));
    }
    
    $job_id = sanitize_text_field($data['job_id']);
    $status = sanitize_text_field($data['status']);
    $user_id = intval($data['user_id']);
    
    // Mettre à jour le job en base
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
        
        // Décompter un jeton pour l'utilisateur
        Token_Manager::use_token($user_id, 0);
        
        // Déclencher les hooks de completion
        iris_trigger_job_completion_hooks($job_id, $status, $user_id);
        
        // Log d'activité
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
 * Statut d'un job via API REST
 * 
 * @since 1.0.0
 * @param WP_REST_Request $request Requête REST
 * @return WP_REST_Response|WP_Error Réponse ou erreur
 */
function iris_get_job_status_api($request) {
    global $wpdb;
    
    $job_id = $request->get_param('job_id');
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

/**
 * API REST pour les statistiques (pour l'admin)
 * 
 * @since 1.0.0
 * @return WP_REST_Response Réponse avec les statistiques
 */
function iris_get_stats_api() {
    global $wpdb;
    
    $table_tokens = $wpdb->prefix . 'iris_user_tokens';
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    
    $stats = array(
        'total_users' => $wpdb->get_var("SELECT COUNT(*) FROM $table_tokens"),
        'total_jobs' => $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs"),
        'completed_jobs' => $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status = 'completed'"),
        'pending_jobs' => $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status IN ('pending', 'processing')"),
        'failed_jobs' => $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status = 'failed'"),
        'total_tokens_purchased' => $wpdb->get_var("SELECT SUM(total_purchased) FROM $table_tokens"),
        'total_tokens_used' => $wpdb->get_var("SELECT SUM(total_used) FROM $table_tokens"),
        'api_url' => IRIS_API_URL
    );
    
    return rest_ensure_response($stats);
}

/**
 * Gestionnaire d'upload d'images
 * 
 * @since 1.0.0
 * @return void
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

    // Vérification de la taille (500 MB max)
    if ($file['size'] > IRIS_MAX_FILE_SIZE) {
    wp_send_json_error('Fichier trop volumineux. Taille maximum : ' . size_format(IRIS_MAX_FILE_SIZE));
    }

        // Vérification de l'extension
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, IRIS_ALLOWED_EXTENSIONS)) {
    $allowed_display = array_map('strtoupper', IRIS_ALLOWED_EXTENSIONS);
    wp_send_json_error('Format de fichier non supporté. Formats acceptés : ' . implode(', ', $allowed_display));
    }

    // Vérification basique du MIME type pour sécurité
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Log pour debug
    iris_log_error("Upload - Fichier: {$file['name']}, Extension: $extension, MIME: $mime_type, Taille: " . size_format($file['size']));   
    
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
        
        // Envoi vers l'API Python
        $api_result = iris_send_to_python_api($file_path, $user_id, $process_id);
        
        if (is_wp_error($api_result)) {
            wp_send_json_error($api_result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => 'Fichier uploadé avec succès ! Traitement en cours...',
                'process_id' => $process_id,
                'job_id' => $api_result['job_id'],
                'file_name' => $file_name,
                'remaining_tokens' => Token_Manager::get_user_balance($user_id)
            ));
        }
    } else {
        wp_send_json_error('Erreur lors de la sauvegarde du fichier');
    }
}

/**
 * Envoi vers l'API Python
 * 
 * @since 1.0.0
 * @param string $file_path Chemin du fichier
 * @param int $user_id ID de l'utilisateur
 * @param int $process_id ID du processus
 * @return array|WP_Error Résultat de l'API ou erreur
 */
function iris_send_to_python_api($file_path, $user_id, $process_id) {
    global $wpdb;
    
    // URL de l'API Python
    $api_url = IRIS_API_URL . '/process';
    $callback_url = home_url('/wp-json/iris/v1/callback');
    
    // Vérifier que le fichier existe
    if (!file_exists($file_path)) {
        return new WP_Error('file_not_found', 'Fichier non trouvé: ' . $file_path);
    }
    
    // Préparer le fichier pour l'upload
    $curl_file = new CURLFile($file_path, mime_content_type($file_path), basename($file_path));
    
    // Données pour l'API
    $post_data = array(
        'file' => $curl_file,
        'user_id' => $user_id,
        'callback_url' => $callback_url,
        'processing_options' => json_encode(array())
    );
    
    try {
        // Configuration cURL
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
 * Création d'un enregistrement de traitement
 * 
 * @since 1.0.0
 * @param int $user_id ID de l'utilisateur
 * @param string $file_name Nom du fichier
 * @param string $file_path Chemin du fichier
 * @return int ID de l'enregistrement créé
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
 * 
 * @since 1.0.0
 * @return void
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
 * Shortcode de la zone d'upload - VERSION AVEC INPUT VISIBLE
 * 
 * @since 1.0.0
 * @param array $atts Attributs du shortcode
 * @return string HTML de la zone d'upload
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
        
        <!-- ZONE DE DROP PRINCIPALE -->
        <div class="iris-drop-zone-main" id="iris-drop-zone">
            <div class="iris-drop-content">
                <div class="iris-upload-icon">
                    <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <line x1="16" y1="52" x2="48" y2="52" stroke="#3de9f4" stroke-width="3" stroke-linecap="round"/>
                        <path d="M32 12 L32 44" stroke="#3de9f4" stroke-width="3" stroke-linecap="round"/>
                        <path d="M24 36 L32 44 L40 36" stroke="#3de9f4" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                        <circle cx="32" cy="32" r="28" stroke="#3de9f4" stroke-width="1" opacity="0.2" fill="none"/>
                    </svg>
                </div>
                <h4>Glissez votre image ici</h4>
                <p><strong>Formats RAW :</strong> CR2, CR3, CRW, NEF, NRW, ARW, SRF, SR2, RAW, RW2, RWL, PTX, PEF, ORF, RAF, SRW, DNG</p>
                <p><strong>Formats standards :</strong> JPG, JPEG, TIF, TIFF, PNG, BMP, WEBP</p>
                <p><strong>Taille maximum :</strong> <?php echo size_format(IRIS_MAX_FILE_SIZE); ?></p>
            </div>
        </div>
        
        <!-- BOUTON DE SÉLECTION SÉPARÉ -->
        <div class="iris-file-selector">
            <label for="iris-file-input" class="iris-file-label">
                📂 Ou cliquez ici pour sélectionner un fichier
            </label>
            <input type="file" 
                   id="iris-file-input" 
                   name="image_file" 
                   accept=".cr2,.cr3,.crw,.nef,.nrw,.arw,.srf,.sr2,.raw,.rw2,.rwl,.ptx,.pef,.orf,.raf,.srw,.dng,.jpg,.jpeg,.tif,.tiff,.png,.bmp,.webp"
                   style="display: none;">
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
    
    <?php
    // Styles CSS intégrés
    echo iris_get_upload_styles();
    
    // JavaScript intégré
    echo iris_get_upload_scripts();
    
    return ob_get_clean();
}
add_shortcode('iris_upload_zone', 'iris_upload_zone_shortcode');

/**
 * Styles CSS pour la zone d'upload
 * 
 * @since 1.0.0
 * @return string CSS complet
 */
function iris_get_upload_styles() {
     return '<style>
    .iris-login-required {
        background: #0C2D39;
        color: #F4F4F2;
        padding: 40px;
        border-radius: 12px;
        text-align: center;
        border: none;
        font-family: "Lato", sans-serif;
    }

    .iris-drop-zone-main {
    border: 3px dashed #3de9f4;
    border-radius: 12px;
    padding: 40px 20px;
    text-align: center;
    transition: all 0.3s ease;
    background: rgba(60, 233, 244, 0.1);
    margin-bottom: 20px;
}

.iris-drop-zone-main:hover {
    border-color: #F05A28;
    background: rgba(240, 90, 40, 0.1);
    transform: scale(1.02);
}

.iris-drop-content {
    color: #F4F4F2;
}

.iris-file-selector {
    text-align: center;
    margin-bottom: 20px;
}

.iris-file-label {
    display: inline-block;
    background: #3de9f4;
    color: #0C2D39;
    padding: 15px 30px;
    border-radius: 25px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    border: none;
}

.iris-file-label:hover {
    background: #F05A28;
    color: #F4F4F2;
    transform: translateY(-2px);
}

#iris-file-input {
    display: none !important;
}

    .iris-drop-zone {
    position: relative;
    border: 3px dashed #3de9f4;
    border-radius: 12px;
    padding: 40px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: rgba(60, 233, 244, 0.1);
    overflow: hidden;
    min-height: 200px;
}

.iris-drop-content {
    position: relative;
    z-index: 1;
    pointer-events: none; /* IMPORTANT: Empêche les clics sur le contenu */
    color: #F4F4F2;
}

#iris-file-input {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    opacity: 0 !important;
    cursor: pointer !important;
    z-index: 999 !important;
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
    
    .iris-file-input-styled {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        opacity: 0 !important;
        cursor: pointer !important;
        z-index: 999 !important;
        font-size: 0;
    }
    
    .iris-drop-zone {
        position: relative;
        border: 3px dashed #3de9f4;
        border-radius: 12px;
        padding: 40px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: rgba(60, 233, 244, 0.1);
        overflow: hidden;
    }
    
    .iris-drop-zone:hover {
        border-color: #F05A28;
        background: rgba(240, 90, 40, 0.1);
        transform: scale(1.02);
    }
    
    .iris-drop-content {
        position: relative;
        z-index: 1;
        /* SUPPRIMÉ pointer-events: none; */
        color: #F4F4F2;
    }
    
    #iris-file-preview {
        background: #0C2D39;
        border-radius: 8px;
        padding: 15px;
        margin: 20px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .iris-file-info {
        color: #F4F4F2;
        display: flex;
        gap: 15px;
        align-items: center;
    }
    
    #iris-file-name {
        font-weight: bold;
        color: #3de9f4;
    }
    
    #iris-file-size {
        color: #ccc;
        font-size: 14px;
    }
    
    #iris-remove-file {
        background: #F05A28;
        color: white;
        border: none;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        cursor: pointer;
        font-size: 16px;
        font-weight: bold;
    }
    
    #iris-remove-file:hover {
        background: #e04a1a;
    }
    
    .iris-upload-actions {
        text-align: center;
        margin-top: 20px;
    }
    
    #iris-upload-btn {
        background: #F05A28;
        color: #F4F4F2;
        border: none;
        padding: 15px 30px;
        border-radius: 25px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
    }
    
    #iris-upload-btn:hover:not(:disabled) {
        background: #3de9f4;
        color: #0C2D39;
        transform: translateY(-2px);
    }
    
    #iris-upload-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    #iris-upload-result {
        margin-top: 20px;
    }
    
    .iris-success {
        background: #28a745;
        color: white;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
    }
    
    .iris-error {
        background: #dc3545;
        color: white;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
    }
    
    .iris-success h4,
    .iris-error h4 {
        margin: 0 0 10px 0;
        font-size: 18px;
    }
    
    .iris-success p,
    .iris-error p {
        margin: 5px 0;
    }
    
    #iris-process-history {
        background: #0C2D39;
        color: #F4F4F2;
        padding: 20px;
        border-radius: 12px;
        margin-top: 30px;
    }
    
    #iris-process-history h3 {
        color: #3de9f4;
        margin: 0 0 20px 0;
        font-size: 20px;
        text-align: center;
    }
    
    .iris-history-items {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .iris-history-item {
        background: #15697B;
        padding: 15px;
        border-radius: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .iris-history-info {
        flex: 1;
    }
    
    .iris-history-info strong {
        color: #3de9f4;
        display: block;
        margin-bottom: 5px;
    }
    
    .iris-status {
        background: #F05A28;
        color: white;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 12px;
        margin-right: 10px;
    }
    
    .iris-date {
        color: #ccc;
        font-size: 14px;
    }
    
    .iris-download-btn {
        background: #3de9f4;
        color: #0C2D39;
        padding: 8px 15px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
        transition: all 0.3s ease;
    }
    
    .iris-download-btn:hover {
        background: #2bc9d4;
        text-decoration: none;
        color: #0C2D39;
    }
    
    @media (max-width: 768px) {
        #iris-upload-container {
            padding: 10px;
        }
        
        .iris-drop-zone {
            padding: 20px 10px;
        }
        
        .iris-history-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
    }
    </style>';
}

/**
 * JavaScript pour la zone d'upload
 * 
 * @since 1.0.0
 * @return string JavaScript complet
 */
function iris_get_upload_scripts() {
    return '<script type="text/javascript">
    jQuery(document).ready(function($) {
        console.log("🚀 Iris Upload - Version corrigée");
        
        var dropZone = $("#iris-drop-zone");
        var fileInput = $("#iris-file-input");
        var filePreview = $("#iris-file-preview");
        var fileName = $("#iris-file-name");
        var fileSize = $("#iris-file-size");
        var removeBtn = $("#iris-remove-file");
        var uploadBtn = $("#iris-upload-btn");
        var uploadForm = $("#iris-upload-form");
        var result = $("#iris-upload-result");
        
        var selectedFile = null;
        
        console.log("Éléments trouvés:", {
            dropZone: dropZone.length,
            fileInput: fileInput.length,
            uploadBtn: uploadBtn.length
        });
        
        // Empêcher défaut navigateur
        $(document).on("dragover drop", function(e) {
            e.preventDefault();
        });
        
        // INPUT CHANGE
        fileInput.on("change", function() {
            console.log("📂 Input change détecté !");
            if (this.files && this.files.length > 0) {
                handleFile(this.files[0]);
            }
        });
        
dropZone.on("dragover dragenter", function(e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).css("background-color", "rgba(240, 90, 40, 0.2)");
    console.log("📁 Drag over");
});

dropZone.on("dragleave", function(e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).css("background-color", "rgba(60, 233, 244, 0.1)");
});

dropZone.on("drop", function(e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).css("background-color", "rgba(60, 233, 244, 0.1)");
    console.log("📥 Drop détecté");
    
    var files = e.originalEvent.dataTransfer.files;
    if (files && files.length > 0) {
        handleFile(files[0]);
    }
});
        
        // Traitement fichier
        function handleFile(file) {
            console.log("🔍 Fichier:", file.name, "Taille:", formatSize(file.size));
            
            var ext = file.name.split(".").pop().toLowerCase();
            var allowed = ["cr2", "cr3", "crw", "nef", "nrw", "arw", "srf", "sr2", "raw", "rw2", "rwl", "ptx", "pef", "orf", "raf", "srw", "dng", "jpg", "jpeg", "tif", "tiff", "png", "bmp", "webp"];
            
            if (allowed.indexOf(ext) === -1) {
                alert("Format non supporté: " + ext.toUpperCase());
                return;
            }
            
            var maxSize = 524288000;
            if (file.size > maxSize) {
                alert("Fichier trop volumineux: " + formatSize(file.size) + " (Max: 500 MB)");
                return;
            }
            
            selectedFile = file;
            fileName.text(file.name);
            fileSize.text(formatSize(file.size));
            filePreview.show();
            uploadBtn.prop("disabled", false);
            
            dropZone.css("background-color", "rgba(40, 167, 69, 0.2)");
            console.log("✅ Fichier accepté:", file.name);
        }
        
        // Supprimer fichier
        removeBtn.on("click", function(e) {
            e.preventDefault();
            selectedFile = null;
            fileInput.val("");
            filePreview.hide();
            uploadBtn.prop("disabled", true);
            dropZone.css("background-color", "rgba(60, 233, 244, 0.1)");
            console.log("🗑️ Fichier supprimé");
        });
        
        // Submit formulaire
        uploadForm.on("submit", function(e) {
            e.preventDefault();
            
            if (!selectedFile) {
                alert("Sélectionnez un fichier");
                return;
            }
            
            console.log("🚀 Upload:", selectedFile.name);
            
            uploadBtn.prop("disabled", true);
            uploadBtn.find(".iris-btn-text").hide();
            uploadBtn.find(".iris-btn-loading").show();
            
            var formData = new FormData();
            formData.append("action", "iris_upload_image");
            formData.append("nonce", iris_ajax.nonce);
            formData.append("image_file", selectedFile);
            
            $.ajax({
                url: iris_ajax.ajax_url,
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                timeout: 120000,
                success: function(resp) {
                    console.log("📨 Réponse:", resp);
                    
                    if (resp && resp.success) {
                        var successMsg = "<div style=\\"background:#28a745;color:white;padding:15px;border-radius:8px;text-align:center;\\"><h4>✅ " + resp.data.message + "</h4><p>Jetons restants: " + resp.data.remaining_tokens + "</p><p>Job ID: " + resp.data.job_id + "</p></div>";
                        
                        result.html(successMsg).show();
                        $("#token-balance").text(resp.data.remaining_tokens);
                        removeBtn.click();
                        
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    } else {
                        var errorMsg = "<div style=\\"background:#dc3545;color:white;padding:15px;border-radius:8px;text-align:center;\\"><h4>❌ Erreur</h4><p>" + (resp.data || "Erreur inconnue") + "</p></div>";
                        result.html(errorMsg).show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error("💥 Erreur:", status, error);
                    var errorMsg = "<div style=\\"background:#dc3545;color:white;padding:15px;border-radius:8px;text-align:center;\\"><h4>❌ Erreur de connexion</h4><p>" + status + ": " + error + "</p></div>";
                    result.html(errorMsg).show();
                },
                complete: function() {
                    uploadBtn.prop("disabled", false);
                    uploadBtn.find(".iris-btn-text").show();
                    uploadBtn.find(".iris-btn-loading").hide();
                }
            });
        });
        
        function formatSize(bytes) {
            if (bytes > 1048576) {
                return Math.round(bytes / 1048576) + " MB";
            }
            return Math.round(bytes / 1024) + " KB";
        }
        
        console.log("✅ Iris Upload initialisé !");
    });
    </script>';
}

/**
 * Récupération de l'historique des traitements utilisateur
 * 
 * @since 1.0.0
 * @param int $user_id ID de l'utilisateur
 * @param int $limit Nombre maximum de résultats
 * @return string HTML de l'historique
 */
function iris_get_user_process_history($user_id, $limit = 10) {
    global $wpdb;
    
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    $jobs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_jobs WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
        $user_id, $limit
    ));
    
    if (empty($jobs)) {
        return '<p style="color: #124C58; text-align: center; padding: 20px; font-family: \'Lato\', sans-serif;">Aucun traitement effectué pour le moment.</p>';
    }
    
    $output = '<div class="iris-history-items">';
    foreach ($jobs as $job) {
        $status_class = 'iris-status-' . $job->status;
        $status_text = iris_get_status_text($job->status);
        
        $output .= '<div class="iris-history-item ' . $status_class . '">';
        $output .= '<div class="iris-history-info">';
        $output .= '<strong>' . esc_html($job->original_file) . '</strong>';
        $output .= '<span class="iris-status">' . $status_text . '</span>';
        $output .= '<span class="iris-date">' . date('d/m/Y H:i', strtotime($job->created_at)) . '</span>';
        $output .= '</div>';
        
        if ($job->status === 'completed' && $job->result_files) {
            $files = json_decode($job->result_files, true);
            if ($files) {
                $output .= '<div class="iris-download">';
                foreach ($files as $file) {
                    $download_url = home_url('/wp-json/iris/v1/download/' . $job->job_id . '/' . basename($file));
                    $output .= '<a href="' . esc_url($download_url) . '" class="iris-download-btn" download>Télécharger ' . esc_html(basename($file)) . '</a>';
                }
                $output .= '</div>';
            }
        }
        
        $output .= '</div>';
    }
    $output .= '</div>';
    
    return $output;
}

/**
 * Conversion du statut en texte lisible
 * 
 * @since 1.0.0
 * @param string $status Statut du job
 * @return string Texte lisible
 */
function iris_get_status_text($status) {
    $statuses = array(
        'pending' => 'En attente',
        'processing' => 'En cours de traitement',
        'completed' => 'Terminé',
        'failed' => 'Erreur'
    );
    
    return isset($statuses[$status]) ? $statuses[$status] : $status;
}

/**
 * Gestionnaire de téléchargement sécurisé
 * 
 * @since 1.0.0
 * @return void
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
 * Pages d'administration
 * 
 * @since 1.0.0
 * @return void
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
    
    add_submenu_page(
        'iris-process',
        'Jobs',
        'Jobs',
        'manage_options',
        'iris-jobs',
        'iris_jobs_admin_page'
    );
}

/**
 * Page d'administration principale
 * 
 * @since 1.0.0
 * @return void
 */
function iris_admin_page() {
    global $wpdb;
    
    // Statistiques générales
    $table_tokens = $wpdb->prefix . 'iris_user_tokens';
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    
    $total_users = $wpdb->get_var("SELECT COUNT(*) FROM $table_tokens");
    $total_jobs = $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs");
    $pending_jobs = $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status IN ('pending', 'processing')");
    $completed_jobs = $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status = 'completed'");
    $failed_jobs = $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status = 'failed'");
    $total_tokens_used = $wpdb->get_var("SELECT SUM(total_used) FROM $table_tokens");
    $total_tokens_purchased = $wpdb->get_var("SELECT SUM(total_purchased) FROM $table_tokens");
    
    // Jobs récents
    $recent_jobs = $wpdb->get_results("
        SELECT j.*, u.display_name, u.user_email 
        FROM $table_jobs j 
        JOIN {$wpdb->users} u ON j.user_id = u.ID 
        ORDER BY j.created_at DESC 
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
                <p class="iris-stat-number"><?php echo number_format($completed_jobs); ?></p>
                <span class="iris-stat-label">Images traitées</span>
            </div>
            
            <div class="iris-stat-card iris-stat-warning">
                <h3>En cours</h3>
                <p class="iris-stat-number"><?php echo number_format($pending_jobs); ?></p>
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
                    <?php if (empty($recent_jobs)): ?>
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
                                <?php foreach ($recent_jobs as $job): ?>
                                <tr>
                                    <td><?php echo esc_html($job->display_name); ?></td>
                                    <td><?php echo esc_html($job->original_file); ?></td>
                                    <td>
                                        <span class="iris-status-badge iris-status-<?php echo $job->status; ?>">
                                            <?php echo iris_get_status_text($job->status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($job->created_at)); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="iris-admin-section">
                <h2>API Status</h2>
                <div class="iris-api-status">
                    <p><strong>URL API:</strong> <?php echo IRIS_API_URL; ?></p>
                    <button type="button" id="test-api" class="button">Tester l'API</button>
                    <div id="api-result"></div>
                </div>
            </div>
        </div>
        
        <?php echo iris_get_admin_styles(); ?>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-api').on('click', function() {
                var btn = $(this);
                var result = $('#api-result');
                
                btn.prop('disabled', true).text('Test...');
                
                $.get('<?php echo IRIS_API_URL; ?>/health')
                    .done(function(data) {
                        result.html('<div style="color:green;padding:10px;">✅ API accessible - Status: ' + data.status + '</div>');
                    })
                    .fail(function() {
                        result.html('<div style="color:red;padding:10px;">❌ API inaccessible</div>');
                    })
                    .always(function() {
                        btn.prop('disabled', false).text('Tester l\'API');
                    });
            });
        });
        </script>
    </div>
    <?php
}

/**
 * Styles CSS pour l'administration
 * 
 * @since 1.0.0
 * @return string CSS pour l'admin
 */
function iris_get_admin_styles() {
    return '<style>
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
        
        .iris-status-badge.iris-status-failed {
            background: #dc3545;
            color: white;
        }
        
        .iris-status-badge.iris-status-pending {
            background: #124C58;
            color: white;
        }
    </style>';
}

/**
 * Page des jobs
 * 
 * @since 1.0.0
 * @return void
 */
function iris_jobs_admin_page() {
    global $wpdb;
    
    $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
    $jobs = $wpdb->get_results("
        SELECT j.*, u.display_name, u.user_email 
        FROM $table_jobs j 
        JOIN {$wpdb->users} u ON j.user_id = u.ID 
        ORDER BY j.created_at DESC 
        LIMIT 50
    ");
    
    ?>
    <div class="wrap">
        <h1>Jobs de traitement</h1>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Job ID</th>
                    <th>Utilisateur</th>
                    <th>Fichier</th>
                    <th>Statut</th>
                    <th>Créé</th>
                    <th>Terminé</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jobs as $job): ?>
                <tr>
                    <td><code><?php echo esc_html($job->job_id); ?></code></td>
                    <td><?php echo esc_html($job->display_name); ?></td>
                    <td><?php echo esc_html($job->original_file); ?></td>
                    <td>
                        <span class="iris-status-badge iris-status-<?php echo $job->status; ?>">
                            <?php echo iris_get_status_text($job->status); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($job->created_at); ?></td>
                    <td><?php echo $job->completed_at ? esc_html($job->completed_at) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Page de configuration
 * 
 * @since 1.0.0
 * @return void
 */
function iris_config_admin_page() {
    // Sauvegarde des paramètres
    if (isset($_POST['submit'])) {
        check_admin_referer('iris_config_save');
        
        update_option('iris_api_url', sanitize_url($_POST['api_url']));
        update_option('iris_max_file_size', intval($_POST['max_file_size']));
        update_option('iris_email_notifications', isset($_POST['email_notifications']));
        
        echo '<div class="notice notice-success"><p>Configuration sauvegardée !</p></div>';
    }
    
    $api_url = get_option('iris_api_url', IRIS_API_URL);
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
                        <input type="url" name="api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text" />
                        <p class="description">URL complète de votre API Python.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Taille max fichiers (MB)</th>
                    <td>
                        <input type="number" name="max_file_size" value="<?php echo esc_attr($max_file_size); ?>" min="1" max="500" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Notifications email</th>
                    <td>
                        <label>
                            <input type="checkbox" name="email_notifications" <?php checked($email_notifications); ?> />
                            Envoyer un email quand le traitement est terminé
                        </label>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Sauvegarder'); ?>
        </form>
    </div>
    <?php
}

/**
 * Widget WordPress pour afficher les jetons dans le dashboard
 * 
 * @since 1.0.0
 * @return void
 */
function iris_dashboard_widget() {
    if (!current_user_can('iris_process_images')) {
        return;
    }
    
    $user_id = get_current_user_id();
    $balance = Token_Manager::get_user_balance($user_id);
    
    echo '<div class="iris-dashboard-widget">';
    echo '<h3>Vos jetons Iris Process</h3>';
    echo '<p class="iris-token-count">' . $balance . ' jeton' . ($balance > 1 ? 's' : '') . ' disponible' . ($balance > 1 ? 's' : '') . '</p>';
    
    if ($balance > 0) {
        echo '<p><a href="' . home_url('/traitement-images/') . '" class="button button-primary">Traiter une image</a></p>';
    } else {
        echo '<p><a href="' . home_url('/boutique/') . '" class="button">Acheter des jetons</a></p>';
    }
    echo '</div>';
    
    echo '<style>
    .iris-dashboard-widget .iris-token-count {
        font-size: 1.5em;
        font-weight: bold;
        color: #3de9f4;
        text-align: center;
        margin: 15px 0;
    }
    </style>';
}

/**
 * Ajouter le widget au dashboard
 * 
 * @since 1.0.0
 * @return void
 */
function iris_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'iris_tokens_widget',
        'Iris Process - Jetons',
        'iris_dashboard_widget'
    );
}

/**
 * Shortcodes
 * 
 * @since 1.0.0
 */
add_shortcode('user_token_balance', 'iris_user_token_balance_shortcode');
add_shortcode('token_history', 'iris_token_history_shortcode');

/**
 * Shortcode pour afficher le solde de jetons
 * 
 * @since 1.0.0
 * @param array $atts Attributs du shortcode
 * @return string Solde de jetons
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
 * 
 * @since 1.0.0
 * @param array $atts Attributs du shortcode
 * @return string HTML de l'historique
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

/**
 * Fonctions utilitaires et helpers
 * 
 * @since 1.0.0
 */

/**
 * Nettoyage automatique des anciens jobs
 * 
 * @since 1.0.0
 * @return void
 */
function iris_cleanup_old_jobs() {
    global $wpdb;
    
    // Supprimer les jobs de plus de 30 jours
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}iris_processing_jobs 
         WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
        30
    ));
    
    // Nettoyer les fichiers temporaires
    $upload_dir = wp_upload_dir();
    $iris_dir = $upload_dir['basedir'] . '/iris-process/';
    
    if (is_dir($iris_dir)) {
        $files = glob($iris_dir . '*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > (7 * 24 * 3600)) { // 7 jours
                unlink($file);
            }
        }
    }
}

/**
 * Programmer le nettoyage quotidien
 * 
 * @since 1.0.0
 */
if (!wp_next_scheduled('iris_daily_cleanup')) {
    wp_schedule_event(time(), 'daily', 'iris_daily_cleanup');
}

/**
 * Fonction pour ajouter des jetons à un utilisateur (utilitaire admin)
 * 
 * @since 1.0.0
 * @param int $user_id ID de l'utilisateur
 * @param int $amount Nombre de jetons
 * @param string $description Description de l'attribution
 * @return bool Succès de l'opération
 */
function iris_admin_add_tokens_to_user($user_id, $amount, $description = 'Attribution manuelle') {
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    return Token_Manager::add_tokens($user_id, $amount, null);
}

/**
 * Hook pour ajouter des jetons lors d'un achat SureCart
 * 
 * @since 1.0.0
 * @param object $order Objet commande SureCart
 * @return void
 */
function iris_handle_surecart_order($order) {
    // Exemple d'intégration SureCart
    // À adapter selon votre configuration SureCart
    
    $user_id = $order->customer->user_id ?? null;
    $product_id = $order->line_items[0]->price->product ?? null;
    
    if (!$user_id || !$product_id) {
        return;
    }
    
    // Configuration des produits et jetons
    $token_products = array(
        'prod_token_10' => 10,   // 10 jetons
        'prod_token_50' => 50,   // 50 jetons
        'prod_token_100' => 100, // 100 jetons
    );
    
    if (isset($token_products[$product_id])) {
        $tokens_to_add = $token_products[$product_id];
        Token_Manager::add_tokens($user_id, $tokens_to_add, $order->id);
        
        // Log de l'attribution
        iris_log_error("$tokens_to_add jetons ajoutés à l'utilisateur $user_id via commande {$order->id}");
    }
}

/**
 * Ajouter des capacités personnalisées
 * 
 * @since 1.0.0
 * @return void
 */
function iris_add_custom_capabilities() {
    $role = get_role('administrator');
    if ($role) {
        $role->add_cap('iris_manage_tokens');
        $role->add_cap('iris_view_all_jobs');
    }
    
    $role = get_role('editor');
    if ($role) {
        $role->add_cap('iris_process_images');
    }
    
    $role = get_role('subscriber');
    if ($role) {
        $role->add_cap('iris_process_images');
    }
}

/**
 * Fonction utilitaire pour vérifier si un utilisateur peut traiter des images
 * 
 * @since 1.0.0
 * @param int|null $user_id ID de l'utilisateur (optionnel)
 * @return bool L'utilisateur peut-il traiter des images
 */
function iris_user_can_process_images($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false;
    }
    
    return user_can($user_id, 'iris_process_images') && Token_Manager::get_user_balance($user_id) > 0;
}

/**
 * Notifications par email pour les traitements terminés
 * 
 * @since 1.0.0
 * @param int $user_id ID de l'utilisateur
 * @param string $job_id ID du job
 * @param string $status Statut du traitement
 * @return void
 */
function iris_send_completion_email($user_id, $job_id, $status) {
    if (!get_option('iris_email_notifications', true)) {
        return;
    }
    
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return;
    }
    
    $subject = 'Iris Process - Traitement terminé';
    $message = "Bonjour {$user->display_name},\n\n";
    
    if ($status === 'completed') {
        $message .= "Votre traitement d'image (Job: {$job_id}) a été terminé avec succès !\n\n";
        $message .= "Vous pouvez télécharger vos fichiers depuis votre espace membre.\n\n";
    } else {
        $message .= "Votre traitement d'image (Job: {$job_id}) a rencontré une erreur.\n\n";
        $message .= "Veuillez réessayer ou contacter le support si le problème persiste.\n\n";
    }
    
    $message .= "Cordialement,\nL'équipe Iris Process";
    
    wp_mail($user->user_email, $subject, $message);
}

/**
 * Fonction pour déclencher l'action lors du callback
 * 
 * @since 1.0.0
 * @param string $job_id ID du job
 * @param string $status Statut du job
 * @param int $user_id ID de l'utilisateur
 * @return void
 */
function iris_trigger_job_completion_hooks($job_id, $status, $user_id) {
    do_action('iris_job_completed', $user_id, $job_id, $status);
}

/**
 * Fonction pour tester la connexion API (utilitaire)
 * 
 * @since 1.0.0
 * @return array Résultat du test
 */
function iris_test_api_connection() {
    $response = wp_remote_get(IRIS_API_URL . '/health', array(
        'timeout' => 10,
        'sslverify' => false
    ));
    
    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => $response->get_error_message()
        );
    }
    
    $code = wp_remote_retrieve_response_code($response);
    if ($code === 200) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return array(
            'success' => true,
            'message' => 'API accessible',
            'data' => $body
        );
    } else {
        return array(
            'success' => false,
            'message' => "Erreur HTTP: $code"
        );
    }
}

/**
 * Ajout d'un endpoint pour vérifier l'état de l'API
 * 
 * @since 1.0.0
 * @return void
 */
function iris_ajax_test_api() {
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

/**
 * Fonction pour nettoyer manuellement les anciens jobs (utilitaire admin)
 * 
 * @since 1.0.0
 * @return bool Succès de l'opération
 */
function iris_manual_cleanup() {
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    iris_cleanup_old_jobs();
    return true;
}

/**
 * Log des erreurs spécifique à Iris Process
 * 
 * @since 1.0.0
 * @param string $message Message à logger
 * @param array $context Contexte additionnel
 * @return void
 */
function iris_log_error($message, $context = array()) {
    $log_message = '[Iris Process] ' . $message;
    if (!empty($context)) {
        $log_message .= ' | Context: ' . json_encode($context);
    }
    error_log($log_message);
}

/**
 * Fonction pour débugger les uploads (mode développement)
 * 
 * @since 1.0.0
 * @param array $data Données à débugger
 * @return void
 */
function iris_debug_upload($data) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        iris_log_error('Debug Upload', $data);
    }
}

/**
 * Hook de désactivation du plugin
 * 
 * @since 1.0.0
 * @return void
 */
function iris_process_deactivate() {
    // Nettoyer les tâches cron
    wp_clear_scheduled_hook('iris_daily_cleanup');
    
    // Log de désactivation
    iris_log_error('Plugin Iris Process désactivé');
}

/**
 * Mise à jour de la base de données si nécessaire
 * 
 * @since 1.0.0
 * @return void
 */
function iris_maybe_update_database() {
    $current_version = get_option('iris_process_db_version', '1.0.0');
    $plugin_version = IRIS_PLUGIN_VERSION;
    
    if (version_compare($current_version, $plugin_version, '<')) {
        iris_create_tables();
        update_option('iris_process_db_version', $plugin_version);
        iris_log_error("Base de données mise à jour vers la version $plugin_version");
    }
}

/**
 * Fin du plugin
 * 
 * @since 1.0.0
 */
/**
 * Vérifier et ajuster les limites PHP pour les gros fichiers
 * 
 * @since 1.0.6
 * @return void
 */
function iris_check_php_limits() {
    $upload_max = wp_max_upload_size();
    $required_size = IRIS_MAX_FILE_SIZE;
    
    if ($upload_max < $required_size) {
        iris_log_error("Limite PHP insuffisante - Upload max: " . size_format($upload_max) . " / Requis: " . size_format($required_size));
        
        // Ajouter une notice admin
        add_action('admin_notices', function() use ($upload_max, $required_size) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>Iris Process:</strong> La limite d\'upload PHP (' . size_format($upload_max) . ') est inférieure à la limite requise (' . size_format($required_size) . '). ';
            echo 'Contactez votre hébergeur pour augmenter upload_max_filesize et post_max_size.';
            echo '</p></div>';
        });
    }
}
add_action('admin_init', 'iris_check_php_limits');

/**
 * Obtenir la liste des formats supportés (pour affichage)
 * 
 * @since 1.0.6
 * @return string Liste formatée des extensions
 */
function iris_get_supported_formats_display() {
    $raw_formats = array('CR2', 'CR3', 'CRW', 'NEF', 'NRW', 'ARW', 'SRF', 'SR2', 'RAW', 'RW2', 'RWL', 'PTX', 'PEF', 'ORF', 'RAF', 'SRW', 'DNG');
    $standard_formats = array('JPG', 'JPEG', 'TIF', 'TIFF', 'PNG', 'BMP', 'WEBP');
    
    return 'RAW: ' . implode(', ', $raw_formats) . ' | Standards: ' . implode(', ', $standard_formats);
}
?>