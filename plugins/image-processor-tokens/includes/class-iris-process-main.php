<?php
if (!defined('ABSPATH')) {
    exit;
}

class Iris_Process_Main {
    
    private static $instance = null;
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function load_dependencies() {
        // Chargement des classes
        require_once IRIS_PLUGIN_PATH . 'includes/class-token-manager.php';
        require_once IRIS_PLUGIN_PATH . 'includes/class-user-dashboard.php';
        require_once IRIS_PLUGIN_PATH . 'includes/class-image-processor.php';
        require_once IRIS_PLUGIN_PATH . 'includes/class-ajax-handlers.php';
        require_once IRIS_PLUGIN_PATH . 'includes/class-rest-api.php';
        require_once IRIS_PLUGIN_PATH . 'includes/class-surecart-integration.php';
        // require_once IRIS_PLUGIN_PATH . 'includes/class-xmp-manager.php';
        require_once IRIS_PLUGIN_PATH . 'shortcodes/class-shortcodes.php';
        
        if (is_admin()) {
            require_once IRIS_PLUGIN_PATH . 'admin/class-admin-menu.php';
            require_once IRIS_PLUGIN_PATH . 'admin/class-admin-pages.php';
        }
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'plugin_init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Initialiser les composants
        new Iris_Process_Ajax_Handlers();
        new Iris_Process_Rest_Api();
        new Iris_Process_Shortcodes();
        
        if (is_admin()) {
            new Iris_Process_Admin_Menu();
        }
    }
    
    public function plugin_init() {
        load_plugin_textdomain(
            'iris-process-tokens',
            false,
            dirname(plugin_basename(IRIS_PLUGIN_PATH)) . '/languages'
        );
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style('iris-upload', IRIS_PLUGIN_URL . 'assets/css/iris-upload.css', array(), IRIS_PLUGIN_VERSION);
        wp_enqueue_script('iris-upload', IRIS_PLUGIN_URL . 'assets/js/iris-upload.js', array('jquery'), IRIS_PLUGIN_VERSION, true);
        
        wp_localize_script('iris-upload', 'iris_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('iris_upload_nonce'),
            'max_file_size' => wp_max_upload_size()
        ));
    }
    
    public function admin_enqueue_scripts($hook) {
        // Pour cibler la page principale renommée en 'iris-dashboard' (anciennement 'iris-process')
        // Le hook sera 'toplevel_page_iris-dashboard' si le slug du menu est 'iris-dashboard'
        if ($hook !== 'toplevel_page_iris-dashboard') {
            return;
        }
        wp_enqueue_style('iris-admin', IRIS_PLUGIN_URL . 'assets/css/iris-admin.css', array(), IRIS_PLUGIN_VERSION);
        wp_enqueue_script('iris-admin', IRIS_PLUGIN_URL . 'assets/js/iris-admin.js', array('jquery'), IRIS_PLUGIN_VERSION, true);
    }
}