<?php
/**
 * Plugin Name: Iris Process - Image Processor with Tokens
 * Plugin URI: https://iris4pro.com
 * Description: Application WordPress de traitement d'images avec système de jetons et presets JSON. Permet le traitement d'images RAW via API Python avec gestion complète des tokens utilisateur et presets Iris Rawpy.
 * Version: 1.1.0
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
 * @version 1.1.0
 * @author Ikomiris
 * @copyright 2025 iris4pro.com
 * 
 * Développé avec l'assistance de Claude.ai (Anthropic)
 * Environnement de développement : VS Code + SFTP + GitHub
 * Serveur de production : Hostinger.com
 * 
 * v1.1.0 : Ajout du système de presets JSON Iris Rawpy (remplacement XMP)
 */

// =============================================================================
// 1. SÉCURITÉ ET CONSTANTES
// =============================================================================

// Sécurité - Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit('Accès direct interdit.');
}

/**
 * Définition des constantes du plugin
 * 
 * @since 1.0.0
 */
define('IRIS_PLUGIN_VERSION', '1.1.0');
define('IRIS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IRIS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('IRIS_API_URL', 'http://54.155.119.226:8000');

// =============================================================================
// 2. FONCTIONS UTILITAIRES GLOBALES
// =============================================================================

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
 * Fonction helper pour récupérer l'instance du plugin
 * 
 * @since 1.0.0
 * @return IrisProcessTokens
 */
function iris_process_tokens() {
    return IrisProcessTokens::get_instance();
}

// =============================================================================
// 3. CLASSE PRINCIPALE
// =============================================================================

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
        // Vérifier et charger les fichiers de classes s'ils existent
        $includes_files = array(
            'includes/class-token-manager.php',
            'includes/class-preset-manager.php',
            'includes/class-surecart-integration.php',
            'includes/class-user-dashboard.php',
            'includes/functions-database.php',
            'includes/functions-api.php',
            'includes/functions-upload.php',
            'includes/functions-admin.php',
            'includes/functions-utilities.php'
        );
        
        foreach ($includes_files as $file) {
            $file_path = IRIS_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                iris_log_error("Fichier manquant: $file");
            }
        }
        
        // Initialiser les classes après chargement
        $this->init_classes();
    }
    
    /**
     * Initialise les classes après chargement des dépendances
     * 
     * @since 1.1.0
     * @return void
     */
    private function init_classes() {
        // Initialiser User_Dashboard
        if (class_exists('User_Dashboard')) {
            new User_Dashboard();
        }
        
        // Initialiser SureCart_Integration si disponible
        if (class_exists('SureCart_Integration')) {
            SureCart_Integration::init();
        }
        
        // Initialiser Preset_Manager si disponible
        if (class_exists('Preset_Manager')) {
            new Preset_Manager();
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
        if (function_exists('iris_create_tables')) {
            iris_create_tables();
        }
        
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

// =============================================================================
// 4. FONCTIONS DE FALLBACK (si les fichiers includes n'existent pas)
// =============================================================================

/**
 * Fallback pour iris_maybe_update_database
 */
if (!function_exists('iris_maybe_update_database')) {
    function iris_maybe_update_database() {
        $current_version = get_option('iris_process_db_version', '1.0.0');
        $plugin_version = IRIS_PLUGIN_VERSION;
        
        if (version_compare($current_version, $plugin_version, '<')) {
            if (function_exists('iris_create_tables')) {
                iris_create_tables();
            }
            update_option('iris_process_db_version', $plugin_version);
            iris_log_error("Base de données mise à jour vers la version $plugin_version");
        }
    }
}

/**
 * Fallback pour iris_process_deactivate
 */
if (!function_exists('iris_process_deactivate')) {
    function iris_process_deactivate() {
        // Nettoyer les tâches cron
        wp_clear_scheduled_hook('iris_daily_cleanup');
        
        // Log de désactivation
        iris_log_error('Plugin Iris Process désactivé');
    }
}

/**
 * Fallback pour les fonctions critiques si les includes n'existent pas
 */
if (!function_exists('iris_enqueue_upload_scripts')) {
    function iris_enqueue_upload_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_style('iris-upload', IRIS_PLUGIN_URL . 'assets/iris-upload.css', array(), IRIS_PLUGIN_VERSION);
        wp_localize_script('jquery', 'iris_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('iris_upload_nonce'),
            'max_file_size' => wp_max_upload_size(),
            'allowed_types' => array('image/jpeg', 'image/tiff', 'image/x-canon-cr3', 'image/x-nikon-nef', 'image/x-sony-arw')
        ));
    }
}

if (!function_exists('iris_admin_enqueue_scripts')) {
    function iris_admin_enqueue_scripts($hook) {
        if (strpos($hook, 'iris') === false) {
            return;
        }
        wp_enqueue_style('iris-admin', IRIS_PLUGIN_URL . 'assets/iris-admin.css', array(), IRIS_PLUGIN_VERSION);
        wp_enqueue_script('iris-admin', IRIS_PLUGIN_URL . 'assets/iris-admin.js', array('jquery'), IRIS_PLUGIN_VERSION, true);
    }
}

// Fallback pour Token_Manager si la classe n'existe pas
if (!class_exists('Token_Manager')) {
    class Token_Manager {
        public static function get_user_balance($user_id) {
            return 0;
        }
        
        public static function add_tokens($user_id, $amount, $order_id = null) {
            return false;
        }
        
        public static function use_token($user_id, $image_process_id) {
            return false;
        }
        
        public static function get_user_transactions($user_id, $limit = 10) {
            return array();
        }
    }
}

// =============================================================================
// 5. INITIALISATION DU PLUGIN
// =============================================================================

// Initialiser le plugin
IrisProcessTokens::get_instance();

/**
 * Shortcode principal si la fonction n'existe pas dans les includes
 */
if (!function_exists('iris_upload_zone_shortcode')) {
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
            
            <div class="iris-upload-zone">
                <p>Zone d'upload Iris Process (version de base)</p>
                <p>Veuillez vérifier que tous les fichiers du plugin sont présents.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    add_shortcode('iris_upload_zone', 'iris_upload_zone_shortcode');
}

/**
 * FIN DU FICHIER PRINCIPAL
 * 
 * Ce fichier se contente maintenant de :
 * - Définir les constantes
 * - Initialiser la classe principale
 * - Charger les dépendances depuis les includes/
 * - Fournir des fallbacks si les fichiers sont manquants
 */
?>