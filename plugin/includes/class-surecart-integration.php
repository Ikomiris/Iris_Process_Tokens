<?php
if (!defined('ABSPATH')) {
    exit;
}

class SureCart_Integration {
    
    public function __construct() {
        add_action('init', array($this, 'setup_webhook_endpoint'));
        add_action('template_redirect', array($this, 'handle_webhook_request'));
    }
    
    public function setup_webhook_endpoint() {
        add_rewrite_rule(
            '^webhook/surecart/?$',
            'index.php?surecart_webhook=1',
            'top'
        );
        
        add_filter('query_vars', function($vars) {
            $vars[] = 'surecart_webhook';
            return $vars;
        });
    }
    
    public function handle_webhook_request() {
        if (!get_query_var('surecart_webhook')) {
            return;
        }
        
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);
        
        // Log pour debug
        error_log('SureCart Webhook Iris Process: ' . print_r($data, true));
        
        // Vérifier la signature (à adapter selon SureCart)
        if (!$this->verify_webhook_signature($payload)) {
            status_header(401);
            exit('Unauthorized');
        }
        
        switch ($data['event']) {
            case 'order.completed':
                $this->process_token_purchase($data['order']);
                break;
            case 'order.refunded':
                $this->process_token_refund($data['order']);
                break;
        }
        
        status_header(200);
        exit('OK');
    }
    
    private function verify_webhook_signature($payload) {
        // À implémenter selon la documentation SureCart
        return true;
    }
    
    private function process_token_purchase($order) {
        foreach ($order['line_items'] as $item) {
            if ($this->is_token_product($item['product_id'])) {
                $user_id = $this->get_user_id_from_order($order);
                $tokens = $this->get_token_quantity_from_product($item);
                
                if ($user_id && $tokens > 0) {
                    Token_Manager::add_tokens($user_id, $tokens, $order['id']);
                }
            }
        }
    }
    
    private function process_token_refund($order) {
        // Logique pour gérer les remboursements
        // À implémenter selon les besoins
    }
    
    private function is_token_product($product_id) {
        $products = get_option('iris_process_products', array());
        return array_key_exists($product_id, $products);
    }
    
    private function get_token_quantity_from_product($item) {
        $products = get_option('iris_process_products', array());
        
        if (isset($products[$item['product_id']])) {
            return $products[$item['product_id']]['tokens'] * $item['quantity'];
        }
        
        return 0;
    }
    
    private function get_user_id_from_order($order) {
        if (isset($order['customer']['wp_user_id'])) {
            return $order['customer']['wp_user_id'];
        }
        
        if (isset($order['customer']['email'])) {
            $user = get_user_by('email', $order['customer']['email']);
            return $user ? $user->ID : null;
        }
        
        return null;
    }
}