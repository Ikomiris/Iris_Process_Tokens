<?php
if (!defined('ABSPATH')) {
    exit;
}

class Token_Manager {
    
    public function __construct() {
        // Hooks si nécessaire
    }
    
    public static function get_user_balance($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'iris_user_tokens';
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT token_balance FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        return $result ? intval($result) : 0;
    }
    
    public static function add_tokens($user_id, $amount, $order_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'iris_user_tokens';
        $transactions_table = $wpdb->prefix . 'iris_token_transactions';
        
        // Mise à jour ou création du solde
        $wpdb->query($wpdb->prepare("
            INSERT INTO $table (user_id, token_balance, total_purchased) 
            VALUES (%d, %d, %d)
            ON DUPLICATE KEY UPDATE 
            token_balance = token_balance + %d,
            total_purchased = total_purchased + %d,
            updated_at = CURRENT_TIMESTAMP
        ", $user_id, $amount, $amount, $amount, $amount));
        
        // Enregistrement de la transaction
        $wpdb->insert($transactions_table, array(
            'user_id' => $user_id,
            'transaction_type' => 'purchase',
            'tokens_amount' => $amount,
            'order_id' => $order_id,
            'description' => "Achat de $amount jetons Iris Process"
        ));
        
        return true;
    }
    
    public static function use_token($user_id, $image_process_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'iris_user_tokens';
        $transactions_table = $wpdb->prefix . 'iris_token_transactions';
        
        $balance = self::get_user_balance($user_id);
        if ($balance < 1) {
            return false;
        }
        
        $wpdb->query($wpdb->prepare("
            UPDATE $table 
            SET token_balance = token_balance - 1,
                total_used = total_used + 1,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = %d
        ", $user_id));
        
        $wpdb->insert($transactions_table, array(
            'user_id' => $user_id,
            'transaction_type' => 'usage',
            'tokens_amount' => -1,
            'image_process_id' => $image_process_id,
            'description' => "Traitement d'image Iris Process"
        ));
        
        return true;
    }
    
    public static function get_user_transactions($user_id, $limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'iris_token_transactions';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id, $limit
        ));
    }
}