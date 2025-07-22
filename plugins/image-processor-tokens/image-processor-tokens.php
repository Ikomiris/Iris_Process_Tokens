<?php
/**
 * Plugin Name: Iris Process - Image Processor with Tokens
 * Plugin URI: https://iris4pro.com
 * Description: Application WordPress de traitement d'images avec système de jetons et presets JSON. Permet le traitement d'images RAW avec gestion complète des tokens utilisateur et presets Iris Rawpy.
 * Version: 1.1.1
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
 * @version 1.1.1
 * @author Ikomiris
 * @copyright 2025 iris4pro.com
 * 
 * 🔄 CORRECTIF v1.1.1 : Ordre de chargement sécurisé
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
define('IRIS_PLUGIN_VERSION', '1.1.1');
define('IRIS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IRIS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('IRIS_API_URL', 'https://btrjln6o7e.execute-api.eu-west-1.amazonaws.com/iris4pro');

// =============================================================================
// 2. FONCTIONS UTILITAIRES GLOBALES DE BASE
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
 * Diagnostic de l'état du plugin
 * 
 * @since 1.1.1
 * @return array État des composants
 */
function iris_diagnostic_check() {
    $status = array(
        'files_missing' => array(),
        'classes_missing' => array(),
        'functions_missing' => array(),
        'critical_error' => false
    );
    
    // Vérifier les fichiers critiques
    $critical_files = array(
        'includes/class-token-manager.php',
        'includes/functions-database.php',
        'includes/functions-upload.php'
    );
    
    foreach ($critical_files as $file) {
        if (!file_exists(IRIS_PLUGIN_PATH . $file)) {
            $status['files_missing'][] = $file;
            $status['critical_error'] = true;
        }
    }
    
    return $status;
}

// =============================================================================
// 3. CLASSE PRINCIPALE SÉCURISÉE
// =============================================================================

/**
 * Classe principale du plugin Iris Process - VERSION SÉCURISÉE
 * 
 * @since 1.0.0
 * @since 1.1.1 Ordre de chargement sécurisé
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
     * État du plugin après chargement
     * 
     * @since 1.1.1
     * @var array
     */
    private $plugin_status = array();
    
    /**
     * Composants chargés avec succès
     * 
     * @since 1.1.1
     * @var array
     */
    private $loaded_components = array();
    
    /**
     * Constructeur privé pour le pattern Singleton
     * 
     * @since 1.0.0
     */
    private function __construct() {
        // Diagnostic immédiat
        $this->plugin_status = iris_diagnostic_check();
        
        if ($this->plugin_status['critical_error']) {
            add_action('admin_notices', array($this, 'show_critical_error_notice'));
            iris_log_error('IRIS CRITICAL: Fichiers manquants détectés', $this->plugin_status);
            return; // Arrêter le chargement
        }
        
        // Chargement sécurisé
        $this->init_safe_hooks();
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
     * Initialise SEULEMENT les hooks de base sécurisés
     * 
     * @since 1.1.1
     * @return void
     */
    private function init_safe_hooks() {
        // Chargement différé - CRITIQUE
        add_action('plugins_loaded', array($this, 'load_plugin_components'), 10);
        add_action('init', array($this, 'plugin_init'), 20);
        
        // Log d'initialisation de base
        iris_log_error('Plugin Iris Process: Hooks de base initialisés - Version ' . IRIS_PLUGIN_VERSION);
    }
    
    /**
     * Charge les composants du plugin de manière sécurisée
     * 
     * @since 1.1.1
     * @return void
     */
    public function load_plugin_components() {
        try {
            // 1. Charger les dépendances de base
            $this->load_core_dependencies();
            
            // 2. Vérifier que les classes critiques existent
            if (!$this->verify_critical_classes()) {
                throw new Exception('Classes critiques manquantes');
            }
            
            // 3. Initialiser les composants de base
            $this->init_core_components();
            
            // 4. Charger les composants non-critiques
            $this->load_optional_components();
            
            // 5. Initialiser les hooks si tout est OK
            $this->init_application_hooks();
            
            iris_log_error('Plugin Iris Process: Composants chargés avec succès');
            
        } catch (Exception $e) {
            iris_log_error('IRIS ERROR: Échec du chargement - ' . $e->getMessage());
            add_action('admin_notices', array($this, 'show_loading_error_notice'));
        }
    }
    
    /**
     * Charge les dépendances de base obligatoires
     * 
     * @since 1.1.1
     * @return void
     * @throws Exception Si fichier critique manquant
     */
    private function load_core_dependencies() {
        $core_files = array(
            'includes/functions-database.php',
            'includes/class-token-manager.php',
            'includes/functions-upload.php'
        );
        
        foreach ($core_files as $file) {
            $file_path = IRIS_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                $this->loaded_components[] = $file;
            } else {
                throw new Exception("Fichier critique manquant: $file");
            }
        }
    }
    
    /**
     * Vérifie que les classes critiques sont disponibles
     * 
     * @since 1.1.1
     * @return bool
     */
    private function verify_critical_classes() {
        $critical_classes = array('Token_Manager');
        
        foreach ($critical_classes as $class) {
            if (!class_exists($class)) {
                iris_log_error("IRIS ERROR: Classe critique manquante - $class");
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Initialise les composants de base
     * 
     * @since 1.1.1
     * @return void
     */
    private function init_core_components() {
        // Créer les tables si nécessaire
        if (function_exists('iris_create_tables')) {
            iris_create_tables();
        }
        
        // Vérifier la version de la DB
        if (function_exists('iris_maybe_update_database')) {
            iris_maybe_update_database();
        }
    }
    
    /**
     * Charge les composants optionnels (non-critiques)
     * 
     * @since 1.1.1
     * @return void
     */
    private function load_optional_components() {
        $optional_files = array(
            'includes/class-language-manager.php',
            'includes/functions-i18n.php',
            'includes/class-preset-manager.php',
            'includes/class-user-dashboard.php',
            'includes/class-image-processor.php',
            'includes/class-ajax-handlers.php',
            'includes/class-rest-api.php',
            'includes/functions-api.php',
            'includes/functions-admin.php',
            'shortcodes/class-shortcodes.php'
        );
        
        foreach ($optional_files as $file) {
            $file_path = IRIS_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                $this->loaded_components[] = $file;
            } else {
                iris_log_error("IRIS WARNING: Fichier optionnel manquant - $file");
            }
        }
    }
    
    /**
     * Initialise les hooks de l'application APRÈS chargement
     * 
     * @since 1.1.1
     * @return void
     */
    private function init_application_hooks() {
        // Scripts et styles
        add_action('wp_enqueue_scripts', 'iris_enqueue_upload_scripts');
        add_action('admin_enqueue_scripts', 'iris_admin_enqueue_scripts');
        
        // AJAX handlers - SEULEMENT si les fonctions existent
        if (function_exists('iris_handle_image_upload')) {
            add_action('wp_ajax_iris_upload_image', 'iris_handle_image_upload');
            add_action('wp_ajax_nopriv_iris_upload_image', 'iris_handle_image_upload');
        }
        
        if (function_exists('iris_check_process_status')) {
            add_action('wp_ajax_iris_check_process_status', 'iris_check_process_status');
        }
        
        if (function_exists('iris_ajax_test_api')) {
            add_action('wp_ajax_iris_test_api', 'iris_ajax_test_api');
        }
        
        // REST API
        if (function_exists('iris_register_rest_routes')) {
            add_action('rest_api_init', 'iris_register_rest_routes');
        }
        
        // Administration
        if (function_exists('iris_add_admin_menu')) {
            // Suppression du hook add_action('admin_menu', 'iris_add_admin_menu');
        }
        
        // Initialiser les classes si elles existent
        if (class_exists('Iris_Process_Ajax_Handlers')) {
            new Iris_Process_Ajax_Handlers();
        }
        
        if (class_exists('Iris_Process_Rest_Api')) {
            new Iris_Process_Rest_Api();
        }
        
        if (class_exists('Iris_Process_Shortcodes')) {
            new Iris_Process_Shortcodes();
        }
        
        if (class_exists('User_Dashboard')) {
            new User_Dashboard();
        }
        
        // Initialiser le gestionnaire de langues AVANT les autres composants
        if (class_exists('Iris_Language_Manager')) {
            Iris_Language_Manager::get_instance();
        }
        
        // Forcer le rechargement des traductions avec le bon domaine
        if (function_exists('load_plugin_textdomain')) {
            $lang_manager = iris_get_language_manager();
            if ($lang_manager && $lang_manager->is_english()) {
                // Forcer la locale anglaise pour ce plugin
                add_filter('locale', function($locale) {
                    return 'en_US';
                }, 999);
                
                // Recharger les traductions
                load_plugin_textdomain(
                    'iris-process-tokens',
                    false,
                    dirname(plugin_basename(__FILE__)) . '/languages'
                );
            }
        }
        
        iris_log_error('Plugin Iris Process: Hooks d\'application initialisés');
    }
    
    /**
     * Initialisation du plugin après le chargement de WordPress
     * 
     * @since 1.0.0
     */
    public function plugin_init() {
        // Chargement des traductions
        load_plugin_textdomain(
            'iris-process-tokens',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
        
        iris_log_error('Plugin Iris Process: Init hook exécuté');
    }
    
    /**
     * Actions lors de l'activation du plugin
     * 
     * @since 1.0.0
     */
    public function activate() {
        // Diagnostic avant activation
        $status = iris_diagnostic_check();
        if ($status['critical_error']) {
            iris_log_error('ACTIVATION BLOQUÉE: Fichiers manquants', $status);
            wp_die('Iris Process: Impossible d\'activer le plugin - fichiers manquants. Vérifiez l\'installation.');
        }
        
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
        
        iris_log_error('Plugin Iris Process activé avec succès - Version ' . IRIS_PLUGIN_VERSION);
    }
    
    /**
     * Actions lors de la désactivation du plugin
     * 
     * @since 1.1.1
     */
    public function deactivate() {
        // Nettoyer les tâches cron
        wp_clear_scheduled_hook('iris_daily_cleanup');
        iris_log_error('Plugin Iris Process désactivé');
    }
    
    /**
     * Affiche un avis d'erreur critique
     * 
     * @since 1.1.1
     * @return void
     */
    public function show_critical_error_notice() {
        $missing_files = implode(', ', $this->plugin_status['files_missing']);
        ?>
        <div class="notice notice-error">
            <p><strong>Iris Process:</strong> Plugin désactivé - Fichiers critiques manquants:</p>
            <ul>
                <?php foreach ($this->plugin_status['files_missing'] as $file): ?>
                    <li><code><?php echo esc_html($file); ?></code></li>
                <?php endforeach; ?>
            </ul>
            <p>Veuillez réinstaller le plugin ou contacter le support.</p>
        </div>
        <?php
    }
    
    /**
     * Affiche un avis d'erreur de chargement
     * 
     * @since 1.1.1
     * @return void
     */
    public function show_loading_error_notice() {
        ?>
        <div class="notice notice-warning">
            <p><strong>Iris Process:</strong> Le plugin fonctionne en mode dégradé. Certaines fonctionnalités peuvent être indisponibles.</p>
            <p>Consultez les logs pour plus d'informations.</p>
        </div>
        <?php
    }

    /**
     * Méthodes statiques pour l'activation/désactivation (pour les hooks globaux)
     */
    public static function activate_static() {
        $instance = self::get_instance();
        if (method_exists($instance, 'activate')) {
            $instance->activate();
        }
    }
    public static function deactivate_static() {
        $instance = self::get_instance();
        if (method_exists($instance, 'deactivate')) {
            $instance->deactivate();
        }
    }
    /**
     * Ajoute les méthodes statiques si elles n'existent pas (pour compatibilité)
     */
    public static function add_static_activation_methods() {}
}

// =============================================================================
// 4. FONCTIONS DE FALLBACK SÉCURISÉES
// =============================================================================

/**
 * Fallback pour iris_enqueue_upload_scripts
 */
if (!function_exists('iris_enqueue_upload_scripts')) {
    function iris_enqueue_upload_scripts() {
        wp_enqueue_script('jquery');
        
        // Charger les styles seulement si le fichier existe
        $css_file = IRIS_PLUGIN_PATH . 'assets/iris-upload.css';
        if (file_exists($css_file)) {
            wp_enqueue_style('iris-upload', IRIS_PLUGIN_URL . 'assets/iris-upload.css', array(), IRIS_PLUGIN_VERSION);
        }
        
        // Charger le JS seulement si le fichier existe
        $js_file = IRIS_PLUGIN_PATH . 'assets/iris-upload.js';
        if (file_exists($js_file)) {
            wp_enqueue_script('iris-upload', IRIS_PLUGIN_URL . 'assets/iris-upload.js', array('jquery'), IRIS_PLUGIN_VERSION, true);
        }
        
        wp_localize_script('jquery', 'iris_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('iris_upload_nonce'),
            'max_file_size' => wp_max_upload_size(),
            'allowed_types' => array('image/jpeg', 'image/tiff', 'image/x-canon-cr3', 'image/x-nikon-nef', 'image/x-sony-arw')
        ));
    }
}

/**
 * Fallback pour iris_admin_enqueue_scripts
 */
if (!function_exists('iris_admin_enqueue_scripts')) {
    function iris_admin_enqueue_scripts($hook) {
        if (strpos($hook, 'iris') === false) {
            return;
        }
        
        $css_file = IRIS_PLUGIN_PATH . 'assets/iris-admin.css';
        if (file_exists($css_file)) {
            wp_enqueue_style('iris-admin', IRIS_PLUGIN_URL . 'assets/iris-admin.css', array(), IRIS_PLUGIN_VERSION);
        }
        
        $js_file = IRIS_PLUGIN_PATH . 'assets/iris-admin.js';
        if (file_exists($js_file)) {
            wp_enqueue_script('iris-admin', IRIS_PLUGIN_URL . 'assets/iris-admin.js', array('jquery'), IRIS_PLUGIN_VERSION, true);
        }
    }
}

/**
 * Shortcode principal de fallback
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
                <p>Zone d'upload Iris Process (mode sécurisé)</p>
                <p>Plugin en cours de chargement... Vérifiez que tous les fichiers sont présents.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    add_shortcode('iris_upload_zone', 'iris_upload_zone_shortcode');
}

// =============================================================================
// 5. INITIALISATION SÉCURISÉE DU PLUGIN
// =============================================================================

// Test de compatibilité PHP avant tout
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Iris Process:</strong> Nécessite PHP 7.4 ou supérieur. Version actuelle: ' . PHP_VERSION . '</p></div>';
    });
    return;
}

// Initialiser le plugin seulement si WordPress est complètement chargé
if (defined('ABSPATH') && !class_exists('IrisProcessTokens_Already_Loaded')) {
    
    // Marquer comme chargé pour éviter les doubles chargements
    class IrisProcessTokens_Already_Loaded {}
    
    // Initialiser le plugin
    $iris_process_instance = IrisProcessTokens::get_instance();
    
    iris_log_error('Plugin Iris Process v' . IRIS_PLUGIN_VERSION . ' initialisé avec succès');
    
    // Déplacement des hooks d'activation/désactivation ici (contexte global)
    register_activation_hook(__FILE__, array('IrisProcessTokens', 'activate_static'));
    register_deactivation_hook(__FILE__, array('IrisProcessTokens', 'deactivate_static'));
    
    // Assure le chargement de la classe admin/class-preset-manager.php
    if (file_exists(IRIS_PLUGIN_PATH . 'admin/class-preset-manager.php')) {
        require_once IRIS_PLUGIN_PATH . 'admin/class-preset-manager.php';
    }
    // Alias pour compatibilité entre anciens et nouveaux systèmes de presets
    if (!class_exists('Preset_Manager') && class_exists('Iris_Preset_Manager')) {
        class_alias('Iris_Preset_Manager', 'Preset_Manager');
    }
    
    // Ajout explicite du chargement du menu admin moderne
    require_once IRIS_PLUGIN_PATH . 'includes/class-iris-process-main.php';
    add_action('plugins_loaded', function() {
        Iris_Process_Main::get_instance();
    });
    
} else {
    iris_log_error('IRIS WARNING: Plugin déjà chargé ou WordPress non initialisé');
}

// Ajout des méthodes statiques pour l'activation/désactivation
if (!method_exists('IrisProcessTokens', 'activate_static')) {
    class_alias('IrisProcessTokens', 'IrisProcessTokens_Activation_Helper');
    IrisProcessTokens_Activation_Helper::add_static_activation_methods();
}

/**
 * FIN DU FICHIER PRINCIPAL SÉCURISÉ
 * 
 * Ce fichier corrigé :
 * - Vérifie l'existence des fichiers avant chargement
 * - Teste les classes avant instanciation  
 * - Initialise les hooks APRÈS le chargement des fonctions
 * - Fournit des fallbacks fonctionnels
 * - Gère les erreurs de manière gracieuse
 * - Évite les plantages fataux
 */
?>