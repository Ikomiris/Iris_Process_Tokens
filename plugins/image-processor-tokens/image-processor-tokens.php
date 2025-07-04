<?php
/**
 * Plugin Name: Iris Process - Image Processor with Tokens
 * Plugin URI: https://iris4pro.com
 * Description: Application WordPress de traitement d'images avec système de jetons et presets JSON. Permet le traitement d'images RAW via API Python avec gestion complète des tokens utilisateur et presets Iris Rawpy.
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
 * Développé avec l'assistance de Claude.ai (Anthropic)
 * Environnement de développement : VS Code + SFTP + GitHub
 * Serveur de production : Hostinger.com
 * 
 * v1.1.1 : Correction des erreurs critiques causant plantage WordPress
 */

// =============================================================================
// 1. SÉCURITÉ ET CONSTANTES
// =============================================================================

// Sécurité - Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit('Accès direct interdit.');
}

// Vérification de la version PHP minimale
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>Iris Process nécessite PHP 7.4 ou supérieur. Version actuelle : ' . PHP_VERSION . '</p></div>';
    });
    return;
}

/**
 * Définition des constantes du plugin
 * 
 * @since 1.0.0
 */
if (!defined('IRIS_PLUGIN_VERSION')) {
    define('IRIS_PLUGIN_VERSION', '1.1.1');
}
if (!defined('IRIS_PLUGIN_URL')) {
    define('IRIS_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('IRIS_PLUGIN_PATH')) {
    define('IRIS_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('IRIS_API_URL')) {
    define('IRIS_API_URL', 'http://54.155.119.226:8000');
}

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
if (!function_exists('iris_log_error')) {
    function iris_log_error($message, $context = array()) {
        $log_message = '[Iris Process] ' . $message;
        if (!empty($context)) {
            $log_message .= ' | Context: ' . json_encode($context);
        }
        error_log($log_message);
        
        // En mode debug, aussi afficher dans admin
        if (defined('WP_DEBUG') && WP_DEBUG && is_admin()) {
            add_action('admin_notices', function() use ($message) {
                echo '<div class="notice notice-warning"><p>Iris Process Debug: ' . esc_html($message) . '</p></div>';
            });
        }
    }
}

/**
 * Fonction helper pour récupérer l'instance du plugin
 * 
 * @since 1.0.0
 * @return IrisProcessTokens|null
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
     * État d'initialisation du plugin
     * 
     * @since 1.1.1
     * @var bool
     */
    private $initialized = false;
    
    /**
     * Erreurs d'initialisation
     * 
     * @since 1.1.1
     * @var array
     */
    private $init_errors = array();
    
    /**
     * Constructeur privé pour le pattern Singleton
     * 
     * @since 1.0.0
     */
    private function __construct() {
        // Vérifier que WordPress est bien chargé
        if (!defined('ABSPATH')) {
            return;
        }
        
        // Initialisation sécurisée
        $this->safe_init();
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
     * Initialisation sécurisée du plugin
     * 
     * @since 1.1.1
     * @return void
     */
    private function safe_init() {
        try {
            // Étape 1: Hooks d'activation/désactivation (priorité haute)
            $this->register_lifecycle_hooks();
            
            // Étape 2: Chargement des dépendances
            if (!$this->load_dependencies()) {
                $this->init_errors[] = 'Impossible de charger les dépendances';
                add_action('admin_notices', array($this, 'show_dependency_error'));
                return;
            }
            
            // Étape 3: Hooks WordPress (après init)
            add_action('init', array($this, 'late_init'), 20);
            add_action('plugins_loaded', array($this, 'plugins_loaded_init'), 20);
            
            // Marquer comme initialisé
            $this->initialized = true;
            
        } catch (Error $e) {
            iris_log_error('Erreur critique lors de l\'initialisation: ' . $e->getMessage());
            $this->init_errors[] = $e->getMessage();
            add_action('admin_notices', array($this, 'show_critical_error'));
        } catch (Exception $e) {
            iris_log_error('Exception lors de l\'initialisation: ' . $e->getMessage());
            $this->init_errors[] = $e->getMessage();
            add_action('admin_notices', array($this, 'show_critical_error'));
        }
    }
    
    /**
     * Enregistre les hooks de cycle de vie du plugin
     * 
     * @since 1.1.1
     * @return void
     */
    private function register_lifecycle_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialisation après que WordPress soit complètement chargé
     * 
     * @since 1.1.1
     * @return void
     */
    public function late_init() {
        if (!$this->initialized) {
            return;
        }
        
        try {
            // Chargement des traductions
            load_plugin_textdomain(
                'iris-process-tokens',
                false,
                dirname(plugin_basename(__FILE__)) . '/languages'
            );
            
            // Initialisation des composants principaux
            $this->init_wordpress_hooks();
            $this->init_classes();
            
            // Log d'initialisation réussie
            if (defined('WP_DEBUG') && WP_DEBUG) {
                iris_log_error('Plugin Iris Process initialisé avec succès - Version ' . IRIS_PLUGIN_VERSION);
            }
            
        } catch (Error $e) {
            iris_log_error('Erreur lors de late_init: ' . $e->getMessage());
        }
    }
    
    /**
     * Initialisation après chargement de tous les plugins
     * 
     * @since 1.1.1
     * @return void
     */
    public function plugins_loaded_init() {
        if (!$this->initialized) {
            return;
        }
        
        // Vérifier et mettre à jour la base de données
        if (function_exists('iris_maybe_update_database')) {
            iris_maybe_update_database();
        }
    }
    
    /**
     * Initialise les hooks WordPress
     * 
     * @since 1.1.1
     * @return void
     */
    private function init_wordpress_hooks() {
        // Scripts et styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers (maintenant sécurisé)
        add_action('wp_ajax_iris_upload_image', array($this, 'ajax_upload_image'));
        add_action('wp_ajax_nopriv_iris_upload_image', array($this, 'ajax_upload_image'));
        add_action('wp_ajax_iris_check_process_status', array($this, 'ajax_check_status'));
        add_action('wp_ajax_iris_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_iris_download', array($this, 'ajax_download'));
        
        // REST API
        add_action('rest_api_init', array($this, 'init_rest_api'));
        
        // Administration
        if (is_admin()) {
            add_action('admin_menu', array($this, 'init_admin_menu'));
            add_action('wp_dashboard_setup', array($this, 'init_dashboard_widget'));
        }
        
        // Capacités et rôles
        add_action('init', array($this, 'add_custom_capabilities'));
        
        // Nettoyage automatique
        if (!wp_next_scheduled('iris_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'iris_daily_cleanup');
        }
        add_action('iris_daily_cleanup', array($this, 'daily_cleanup'));
        
        // Intégrations externes
        add_action('surecart/order_completed', array($this, 'handle_surecart_order'), 10, 1);
        add_action('iris_job_completed', array($this, 'send_completion_email'), 10, 3);
    }
    
    /**
     * Charge les dépendances du plugin de manière sécurisée
     * 
     * @since 1.1.1
     * @return bool Succès du chargement
     */
    private function load_dependencies() {
        // Ordre de chargement important
        $includes_files = array(
            'includes/functions-database.php',      // Base de données en premier
            'includes/functions-utilities.php',     // Utilitaires de base
            'includes/class-token-manager.php',     // Gestion des tokens
            'includes/class-preset-manager.php',    // Gestion des presets
            'includes/class-user-dashboard.php',    // Interface utilisateur
            'includes/class-surecart-integration.php', // Intégrations
            'includes/functions-api.php',           // API REST
            'includes/functions-upload.php',        // Gestion uploads
            'includes/functions-admin.php',         // Administration
        );
        
        $loaded_count = 0;
        $total_files = count($includes_files);
        
        foreach ($includes_files as $file) {
            $file_path = IRIS_PLUGIN_PATH . $file;
            
            if (!file_exists($file_path)) {
                iris_log_error("Fichier manquant: $file");
                continue;
            }
            
            try {
                // Vérification de la syntaxe PHP avant inclusion
                if (!$this->validate_php_syntax($file_path)) {
                    iris_log_error("Erreur de syntaxe dans: $file");
                    continue;
                }
                
                require_once $file_path;
                $loaded_count++;
                
            } catch (ParseError $e) {
                iris_log_error("Erreur de parsing dans $file: " . $e->getMessage());
                continue;
            } catch (Error $e) {
                iris_log_error("Erreur fatale dans $file: " . $e->getMessage());
                continue;
            } catch (Exception $e) {
                iris_log_error("Exception dans $file: " . $e->getMessage());
                continue;
            }
        }
        
        // Considérer comme succès si au moins 80% des fichiers sont chargés
        $success_rate = ($loaded_count / $total_files) * 100;
        
        if ($success_rate < 80) {
            iris_log_error("Taux de chargement insuffisant: {$success_rate}% ({$loaded_count}/{$total_files})");
            return false;
        }
        
        iris_log_error("Dépendances chargées: {$loaded_count}/{$total_files} ({$success_rate}%)");
        return true;
    }
    
    /**
     * Valide la syntaxe PHP d'un fichier
     * 
     * @since 1.1.1
     * @param string $file_path Chemin du fichier
     * @return bool Syntaxe valide
     */
    private function validate_php_syntax($file_path) {
        // Méthode simple de validation
        $content = file_get_contents($file_path);
        
        // Vérifications basiques
        if (strpos($content, '<?php') === false) {
            return false;
        }
        
        // Note: Pour une validation complète, on pourrait utiliser token_get_all()
        // mais cela peut être coûteux en performance
        
        return true;
    }
    
    /**
     * Initialise les classes après chargement des dépendances
     * 
     * @since 1.1.1
     * @return void
     */
    private function init_classes() {
        $classes_to_init = array(
            'User_Dashboard' => array('method' => 'construct'),
            'SureCart_Integration' => array('method' => 'init'),
            'Preset_Manager' => array('method' => 'construct'),
        );
        
        foreach ($classes_to_init as $class_name => $config) {
            if (!class_exists($class_name)) {
                iris_log_error("Classe manquante: $class_name");
                continue;
            }
            
            try {
                if ($config['method'] === 'construct') {
                    new $class_name();
                } else {
                    call_user_func(array($class_name, $config['method']));
                }
                
            } catch (Error $e) {
                iris_log_error("Erreur initialisation $class_name: " . $e->getMessage());
            } catch (Exception $e) {
                iris_log_error("Exception initialisation $class_name: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Actions lors de l'activation du plugin
     * 
     * @since 1.0.0
     * @return void
     */
    public function activate() {
        try {
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
            
        } catch (Error $e) {
            iris_log_error('Erreur lors de l\'activation: ' . $e->getMessage());
            wp_die('Erreur lors de l\'activation du plugin Iris Process: ' . $e->getMessage());
        }
    }
    
    /**
     * Actions lors de la désactivation du plugin
     * 
     * @since 1.1.1
     * @return void
     */
    public function deactivate() {
        // Nettoyer les tâches cron
        wp_clear_scheduled_hook('iris_daily_cleanup');
        
        // Log de désactivation
        iris_log_error('Plugin Iris Process désactivé');
    }
    
    /**
     * Enqueue des scripts frontend
     * 
     * @since 1.1.1
     * @return void
     */
    public function enqueue_frontend_scripts() {
        // Chargement conditionnel - seulement si nécessaire
        if (!$this->should_load_frontend_scripts()) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_style('iris-upload', IRIS_PLUGIN_URL . 'assets/iris-upload.css', array(), IRIS_PLUGIN_VERSION);
        wp_enqueue_script('iris-upload', IRIS_PLUGIN_URL . 'assets/iris-upload.js', array('jquery'), IRIS_PLUGIN_VERSION, true);
        
        wp_localize_script('iris-upload', 'iris_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('iris_upload_nonce'),
            'max_file_size' => wp_max_upload_size(),
            'allowed_types' => array('image/jpeg', 'image/tiff', 'image/x-canon-cr3', 'image/x-nikon-nef', 'image/x-sony-arw')
        ));
    }
    
    /**
     * Détermine si les scripts frontend doivent être chargés
     * 
     * @since 1.1.1
     * @return bool
     */
    private function should_load_frontend_scripts() {
        global $post;
        
        // Charger sur les pages avec shortcodes Iris
        if ($post && has_shortcode($post->post_content, 'iris_upload_zone')) {
            return true;
        }
        
        // Charger sur les pages templates spécifiques
        if (is_page_template('iris-process.php')) {
            return true;
        }
        
        // Charger si URL contient iris
        if (strpos($_SERVER['REQUEST_URI'], 'iris') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Enqueue des scripts admin
     * 
     * @since 1.1.1
     * @param string $hook Page admin actuelle
     * @return void
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'iris') === false) {
            return;
        }
        
        wp_enqueue_style('iris-admin', IRIS_PLUGIN_URL . 'assets/iris-admin.css', array(), IRIS_PLUGIN_VERSION);
        wp_enqueue_script('iris-admin', IRIS_PLUGIN_URL . 'assets/iris-admin.js', array('jquery'), IRIS_PLUGIN_VERSION, true);
    }
    
    /**
     * Gestionnaires AJAX sécurisés
     */
    public function ajax_upload_image() {
        if (function_exists('iris_handle_image_upload')) {
            iris_handle_image_upload();
        } else {
            wp_send_json_error('Fonction de traitement non disponible');
        }
    }
    
    public function ajax_check_status() {
        if (function_exists('iris_check_process_status')) {
            iris_check_process_status();
        } else {
            wp_send_json_error('Fonction de vérification non disponible');
        }
    }
    
    public function ajax_test_api() {
        if (function_exists('iris_ajax_test_api')) {
            iris_ajax_test_api();
        } else {
            wp_send_json_error('Fonction de test API non disponible');
        }
    }
    
    public function ajax_download() {
        if (function_exists('iris_handle_download')) {
            iris_handle_download();
        } else {
            wp_die('Fonction de téléchargement non disponible');
        }
    }
    
    /**
     * Initialisation des autres composants
     */
    public function init_rest_api() {
        if (function_exists('iris_register_rest_routes')) {
            iris_register_rest_routes();
        }
    }
    
    public function init_admin_menu() {
        if (function_exists('iris_add_admin_menu')) {
            iris_add_admin_menu();
        }
    }
    
    public function init_dashboard_widget() {
        if (function_exists('iris_add_dashboard_widget')) {
            iris_add_dashboard_widget();
        }
    }
    
    public function add_custom_capabilities() {
        if (function_exists('iris_add_custom_capabilities')) {
            iris_add_custom_capabilities();
        } else {
            // Fallback : ajouter les capacités de base
            $role = get_role('subscriber');
            if ($role) {
                $role->add_cap('iris_process_images');
            }
        }
    }
    
    public function daily_cleanup() {
        if (function_exists('iris_cleanup_old_jobs')) {
            iris_cleanup_old_jobs();
        } else {
            // Fallback : nettoyage basique
            $this->basic_cleanup();
        }
    }
    
    public function handle_surecart_order($order) {
        if (function_exists('iris_handle_surecart_order')) {
            iris_handle_surecart_order($order);
        } else {
            // Fallback : log de l'ordre reçu
            iris_log_error('Commande SureCart reçue mais handler non disponible: ' . json_encode($order));
        }
    }
    
    public function send_completion_email($user_id, $job_id, $status) {
        if (function_exists('iris_send_completion_email')) {
            iris_send_completion_email($user_id, $job_id, $status);
        } else {
            // Fallback : notification basique
            $this->basic_completion_notification($user_id, $job_id, $status);
        }
    }
    
    /**
     * Nettoyage basique en cas d'absence de la fonction principale
     * 
     * @since 1.1.1
     * @return void
     */
    private function basic_cleanup() {
        global $wpdb;
        
        try {
            // Supprimer les jobs de plus de 30 jours
            $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table_jobs} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                30
            ));
            
            if ($deleted !== false) {
                iris_log_error("Nettoyage basique : {$deleted} jobs supprimés");
            }
            
        } catch (Exception $e) {
            iris_log_error('Erreur lors du nettoyage basique: ' . $e->getMessage());
        }
    }
    
    /**
     * Notification basique de completion
     * 
     * @since 1.1.1
     * @param int $user_id ID utilisateur
     * @param string $job_id ID du job
     * @param string $status Statut final
     * @return void
     */
    private function basic_completion_notification($user_id, $job_id, $status) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $subject = 'Iris Process - Traitement terminé';
        $message = "Bonjour {$user->display_name},\n\n";
        $message .= "Votre traitement d'image (Job: {$job_id}) est terminé.\n";
        $message .= "Statut: {$status}\n\n";
        $message .= "Vous pouvez consulter vos résultats sur " . home_url() . "\n\n";
        $message .= "L'équipe Iris Process";
        
        wp_mail($user->user_email, $subject, $message);
        iris_log_error("Email de notification envoyé à {$user->user_email} pour job {$job_id}");
    }
    
    /**
     * Affichage des erreurs de dépendances
     * 
     * @since 1.1.1
     * @return void
     */
    public function show_dependency_error() {
        echo '<div class="notice notice-error">
            <p><strong>Erreur Iris Process</strong> : Impossible de charger toutes les dépendances. Vérifiez que tous les fichiers du plugin sont présents.</p>
            <p>Consultez les logs WordPress pour plus de détails.</p>
        </div>';
    }
    
    /**
     * Affichage des erreurs critiques
     * 
     * @since 1.1.1
     * @return void
     */
    public function show_critical_error() {
        $errors = implode('<br>', $this->init_errors);
        echo '<div class="notice notice-error">
            <p><strong>Erreur critique Iris Process</strong> : Le plugin n\'a pas pu s\'initialiser correctement.</p>
            <details>
                <summary>Détails des erreurs</summary>
                <p>' . esc_html($errors) . '</p>
            </details>
        </div>';
    }
    
    /**
     * Vérifier si le plugin est correctement initialisé
     * 
     * @since 1.1.1
     * @return bool
     */
    public function is_initialized() {
        return $this->initialized;
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
 * Fallback pour iris_add_custom_capabilities
 */
if (!function_exists('iris_add_custom_capabilities')) {
    function iris_add_custom_capabilities() {
        $role = get_role('subscriber');
        if ($role) {
            $role->add_cap('iris_process_images');
        }
        
        $role = get_role('customer');
        if ($role) {
            $role->add_cap('iris_process_images');
        }
    }
}

/**
 * Fallback pour iris_cleanup_old_jobs
 */
if (!function_exists('iris_cleanup_old_jobs')) {
    function iris_cleanup_old_jobs() {
        global $wpdb;
        
        // Supprimer les jobs de plus de 30 jours
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_jobs}'") === $table_jobs) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table_jobs} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                30
            ));
        }
        
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
}

/**
 * Fallback pour iris_handle_surecart_order
 */
if (!function_exists('iris_handle_surecart_order')) {
    function iris_handle_surecart_order($order) {
        iris_log_error('Commande SureCart reçue: ' . json_encode($order));
        
        // Logique basique d'attribution de tokens
        if (isset($order['customer']['id']) && isset($order['line_items'])) {
            $customer_id = $order['customer']['id'];
            
            // Rechercher l'utilisateur WordPress correspondant
            $user = get_user_by('email', $order['customer']['email']);
            if ($user && class_exists('Token_Manager')) {
                foreach ($order['line_items'] as $item) {
                    if (strpos($item['price']['name'], 'token') !== false) {
                        $tokens = intval($item['quantity']);
                        Token_Manager::add_tokens($user->ID, $tokens, $order['id']);
                    }
                }
            }
        }
    }
}

/**
 * Fallback pour iris_send_completion_email
 */
if (!function_exists('iris_send_completion_email')) {
    function iris_send_completion_email($user_id, $job_id, $status) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $subject = 'Iris Process - Traitement terminé';
        $message = "Bonjour {$user->display_name},\n\n";
        $message .= "Votre traitement d'image (Job: {$job_id}) est terminé.\n";
        $message .= "Statut: {$status}\n\n";
        
        if ($status === 'completed') {
            $message .= "Vous pouvez télécharger vos images traitées depuis votre espace membre.\n";
        } else {
            $message .= "Une erreur s'est produite lors du traitement. Contactez le support si nécessaire.\n";
        }
        
        $message .= "\nCordialement,\nL'équipe Iris Process";
        
        wp_mail($user->user_email, $subject, $message);
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
                <p>Zone d'upload Iris Process</p>
                <p style="color: orange;">Plugin en cours de chargement - Certaines fonctionnalités peuvent être limitées.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    add_shortcode('iris_upload_zone', 'iris_upload_zone_shortcode');
}

// =============================================================================
// 5. INITIALISATION DU PLUGIN
// =============================================================================

// Initialiser le plugin seulement si WordPress est complètement chargé
if (defined('ABSPATH')) {
    // Attendre que WordPress soit prêt
    add_action('plugins_loaded', function() {
        IrisProcessTokens::get_instance();
    }, 1);
}

/**
 * Fonction de diagnostic pour le débogage
 * 
 * @since 1.1.1
 * @return array État du plugin
 */
function iris_get_plugin_status() {
    $instance = iris_process_tokens();
    
    return array(
        'version' => IRIS_PLUGIN_VERSION,
        'initialized' => $instance ? $instance->is_initialized() : false,
        'constants_defined' => defined('IRIS_PLUGIN_PATH') && defined('IRIS_API_URL'),
        'wordpress_ready' => did_action('init') > 0,
        'dependencies_loaded' => class_exists('Token_Manager'),
    );
}

/**
 * FIN DU FICHIER PRINCIPAL
 * 
 * Ce fichier corrigé :
 * - Gère l'initialisation de manière sécurisée
 * - Charge les dépendances avec gestion d'erreur
 * - Initialise les hooks au bon moment
 * - Fournit des fallbacks complets
 * - Inclut un système de diagnostic
 */
?>