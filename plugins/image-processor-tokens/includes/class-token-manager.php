<?php
/**
 * Classe de gestion des jetons
 * 
 * @package IrisProcessTokens
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
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
    
    /**
     * Récupérer les transactions d'un utilisateur
     * 
     * @since 1.0.0
     * @param int $user_id ID de l'utilisateur
     * @param int $limit Nombre de transactions à récupérer
     * @return array Liste des transactions
     */
    public static function get_user_transactions($user_id, $limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'iris_token_transactions';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id, $limit
        ));
    }
}