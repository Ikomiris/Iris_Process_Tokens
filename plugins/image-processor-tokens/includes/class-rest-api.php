<?php
/**
 * API REST pour Iris Process
 * 
 * @package IrisProcessTokens
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe de gestion de l'API REST
 * 
 * Fournit les endpoints REST pour l'interaction avec l'API Python et les applications externes
 * 
 * @since 1.0.0
 */
class Iris_Process_Rest_Api {
    
    /**
     * Namespace de l'API
     * 
     * @since 1.0.0
     * @var string
     */
    private $namespace = 'iris/v1';
    
    /**
     * Version de l'API
     * 
     * @since 1.0.0
     * @var string
     */
    private $version = '1.1.0';
    
    /**
     * Constructeur - Enregistrement des hooks
     * 
     * @since 1.0.0
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('init', array($this, 'add_cors_support'));
    }
    
    /**
     * Ajouter le support CORS pour les requêtes cross-origin
     * 
     * @since 1.0.0
     * @return void
     */
    public function add_cors_support() {
        // Ajouter les headers CORS si nécessaire
        add_action('rest_api_init', function() {
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
            add_filter('rest_pre_serve_request', array($this, 'add_cors_headers'));
        }, 15);
    }
    
    /**
     * Ajouter les headers CORS personnalisés
     * 
     * @since 1.0.0
     * @param bool $value
     * @return bool
     */
    public function add_cors_headers($value) {
        $origin = get_http_origin();
        
        // Autoriser les requêtes depuis l'API Python
        $allowed_origins = array(
            IRIS_API_URL,
            home_url(),
            admin_url()
        );
        
        if (in_array($origin, $allowed_origins) || current_user_can('manage_options')) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
        }
        
        return $value;
    }
    
    /**
     * Enregistrer les routes de l'API REST
     * 
     * @since 1.0.0
     * @return void
     */
    public function register_routes() {
        // Route d'information sur l'API
        register_rest_route($this->namespace, '/info', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_api_info'),
            'permission_callback' => '__return_true'
        ));
        
        // Route de callback depuis l'API Python
        register_rest_route($this->namespace, '/callback', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_callback'),
            'permission_callback' => array($this, 'verify_api_callback'),
            'args' => array(
                'job_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array($this, 'validate_job_id')
                ),
                'status' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array('pending', 'processing', 'completed', 'failed'),
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'user_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => array($this, 'validate_user_id')
                )
            )
        ));
        
        // Route de statut d'un job
        register_rest_route($this->namespace, '/status/(?P<job_id>[a-zA-Z0-9_-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_job_status'),
            'permission_callback' => array($this, 'check_job_access'),
            'args' => array(
                'job_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array($this, 'validate_job_id')
                )
            )
        ));
        
        // Route de téléchargement sécurisé
        register_rest_route($this->namespace, '/download/(?P<job_id>[a-zA-Z0-9_-]+)/(?P<file_name>[^/]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'download_file'),
            'permission_callback' => array($this, 'check_download_access'),
            'args' => array(
                'job_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'file_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_file_name'
                )
            )
        ));
        
        // Route des statistiques (admin uniquement)
        register_rest_route($this->namespace, '/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stats'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        // Route des utilisateurs et leurs jetons (admin)
        register_rest_route($this->namespace, '/users', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_users_list'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'page' => array(
                    'default' => 1,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ),
                'per_page' => array(
                    'default' => 20,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                )
            )
        ));
        
        // Route pour les presets (lecture publique)
        register_rest_route($this->namespace, '/presets', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_presets'),
            'permission_callback' => '__return_true'
        ));
        
        // Route pour un preset spécifique
        register_rest_route($this->namespace, '/presets/(?P<preset_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_preset'),
            'permission_callback' => '__return_true',
            'args' => array(
                'preset_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                )
            )
        ));
        
        // Route de test de santé
        register_rest_route($this->namespace, '/health', array(
            'methods' => 'GET',
            'callback' => array($this, 'health_check'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Informations sur l'API
     * 
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_api_info($request) {
        $info = array(
            'name' => 'Iris Process API',
            'version' => $this->version,
            'plugin_version' => IRIS_PLUGIN_VERSION,
            'namespace' => $this->namespace,
            'endpoints' => array(
                '/info' => 'Informations sur l\'API',
                '/callback' => 'Callback depuis l\'API Python',
                '/status/{job_id}' => 'Statut d\'un job',
                '/download/{job_id}/{file_name}' => 'Téléchargement de fichier',
                '/stats' => 'Statistiques (admin)',
                '/users' => 'Liste des utilisateurs (admin)',
                '/presets' => 'Liste des presets',
                '/presets/{preset_id}' => 'Preset spécifique',
                '/health' => 'Vérification de santé'
            ),
            'python_api_url' => IRIS_API_URL,
            'timestamp' => current_time('timestamp'),
            'timezone' => get_option('timezone_string')
        );
        
        return rest_ensure_response($info);
    }
    
    /**
     * Gérer le callback de l'API Python
     * 
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_callback($request) {
        try {
            $data = $request->get_json_params();
            
            if (empty($data)) {
                return new WP_Error('missing_data', 'Données JSON manquantes', array('status' => 400));
            }
            
            // Log du callback reçu
            iris_log_error('Callback reçu: ' . json_encode($data));
            
            // Valider les données requises
            $required_fields = array('job_id', 'status', 'user_id');
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return new WP_Error('missing_field', "Champ requis manquant: $field", array('status' => 400));
                }
            }
            
            $processor = new Iris_Process_Image_Processor();
            $response = $processor->handle_api_callback($data);
            
            // Log de la réponse
            iris_log_error('Callback traité avec succès pour job: ' . $data['job_id']);
            
            return $response;
            
        } catch (Exception $e) {
            iris_log_error('Erreur dans handle_callback: ' . $e->getMessage());
            return new WP_Error('callback_error', 'Erreur lors du traitement du callback', array('status' => 500));
        }
    }
    
    /**
     * Obtenir le statut d'un job
     * 
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_job_status($request) {
        try {
            $job_id = $request->get_param('job_id');
            
            if (empty($job_id)) {
                return new WP_Error('missing_job_id', 'ID de job manquant', array('status' => 400));
            }
            
            $processor = new Iris_Process_Image_Processor();
            return $processor->get_job_status($job_id);
            
        } catch (Exception $e) {
            iris_log_error('Erreur dans get_job_status: ' . $e->getMessage());
            return new WP_Error('status_error', 'Erreur lors de la récupération du statut', array('status' => 500));
        }
    }
    
    /**
     * Téléchargement sécurisé de fichiers
     * 
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function download_file($request) {
        try {
            $job_id = $request->get_param('job_id');
            $file_name = $request->get_param('file_name');
            
            if (empty($job_id) || empty($file_name)) {
                return new WP_Error('missing_params', 'Paramètres manquants', array('status' => 400));
            }
            
            global $wpdb;
            $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
            
            // Récupérer le job
            $job = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_jobs WHERE job_id = %s",
                $job_id
            ));
            
            if (!$job) {
                return new WP_Error('job_not_found', 'Job non trouvé', array('status' => 404));
            }
            
            if ($job->status !== 'completed') {
                return new WP_Error('job_not_completed', 'Job non terminé', array('status' => 400));
            }
            
            // Vérifier les fichiers de résultat
            $result_files = json_decode($job->result_files, true);
            if (!$result_files || !is_array($result_files)) {
                return new WP_Error('no_files', 'Aucun fichier disponible', array('status' => 404));
            }
            
            // Chercher le fichier demandé
            $file_path = null;
            foreach ($result_files as $file) {
                if (basename($file) === $file_name) {
                    $file_path = $file;
                    break;
                }
            }
            
            if (!$file_path || !file_exists($file_path)) {
                return new WP_Error('file_not_found', 'Fichier non trouvé', array('status' => 404));
            }
            
            // Vérifier la sécurité du chemin
            $upload_dir = wp_upload_dir();
            $allowed_dir = $upload_dir['basedir'] . '/iris-process';
            
            if (strpos(realpath($file_path), realpath($allowed_dir)) !== 0) {
                return new WP_Error('security_error', 'Accès non autorisé', array('status' => 403));
            }
            
            // Préparer le téléchargement
            $file_size = filesize($file_path);
            $mime_type = mime_content_type($file_path) ?: 'application/octet-stream';
            
            // Headers pour le téléchargement
            header('Content-Type: ' . $mime_type);
            header('Content-Disposition: attachment; filename="' . basename($file_name) . '"');
            header('Content-Length: ' . $file_size);
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
            
            // Nettoyage du buffer
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Envoi du fichier
            readfile($file_path);
            
            // Log du téléchargement
            iris_log_error("Téléchargement REST: $file_name pour job $job_id");
            
            exit;
            
        } catch (Exception $e) {
            iris_log_error('Erreur dans download_file: ' . $e->getMessage());
            return new WP_Error('download_error', 'Erreur lors du téléchargement', array('status' => 500));
        }
    }
    
    /**
     * Obtenir les statistiques (admin)
     * 
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_stats($request) {
        try {
            $stats = array();
            
            // Statistiques des jetons
            if (class_exists('Token_Manager')) {
                $stats['tokens'] = Token_Manager::get_global_stats();
            }
            
            // Statistiques du processeur
            $processor = new Iris_Process_Image_Processor();
            $stats['processor'] = $processor->get_processor_stats();
            
            // Statistiques de la base de données
            global $wpdb;
            $tables = array(
                'iris_user_tokens' => 'Utilisateurs avec jetons',
                'iris_token_transactions' => 'Transactions',
                'iris_processing_jobs' => 'Jobs de traitement',
                'iris_image_processes' => 'Processus d\'images'
            );
            
            $stats['database'] = array();
            foreach ($tables as $table_suffix => $description) {
                $table_name = $wpdb->prefix . $table_suffix;
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                $stats['database'][$table_suffix] = array(
                    'description' => $description,
                    'count' => intval($count)
                );
            }
            
            // Informations système
            $stats['system'] = array(
                'plugin_version' => IRIS_PLUGIN_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'api_url' => IRIS_API_URL,
                'timestamp' => current_time('timestamp')
            );
            
            return rest_ensure_response($stats);
            
        } catch (Exception $e) {
            iris_log_error('Erreur dans get_stats: ' . $e->getMessage());
            return new WP_Error('stats_error', 'Erreur lors de la récupération des statistiques', array('status' => 500));
        }
    }
    
    /**
     * Obtenir la liste des utilisateurs avec jetons (admin)
     * 
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_users_list($request) {
        try {
            $page = max(1, $request->get_param('page'));
            $per_page = min(100, max(1, $request->get_param('per_page')));
            $offset = ($page - 1) * $per_page;
            
            global $wpdb;
            $table_tokens = $wpdb->prefix . 'iris_user_tokens';
            
            // Récupérer les utilisateurs avec leurs informations de jetons
            $users = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    t.*,
                    u.display_name,
                    u.user_email,
                    u.user_registered
                FROM $table_tokens t
                JOIN {$wpdb->users} u ON t.user_id = u.ID
                ORDER BY t.updated_at DESC
                LIMIT %d OFFSET %d
            ", $per_page, $offset));
            
            // Compter le total pour la pagination
            $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_tokens");
            
            $response = rest_ensure_response($users);
            $response->header('X-Total-Count', $total);
            $response->header('X-Total-Pages', ceil($total / $per_page));
            
            return $response;
            
        } catch (Exception $e) {
            iris_log_error('Erreur dans get_users_list: ' . $e->getMessage());
            return new WP_Error('users_error', 'Erreur lors de la récupération des utilisateurs', array('status' => 500));
        }
    }
    
    /**
     * Obtenir la liste des presets
     * 
     * @since 1.1.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_presets($request) {
        try {
            if (class_exists('Preset_Manager')) {
                $presets = Preset_Manager::list_all();
                return rest_ensure_response($presets);
            } else {
                return rest_ensure_response(array());
            }
            
        } catch (Exception $e) {
            iris_log_error('Erreur dans get_presets: ' . $e->getMessage());
            return new WP_Error('presets_error', 'Erreur lors de la récupération des presets', array('status' => 500));
        }
    }
    
    /**
     * Obtenir un preset spécifique
     * 
     * @since 1.1.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_preset($request) {
        try {
            $preset_id = $request->get_param('preset_id');
            
            if (class_exists('Preset_Manager')) {
                $preset_data = Preset_Manager::get_by_id($preset_id);
                
                if ($preset_data) {
                    return rest_ensure_response($preset_data);
                } else {
                    return new WP_Error('preset_not_found', 'Preset non trouvé', array('status' => 404));
                }
            } else {
                return new WP_Error('presets_unavailable', 'Gestionnaire de presets non disponible', array('status' => 503));
            }
            
        } catch (Exception $e) {
            iris_log_error('Erreur dans get_preset: ' . $e->getMessage());
            return new WP_Error('preset_error', 'Erreur lors de la récupération du preset', array('status' => 500));
        }
    }
    
    /**
     * Vérification de santé de l'API
     * 
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function health_check($request) {
        $health = array(
            'status' => 'ok',
            'timestamp' => current_time('timestamp'),
            'version' => IRIS_PLUGIN_VERSION,
            'checks' => array()
        );
        
        // Vérification de la base de données
        try {
            global $wpdb;
            $wpdb->get_var("SELECT 1");
            $health['checks']['database'] = 'ok';
        } catch (Exception $e) {
            $health['checks']['database'] = 'error';
            $health['status'] = 'degraded';
        }
        
        // Vérification des fichiers d'upload
        $upload_dir = wp_upload_dir();
        $iris_dir = $upload_dir['basedir'] . '/iris-process';
        
        if (is_writable($iris_dir)) {
            $health['checks']['upload_directory'] = 'ok';
        } else {
            $health['checks']['upload_directory'] = 'error';
            $health['status'] = 'degraded';
        }
        
        // Vérification de l'API Python
        if (function_exists('iris_test_api_connection')) {
            $api_test = iris_test_api_connection();
            $health['checks']['python_api'] = $api_test['success'] ? 'ok' : 'error';
            
            if (!$api_test['success']) {
                $health['status'] = 'degraded';
            }
        }
        
        return rest_ensure_response($health);
    }
    
    /**
     * Vérifier les permissions admin
     * 
     * @since 1.0.0
     * @return bool
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }
    
    /**
     * Vérifier l'accès à un job
     * 
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return bool
     */
    public function check_job_access($request) {
        // Admin a accès à tout
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Utilisateur connecté : vérifier la propriété du job
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        
        $job_id = $request->get_param('job_id');
        if (empty($job_id)) {
            return false;
        }
        
        global $wpdb;
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        
        $job_user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM $table_jobs WHERE job_id = %s",
            $job_id
        ));
        
        return $job_user_id && intval($job_user_id) === $user_id;
    }
    
    /**
     * Vérifier l'accès au téléchargement
     * 
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return bool
     */
    public function check_download_access($request) {
        return $this->check_job_access($request);
    }
    
    /**
     * Vérifier la validité du callback API
     * 
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return bool
     */
    public function verify_api_callback($request) {
        // Pour l'instant, autoriser tous les callbacks
        // TODO: Implémenter une vérification par signature ou token
        return true;
    }
    
    /**
     * Valider un ID de job
     * 
     * @since 1.0.0
     * @param string $param
     * @return bool
     */
    public function validate_job_id($param) {
        return !empty($param) && preg_match('/^[a-zA-Z0-9_-]+$/', $param);
    }
    
    /**
     * Valider un ID utilisateur
     * 
     * @since 1.0.0
     * @param int $param
     * @return bool
     */
    public function validate_user_id($param) {
        return is_numeric($param) && intval($param) > 0;
    }
}