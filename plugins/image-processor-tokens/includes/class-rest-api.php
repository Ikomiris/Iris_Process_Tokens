<?php
if (!defined('ABSPATH')) {
    exit;
}

class Iris_Process_Rest_Api {
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    public function register_routes() {
        register_rest_route('iris/v1', '/callback', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_callback'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('iris/v1', '/status/(?P<job_id>[a-zA-Z0-9_]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_job_status'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('iris/v1', '/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stats'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
    }
    
    public function handle_callback($request) {
        $data = $request->get_json_params();
        
        if (!isset($data['job_id'])) {
            return new WP_Error('missing_data', 'Job ID manquant', array('status' => 400));
        }
        
        $processor = new Iris_Process_Image_Processor();
        return $processor->handle_api_callback($data);
    }
    
    public function get_job_status($request) {
        $job_id = $request->get_param('job_id');
        $processor = new Iris_Process_Image_Processor();
        return $processor->get_job_status($job_id);
    }
    
    public function get_stats() {
        return iris_get_plugin_stats();
    }
    
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }
}