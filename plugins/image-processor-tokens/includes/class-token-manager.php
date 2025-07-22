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
        
        // Validation des paramètres
        if (!is_numeric($user_id) || $user_id <= 0) {
            iris_log_error("Token_Manager::add_tokens - User ID invalide: $user_id");
            return false;
        }
        
        if (!is_numeric($amount) || $amount <= 0) {
            iris_log_error("Token_Manager::add_tokens - Montant invalide: $amount");
            return false;
        }
        
        // Début de transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Mise à jour ou création du solde avec INSERT ... ON DUPLICATE KEY UPDATE
            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO $table_tokens (user_id, token_balance, total_purchased, created_at) 
                 VALUES (%d, %d, %d, NOW()) 
                 ON DUPLICATE KEY UPDATE 
                 token_balance = token_balance + %d, 
                 total_purchased = total_purchased + %d,
                 updated_at = NOW()",
                $user_id, $amount, $amount, $amount, $amount
            ));
            
            if ($result === false) {
                throw new Exception("Erreur lors de la mise à jour du solde");
            }
            
            // Enregistrement de la transaction
            $transaction_result = $wpdb->insert(
                $table_transactions,
                array(
                    'user_id' => $user_id,
                    'transaction_type' => 'purchase',
                    'tokens_amount' => $amount,
                    'order_id' => $order_id,
                    'description' => 'Achat de jetons',
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%d', '%s', '%s', '%s')
            );
            
            if ($transaction_result === false) {
                throw new Exception("Erreur lors de l'enregistrement de la transaction");
            }
            
            $wpdb->query('COMMIT');
            
            iris_log_error("Jetons ajoutés avec succès: $amount jetons pour l'utilisateur $user_id");
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            iris_log_error("Erreur Token_Manager::add_tokens: " . $e->getMessage());
            return false;
        }
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
        
        // Validation des paramètres
        if (!is_numeric($user_id) || $user_id <= 0) {
            iris_log_error("Token_Manager::use_token - User ID invalide: $user_id");
            return false;
        }
        
        // Début de transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Vérifier le solde avec verrouillage
            $current_balance = $wpdb->get_var($wpdb->prepare(
                "SELECT token_balance FROM $table_tokens WHERE user_id = %d FOR UPDATE",
                $user_id
            ));
            
            if ($current_balance === null || intval($current_balance) < 1) {
                throw new Exception("Solde insuffisant");
            }
            
            // Déduire le jeton
            $update_result = $wpdb->query($wpdb->prepare(
                "UPDATE $table_tokens 
                 SET token_balance = token_balance - 1, 
                     total_used = total_used + 1,
                     updated_at = NOW()
                 WHERE user_id = %d AND token_balance >= 1",
                $user_id
            ));
            
            if ($update_result === 0) {
                throw new Exception("Impossible de déduire le jeton - solde insuffisant");
            }
            
            // Enregistrer la transaction
            $transaction_result = $wpdb->insert(
                $table_transactions,
                array(
                    'user_id' => $user_id,
                    'transaction_type' => 'usage',
                    'tokens_amount' => -1,
                    'image_process_id' => $image_process_id,
                    'description' => 'Traitement d\'image',
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%d', '%d', '%s', '%s')
            );
            
            if ($transaction_result === false) {
                throw new Exception("Erreur lors de l'enregistrement de la transaction");
            }
            
            $wpdb->query('COMMIT');
            
            iris_log_error("Jeton utilisé avec succès pour l'utilisateur $user_id (process_id: $image_process_id)");
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            iris_log_error("Erreur Token_Manager::use_token: " . $e->getMessage());
            return false;
        }
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
        
        // Validation des paramètres
        if (!is_numeric($user_id) || $user_id <= 0) {
            iris_log_error("Token_Manager::get_user_transactions - User ID invalide: $user_id");
            return array();
        }
        
        $limit = max(1, min(100, intval($limit))); // Limiter entre 1 et 100
        
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id, $limit
        ));
        
        return $transactions ? $transactions : array();
    }
    
    /**
     * Obtenir les statistiques globales des jetons
     * 
     * @since 1.0.0
     * @return array Statistiques des jetons
     */
    public static function get_global_stats() {
        global $wpdb;
        $table_tokens = $wpdb->prefix . 'iris_user_tokens';
        $table_transactions = $wpdb->prefix . 'iris_token_transactions';
        
        $stats = array(
            'total_users' => 0,
            'total_tokens_purchased' => 0,
            'total_tokens_used' => 0,
            'total_tokens_remaining' => 0,
            'total_transactions' => 0
        );
        
        try {
            // Statistiques des utilisateurs et jetons
            $token_stats = $wpdb->get_row(
                "SELECT 
                    COUNT(*) as total_users,
                    COALESCE(SUM(total_purchased), 0) as total_purchased,
                    COALESCE(SUM(total_used), 0) as total_used,
                    COALESCE(SUM(token_balance), 0) as total_remaining
                 FROM $table_tokens"
            );
            
            if ($token_stats) {
                $stats['total_users'] = intval($token_stats->total_users);
                $stats['total_tokens_purchased'] = intval($token_stats->total_purchased);
                $stats['total_tokens_used'] = intval($token_stats->total_used);
                $stats['total_tokens_remaining'] = intval($token_stats->total_remaining);
            }
            
            // Nombre total de transactions
            $total_transactions = $wpdb->get_var("SELECT COUNT(*) FROM $table_transactions");
            $stats['total_transactions'] = intval($total_transactions);
            
        } catch (Exception $e) {
            iris_log_error("Erreur Token_Manager::get_global_stats: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Rembourser des jetons à un utilisateur
     * 
     * @since 1.0.0
     * @param int $user_id ID de l'utilisateur
     * @param int $amount Nombre de jetons à rembourser
     * @param string $reason Raison du remboursement
     * @return bool Succès de l'opération
     */
    public static function refund_tokens($user_id, $amount, $reason = 'Remboursement') {
        global $wpdb;
        $table_tokens = $wpdb->prefix . 'iris_user_tokens';
        $table_transactions = $wpdb->prefix . 'iris_token_transactions';
        
        // Validation des paramètres
        if (!is_numeric($user_id) || $user_id <= 0) {
            iris_log_error("Token_Manager::refund_tokens - User ID invalide: $user_id");
            return false;
        }
        
        if (!is_numeric($amount) || $amount <= 0) {
            iris_log_error("Token_Manager::refund_tokens - Montant invalide: $amount");
            return false;
        }
        
        // Début de transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Ajouter les jetons remboursés au solde
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE $table_tokens 
                 SET token_balance = token_balance + %d,
                     updated_at = NOW()
                 WHERE user_id = %d",
                $amount, $user_id
            ));
            
            if ($result === false) {
                throw new Exception("Erreur lors du remboursement");
            }
            
            // Si l'utilisateur n'existe pas, créer l'enregistrement
            if ($wpdb->rows_affected === 0) {
                $insert_result = $wpdb->insert(
                    $table_tokens,
                    array(
                        'user_id' => $user_id,
                        'token_balance' => $amount,
                        'total_purchased' => 0,
                        'total_used' => 0,
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%d', '%d', '%d', '%s')
                );
                
                if ($insert_result === false) {
                    throw new Exception("Erreur lors de la création du compte utilisateur");
                }
            }
            
            // Enregistrer la transaction de remboursement
            $transaction_result = $wpdb->insert(
                $table_transactions,
                array(
                    'user_id' => $user_id,
                    'transaction_type' => 'refund',
                    'tokens_amount' => $amount,
                    'description' => sanitize_text_field($reason),
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%d', '%s', '%s')
            );
            
            if ($transaction_result === false) {
                throw new Exception("Erreur lors de l'enregistrement de la transaction de remboursement");
            }
            
            $wpdb->query('COMMIT');
            
            iris_log_error("Remboursement effectué: $amount jetons pour l'utilisateur $user_id - Raison: $reason");
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            iris_log_error("Erreur Token_Manager::refund_tokens: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Vérifier l'intégrité des données de jetons
     * 
     * @since 1.0.0
     * @return array Rapport de vérification
     */
    public static function verify_data_integrity() {
        global $wpdb;
        $table_tokens = $wpdb->prefix . 'iris_user_tokens';
        $table_transactions = $wpdb->prefix . 'iris_token_transactions';
        
        $report = array(
            'status' => 'success',
            'issues' => array(),
            'users_checked' => 0,
            'errors_found' => 0
        );
        
        try {
            // Récupérer tous les utilisateurs avec des jetons
            $users = $wpdb->get_results("SELECT * FROM $table_tokens");
            $report['users_checked'] = count($users);
            
            foreach ($users as $user) {
                // Calculer le solde basé sur les transactions
                $transactions_sum = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(tokens_amount), 0) FROM $table_transactions WHERE user_id = %d",
                    $user->user_id
                ));
                
                $expected_balance = intval($transactions_sum);
                $actual_balance = intval($user->token_balance);
                
                // Vérifier la cohérence
                if ($expected_balance !== $actual_balance) {
                    $report['issues'][] = array(
                        'user_id' => $user->user_id,
                        'type' => 'balance_mismatch',
                        'expected' => $expected_balance,
                        'actual' => $actual_balance,
                        'difference' => $actual_balance - $expected_balance
                    );
                    $report['errors_found']++;
                }
                
                // Vérifier que le solde n'est pas négatif
                if ($actual_balance < 0) {
                    $report['issues'][] = array(
                        'user_id' => $user->user_id,
                        'type' => 'negative_balance',
                        'balance' => $actual_balance
                    );
                    $report['errors_found']++;
                }
            }
            
            if ($report['errors_found'] > 0) {
                $report['status'] = 'errors_found';
            }
            
        } catch (Exception $e) {
            $report['status'] = 'error';
            $report['error_message'] = $e->getMessage();
            iris_log_error("Erreur Token_Manager::verify_data_integrity: " . $e->getMessage());
        }
        
        return $report;
    }

    /**
     * Définir le solde de jetons d'un utilisateur (modification directe)
     *
     * @since 1.2.0
     * @param int $user_id ID de l'utilisateur
     * @param int $new_balance Nouveau solde de jetons
     * @param string $reason Raison de la modification (optionnel)
     * @return bool Succès de l'opération
     */
    public static function set_user_balance($user_id, $new_balance, $reason = 'Ajustement manuel') {
        global $wpdb;
        $table_tokens = $wpdb->prefix . 'iris_user_tokens';
        $table_transactions = $wpdb->prefix . 'iris_token_transactions';

        if (!is_numeric($user_id) || $user_id <= 0) {
            iris_log_error("Token_Manager::set_user_balance - User ID invalide: $user_id");
            return false;
        }
        if (!is_numeric($new_balance) || $new_balance < 0) {
            iris_log_error("Token_Manager::set_user_balance - Solde invalide: $new_balance");
            return false;
        }

        $wpdb->query('START TRANSACTION');
        try {
            // Récupérer l'ancien solde
            $old_balance = self::get_user_balance($user_id);
            $diff = $new_balance - $old_balance;

            // Mettre à jour ou créer le solde
            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO $table_tokens (user_id, token_balance, total_purchased, total_used, created_at, updated_at)
                 VALUES (%d, %d, %d, %d, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE token_balance = %d, updated_at = NOW()",
                $user_id, $new_balance, max($diff,0), max(-$diff,0), $new_balance
            ));
            if ($result === false) {
                throw new Exception("Erreur lors de la mise à jour du solde");
            }
            // Enregistrer la transaction d'ajustement
            if ($diff !== 0) {
                $wpdb->insert(
                    $table_transactions,
                    array(
                        'user_id' => $user_id,
                        'transaction_type' => 'adjustment',
                        'tokens_amount' => $diff,
                        'description' => sanitize_text_field($reason),
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%d', '%s', '%s')
                );
            }
            $wpdb->query('COMMIT');
            iris_log_error("Solde modifié manuellement pour utilisateur $user_id: $old_balance => $new_balance");
            return true;
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            iris_log_error("Erreur Token_Manager::set_user_balance: " . $e->getMessage());
            return false;
        }
    }
}