<?php
/**
 * Intégration SureCart
 * 
 * @package IrisProcessTokens
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
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