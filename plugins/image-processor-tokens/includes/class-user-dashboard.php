<?php
/**
 * Dashboard utilisateur pour Iris Process
 * 
 * @package IrisProcessTokens
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour le dashboard utilisateur
 * 
 * Gère l'affichage des informations utilisateur, historiques et interfaces
 * 
 * @since 1.0.0
 */
class User_Dashboard {
    
    /**
     * Constructeur
     * 
     * @since 1.0.0
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_dashboard_scripts'));
    }
    
    /**
     * Initialisation des shortcodes et hooks
     * 
     * @since 1.0.0
     * @return void
     */
    public function init() {
        // Enregistrement des shortcodes
        add_shortcode('user_token_balance', array($this, 'display_token_balance'));
        add_shortcode('token_history', array($this, 'display_token_history'));
        add_shortcode('iris_process_page', array($this, 'display_process_page'));
        add_shortcode('iris_user_dashboard', array($this, 'display_full_dashboard'));
        add_shortcode('iris_user_stats', array($this, 'display_user_stats'));
        
        // Hooks pour les actions utilisateur
        add_action('wp_ajax_iris_refresh_dashboard', array($this, 'ajax_refresh_dashboard'));
        add_action('wp_ajax_iris_get_job_progress', array($this, 'ajax_get_job_progress'));
    }
    
    /**
     * Charger les scripts et styles du dashboard
     * 
     * @since 1.0.0
     * @return void
     */
    public function enqueue_dashboard_scripts() {
        if ($this->is_dashboard_page()) {
            wp_enqueue_style(
                'iris-dashboard', 
                IRIS_PLUGIN_URL . 'assets/css/iris-dashboard.css', 
                array(), 
                IRIS_PLUGIN_VERSION
            );
            
            wp_enqueue_script(
                'iris-dashboard', 
                IRIS_PLUGIN_URL . 'assets/js/iris-dashboard.js', 
                array('jquery'), 
                IRIS_PLUGIN_VERSION, 
                true
            );
            
            wp_localize_script('iris-dashboard', 'iris_dashboard_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('iris_dashboard_nonce'),
                'strings' => array(
                    'loading' => 'Chargement...',
                    'error' => 'Erreur lors du chargement',
                    'refresh' => 'Actualiser',
                    'no_jobs' => 'Aucun traitement en cours'
                )
            ));
        }
    }
    
    /**
     * Vérifier si nous sommes sur une page avec dashboard
     * 
     * @since 1.0.0
     * @return bool
     */
    private function is_dashboard_page() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        $dashboard_shortcodes = array(
            'iris_process_page', 
            'iris_user_dashboard', 
            'user_token_balance',
            'token_history'
        );
        
        foreach ($dashboard_shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Afficher le solde de jetons avec design amélioré
     * 
     * @since 1.0.0
     * @param array $atts Attributs du shortcode
     * @return string HTML du solde
     */
    public function display_token_balance($atts) {
        if (!is_user_logged_in()) {
            return $this->render_login_required('pour voir votre solde de jetons');
        }
        
        $atts = shortcode_atts(array(
            'style' => 'card', // card, simple, inline
            'show_actions' => 'true'
        ), $atts);
        
        $user_id = get_current_user_id();
        $balance = Token_Manager::get_user_balance($user_id);
        $user_stats = $this->get_user_statistics($user_id);
        
        ob_start();
        ?>
        <div class="iris-token-balance iris-style-<?php echo esc_attr($atts['style']); ?>">
            <?php if ($atts['style'] === 'card'): ?>
                <div class="iris-balance-card">
                    <div class="iris-balance-header">
                        <h3>💎 Vos jetons Iris Process</h3>
                        <div class="iris-balance-refresh">
                            <button type="button" class="iris-refresh-btn" data-action="refresh-balance">
                                🔄 Actualiser
                            </button>
                        </div>
                    </div>
                    
                    <div class="iris-balance-content">
                        <div class="iris-balance-main">
                            <span class="iris-token-count" data-balance="<?php echo $balance; ?>">
                                <?php echo number_format($balance); ?>
                            </span>
                            <span class="iris-token-label">
                                jeton<?php echo $balance > 1 ? 's' : ''; ?> disponible<?php echo $balance > 1 ? 's' : ''; ?>
                            </span>
                        </div>
                        
                        <div class="iris-balance-stats">
                            <div class="iris-stat-item">
                                <span class="iris-stat-label">Total utilisé:</span>
                                <span class="iris-stat-value"><?php echo number_format($user_stats['total_used']); ?></span>
                            </div>
                            <div class="iris-stat-item">
                                <span class="iris-stat-label">Total acheté:</span>
                                <span class="iris-stat-value"><?php echo number_format($user_stats['total_purchased']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($atts['show_actions'] === 'true'): ?>
                        <div class="iris-balance-actions">
                            <?php if ($balance > 0): ?>
                                <a href="<?php echo home_url('/traitement-images/'); ?>" class="iris-btn iris-btn-primary">
                                    🎨 Traiter une image
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo home_url('/boutique/'); ?>" class="iris-btn iris-btn-secondary">
                                🛒 Acheter des jetons
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($atts['style'] === 'simple'): ?>
                <div class="iris-balance-simple">
                    <span class="iris-balance-text">
                        Jetons disponibles: <strong><?php echo $balance; ?></strong>
                    </span>
                </div>
            <?php else: // inline ?>
                <span class="iris-balance-inline">
                    <span class="iris-token-icon">💎</span>
                    <span class="iris-token-count"><?php echo $balance; ?></span>
                </span>
            <?php endif; ?>
        </div>
        
        <?php if ($balance === 0 && $atts['style'] === 'card'): ?>
            <div class="iris-no-tokens-notice">
                <p>🚨 Vous n'avez plus de jetons pour traiter vos images.</p>
                <a href="<?php echo home_url('/boutique/'); ?>" class="iris-btn iris-btn-accent">
                    Recharger maintenant
                </a>
            </div>
        <?php endif; ?>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Afficher l'historique des jetons avec pagination
     * 
     * @since 1.0.0
     * @param array $atts Attributs du shortcode
     * @return string HTML de l'historique
     */
    public function display_token_history($atts) {
        if (!is_user_logged_in()) {
            return $this->render_login_required('pour voir votre historique');
        }
        
        $atts = shortcode_atts(array(
            'limit' => 10,
            'show_pagination' => 'true',
            'show_filters' => 'true'
        ), $atts);
        
        $user_id = get_current_user_id();
        $limit = min(50, max(1, intval($atts['limit'])));
        $page = max(1, intval($_GET['history_page'] ?? 1));
        $offset = ($page - 1) * $limit;
        
        // Récupérer les transactions avec pagination
        $transactions = $this->get_user_transactions_paginated($user_id, $limit, $offset);
        $total_transactions = $this->count_user_transactions($user_id);
        $total_pages = ceil($total_transactions / $limit);
        
        if (empty($transactions)) {
            return '<div class="iris-no-history">
                        <p>📝 Aucune transaction trouvée.</p>
                        <p><a href="' . home_url('/boutique/') . '">Effectuez votre premier achat</a></p>
                    </div>';
        }
        
        ob_start();
        ?>
        <div class="iris-token-history">
            <div class="iris-history-header">
                <h4>📋 Historique des jetons</h4>
                <?php if ($atts['show_filters'] === 'true'): ?>
                    <div class="iris-history-filters">
                        <select id="iris-transaction-filter" data-nonce="<?php echo wp_create_nonce('iris_dashboard_nonce'); ?>">
                            <option value="all">Toutes les transactions</option>
                            <option value="purchase">Achats uniquement</option>
                            <option value="usage">Utilisations uniquement</option>
                            <option value="refund">Remboursements</option>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="iris-history-content">
                <table class="iris-transaction-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Jetons</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr class="iris-transaction-<?php echo esc_attr($transaction->transaction_type); ?>">
                            <td class="iris-transaction-date">
                                <?php echo date('d/m/Y H:i', strtotime($transaction->created_at)); ?>
                            </td>
                            <td class="iris-transaction-type">
                                <span class="iris-type-badge iris-type-<?php echo esc_attr($transaction->transaction_type); ?>">
                                    <?php echo $this->get_transaction_type_label($transaction->transaction_type); ?>
                                </span>
                            </td>
                            <td class="iris-transaction-amount">
                                <?php if ($transaction->tokens_amount > 0): ?>
                                    <span class="iris-amount-positive">+<?php echo $transaction->tokens_amount; ?></span>
                                <?php else: ?>
                                    <span class="iris-amount-negative"><?php echo $transaction->tokens_amount; ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="iris-transaction-description">
                                <?php echo esc_html($transaction->description); ?>
                                <?php if ($transaction->order_id): ?>
                                    <small class="iris-order-ref">
                                        (Commande: <?php echo esc_html($transaction->order_id); ?>)
                                    </small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($atts['show_pagination'] === 'true' && $total_pages > 1): ?>
                <div class="iris-history-pagination">
                    <?php
                    $base_url = remove_query_arg('history_page');
                    
                    if ($page > 1): ?>
                        <a href="<?php echo add_query_arg('history_page', $page - 1, $base_url); ?>" class="iris-page-btn iris-prev">
                            ← Précédent
                        </a>
                    <?php endif; ?>
                    
                    <span class="iris-page-info">
                        Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                        (<?php echo $total_transactions; ?> transaction<?php echo $total_transactions > 1 ? 's' : ''; ?>)
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo add_query_arg('history_page', $page + 1, $base_url); ?>" class="iris-page-btn iris-next">
                            Suivant →
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Afficher la page de traitement complète avec presets
     * 
     * @since 1.0.0
     * @param array $atts Attributs du shortcode
     * @return string HTML de la page
     */
    public function display_process_page($atts) {
        if (!is_user_logged_in()) {
            return $this->render_login_required('pour traiter vos images');
        }
        
        $user_id = get_current_user_id();
        $balance = Token_Manager::get_user_balance($user_id);
        
        if ($balance < 1) {
            return '<div class="iris-no-tokens-page">
                        <div class="iris-no-tokens-content">
                            <h3>💎 Plus de jetons disponibles</h3>
                            <p>Vous devez acheter des jetons pour traiter vos images.</p>
                            <a href="' . home_url('/boutique/') . '" class="iris-btn iris-btn-primary">
                                🛒 Acheter des jetons
                            </a>
                        </div>
                    </div>';
        }
        
        // Récupérer les presets disponibles
        $available_presets = array();
        if (class_exists('Preset_Manager')) {
            $available_presets = Preset_Manager::list_all();
        }
        
        ob_start();
        ?>
        <div class="iris-process-page">
            <div class="iris-process-header">
                <h2>🎨 Traitement d'image Iris Process</h2>
                <div class="iris-process-info">
                    <div class="iris-token-info-compact">
                        💎 <strong><?php echo $balance; ?></strong> jeton<?php echo $balance > 1 ? 's' : ''; ?> disponible<?php echo $balance > 1 ? 's' : ''; ?>
                    </div>
                    <div class="iris-process-help">
                        <button type="button" class="iris-help-btn" data-toggle="help">
                            ❓ Aide
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="iris-help-panel" id="iris-help-panel" style="display: none;">
                <div class="iris-help-content">
                    <h4>💡 Comment utiliser le traitement d'images ?</h4>
                    <ol>
                        <li><strong>Choisissez un preset</strong> pour appliquer des réglages spécifiques (optionnel)</li>
                        <li><strong>Sélectionnez votre image</strong> dans un format supporté</li>
                        <li><strong>Lancez le traitement</strong> - 1 jeton sera consommé</li>
                        <li><strong>Téléchargez le résultat</strong> une fois le traitement terminé</li>
                    </ol>
                    <p><strong>Formats supportés:</strong> CR3, CR2, NEF, ARW, RAW, DNG, ORF, RAF, RW2, JPG, TIF, PNG</p>
                </div>
            </div>
            
            <!-- Sélection du preset -->
            <?php if (!empty($available_presets)): ?>
            <div class="iris-preset-selection">
                <h4>🎛️ Choisir un preset de traitement</h4>
                <div class="iris-preset-grid">
                    <label class="iris-preset-option">
                        <input type="radio" name="iris_preset" value="" checked>
                        <div class="iris-preset-card iris-preset-default">
                            <div class="iris-preset-name">Traitement par défaut</div>
                            <div class="iris-preset-description">Réglages automatiques optimisés</div>
                        </div>
                    </label>
                    
                    <?php foreach ($available_presets as $preset): ?>
                    <label class="iris-preset-option">
                        <input type="radio" name="iris_preset" value="<?php echo esc_attr($preset->id); ?>">
                        <div class="iris-preset-card">
                            <div class="iris-preset-name">
                                <?php echo esc_html($preset->preset_name); ?>
                                <?php if ($preset->is_default): ?>
                                    <span class="iris-preset-badge">⭐ Recommandé</span>
                                <?php endif; ?>
                            </div>
                            <div class="iris-preset-description">
                                <?php echo esc_html($preset->description ?: 'Preset personnalisé'); ?>
                            </div>
                            <div class="iris-preset-stats">
                                📊 Utilisé <?php echo $preset->usage_count; ?> fois
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Zone d'upload -->
            <div class="iris-upload-section">
                <form id="iris-upload-form" enctype="multipart/form-data">
                    <?php wp_nonce_field('iris_upload_nonce', 'nonce'); ?>
                    
                    <div class="iris-upload-area">
                        <div class="iris-drop-zone" id="iris-drop-zone">
                            <div class="iris-upload-icon">📤</div>
                            <h4>Glissez votre image ici ou cliquez pour sélectionner</h4>
                            <p>Formats acceptés : CR3, CR2, NEF, ARW, RAW, DNG, ORF, RAF, RW2, JPG, TIF, PNG</p>
                            <p>Taille maximum : <?php echo size_format(wp_max_upload_size()); ?></p>
                            
                            <input type="file" 
                                   id="iris-image-input" 
                                   name="image_file" 
                                   accept=".cr3,.cr2,.nef,.arw,.jpg,.jpeg,.tif,.tiff,.raw,.dng,.orf,.raf,.rw2,.png" 
                                   required>
                        </div>
                        
                        <div id="iris-file-preview" style="display: none;">
                            <div class="iris-file-info">
                                <div class="iris-file-details">
                                    <div class="iris-file-name" id="iris-file-name"></div>
                                    <div class="iris-file-size" id="iris-file-size"></div>
                                </div>
                                <button type="button" id="iris-remove-file" class="iris-remove-btn">
                                    ❌ Supprimer
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="iris-process-actions">
                        <button type="submit" id="iris-process-btn" class="iris-btn iris-btn-primary" disabled>
                            <span class="iris-btn-text">🚀 Traiter l'image (1 jeton)</span>
                            <span class="iris-btn-loading" style="display: none;">⏳ Traitement en cours...</span>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Zone de progression -->
            <div id="iris-progress" class="iris-progress-section" style="display: none;">
                <div class="iris-progress-header">
                    <h4>⚡ Traitement en cours...</h4>
                    <div class="iris-progress-timer" id="iris-progress-timer">00:00</div>
                </div>
                <div class="iris-progress-bar">
                    <div class="iris-progress-fill" id="iris-progress-fill"></div>
                </div>
                <div class="iris-progress-text" id="iris-progress-text">Initialisation...</div>
            </div>
            
            <!-- Zone de résultat -->
            <div id="iris-result" class="iris-result-section" style="display: none;">
                <!-- Le résultat sera injecté ici via JavaScript -->
            </div>
            
            <!-- Historique récent -->
            <div class="iris-recent-jobs">
                <h4>📋 Traitements récents</h4>
                <div id="iris-recent-list">
                    <?php echo $this->get_recent_jobs_html($user_id, 5); ?>
                </div>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Afficher le dashboard utilisateur complet
     * 
     * @since 1.0.0
     * @param array $atts Attributs du shortcode
     * @return string HTML du dashboard
     */
    public function display_full_dashboard($atts) {
        if (!is_user_logged_in()) {
            return $this->render_login_required('pour accéder à votre dashboard');
        }
        
        $atts = shortcode_atts(array(
            'layout' => 'grid' // grid, list
        ), $atts);
        
        $user_id = get_current_user_id();
        $user_info = get_userdata($user_id);
        
        ob_start();
        ?>
        <div class="iris-user-dashboard iris-layout-<?php echo esc_attr($atts['layout']); ?>">
            <div class="iris-dashboard-header">
                <h2>👋 Bonjour <?php echo esc_html($user_info->display_name); ?></h2>
                <p class="iris-dashboard-subtitle">Gérez vos jetons et traitements d'images</p>
            </div>
            
            <div class="iris-dashboard-grid">
                <!-- Widget solde -->
                <div class="iris-dashboard-widget">
                    <?php echo $this->display_token_balance(array('style' => 'card')); ?>
                </div>
                
                <!-- Widget statistiques -->
                <div class="iris-dashboard-widget">
                    <?php echo $this->display_user_stats(); ?>
                </div>
                
                <!-- Widget accès rapide -->
                <div class="iris-dashboard-widget">
                    <div class="iris-quick-actions">
                        <h4>🚀 Actions rapides</h4>
                        <div class="iris-action-buttons">
                            <a href="<?php echo home_url('/traitement-images/'); ?>" class="iris-action-btn iris-primary">
                                🎨 Traiter une image
                            </a>
                            <a href="<?php echo home_url('/boutique/'); ?>" class="iris-action-btn iris-secondary">
                                🛒 Acheter des jetons
                            </a>
                            <a href="<?php echo home_url('/historique/'); ?>" class="iris-action-btn iris-tertiary">
                                📋 Voir l'historique
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Widget jobs en cours -->
                <div class="iris-dashboard-widget iris-widget-full">
                    <div class="iris-active-jobs">
                        <h4>⚡ Traitements en cours</h4>
                        <div id="iris-active-jobs-list">
                            <?php echo $this->get_active_jobs_html($user_id); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Afficher les statistiques utilisateur
     * 
     * @since 1.0.0
     * @param array $atts Attributs du shortcode
     * @return string HTML des statistiques
     */
    public function display_user_stats($atts = array()) {
        if (!is_user_logged_in()) {
            return '';
        }
        
        $user_id = get_current_user_id();
        $stats = $this->get_user_statistics($user_id);
        
        ob_start();
        ?>
        <div class="iris-user-stats">
            <h4>📊 Vos statistiques</h4>
            <div class="iris-stats-grid">
                <div class="iris-stat-card">
                    <div class="iris-stat-icon">💎</div>
                    <div class="iris-stat-content">
                        <div class="iris-stat-number"><?php echo number_format($stats['token_balance']); ?></div>
                        <div class="iris-stat-label">Jetons disponibles</div>
                    </div>
                </div>
                
                <div class="iris-stat-card">
                    <div class="iris-stat-icon">✅</div>
                    <div class="iris-stat-content">
                        <div class="iris-stat-number"><?php echo number_format($stats['completed_jobs']); ?></div>
                        <div class="iris-stat-label">Images traitées</div>
                    </div>
                </div>
                
                <div class="iris-stat-card">
                    <div class="iris-stat-icon">🛒</div>
                    <div class="iris-stat-content">
                        <div class="iris-stat-number"><?php echo number_format($stats['total_purchased']); ?></div>
                        <div class="iris-stat-label">Jetons achetés</div>
                    </div>
                </div>
                
                <div class="iris-stat-card">
                    <div class="iris-stat-icon">⏱️</div>
                    <div class="iris-stat-content">
                        <div class="iris-stat-number"><?php echo $stats['avg_processing_time']; ?>min</div>
                        <div class="iris-stat-label">Temps moyen</div>
                    </div>
                </div>
            </div>
            
            <?php if ($stats['last_activity']): ?>
                <div class="iris-last-activity">
                    <small>
                        🕒 Dernière activité: <?php echo human_time_diff(strtotime($stats['last_activity'])); ?> ago
                    </small>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Récupérer les statistiques détaillées d'un utilisateur
     * 
     * @since 1.0.0
     * @param int $user_id ID de l'utilisateur
     * @return array Statistiques
     */
    private function get_user_statistics($user_id) {
        global $wpdb;
        
        $stats = array(
            'token_balance' => 0,
            'total_purchased' => 0,
            'total_used' => 0,
            'completed_jobs' => 0,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'avg_processing_time' => 0,
            'last_activity' => null
        );
        
        try {
            // Statistiques des jetons
            $token_stats = $wpdb->get_row($wpdb->prepare(
                "SELECT token_balance, total_purchased, total_used, updated_at 
                 FROM {$wpdb->prefix}iris_user_tokens 
                 WHERE user_id = %d",
                $user_id
            ));
            
            if ($token_stats) {
                $stats['token_balance'] = intval($token_stats->token_balance);
                $stats['total_purchased'] = intval($token_stats->total_purchased);
                $stats['total_used'] = intval($token_stats->total_used);
                $stats['last_activity'] = $token_stats->updated_at;
            }
            
            // Statistiques des jobs
            $job_stats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_jobs,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status IN ('pending', 'processing') THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    AVG(CASE WHEN status = 'completed' AND completed_at IS NOT NULL 
                        THEN TIMESTAMPDIFF(MINUTE, created_at, completed_at) END) as avg_time
                 FROM {$wpdb->prefix}iris_processing_jobs 
                 WHERE user_id = %d",
                $user_id
            ));
            
            if ($job_stats) {
                $stats['completed_jobs'] = intval($job_stats->completed);
                $stats['pending_jobs'] = intval($job_stats->pending);
                $stats['failed_jobs'] = intval($job_stats->failed);
                $stats['avg_processing_time'] = $job_stats->avg_time ? round(floatval($job_stats->avg_time), 1) : 0;
            }
            
        } catch (Exception $e) {
            iris_log_error('Erreur get_user_statistics: ' . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Récupérer les transactions avec pagination
     * 
     * @since 1.0.0
     * @param int $user_id ID de l'utilisateur
     * @param int $limit Limite de résultats
     * @param int $offset Décalage
     * @return array Transactions
     */
    private function get_user_transactions_paginated($user_id, $limit, $offset) {
        global $wpdb;
        $table = $wpdb->prefix . 'iris_token_transactions';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ));
    }
    
    /**
     * Compter le nombre total de transactions d'un utilisateur
     * 
     * @since 1.0.0
     * @param int $user_id ID de l'utilisateur
     * @return int Nombre de transactions
     */
    private function count_user_transactions($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'iris_token_transactions';
        
        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d",
            $user_id
        )));
    }
    
    /**
     * Obtenir le label d'un type de transaction
     * 
     * @since 1.0.0
     * @param string $type Type de transaction
     * @return string Label localisé
     */
    private function get_transaction_type_label($type) {
        $labels = array(
            'purchase' => '🛒 Achat',
            'usage' => '🎨 Utilisation',
            'refund' => '↩️ Remboursement',
            'bonus' => '🎁 Bonus',
            'admin_adjustment' => '⚙️ Ajustement'
        );
        
        return $labels[$type] ?? ucfirst($type);
    }
    
    /**
     * Récupérer les jobs récents HTML
     * 
     * @since 1.0.0
     * @param int $user_id ID de l'utilisateur
     * @param int $limit Nombre de jobs à afficher
     * @return string HTML des jobs récents
     */
    private function get_recent_jobs_html($user_id, $limit = 5) {
        global $wpdb;
        
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        $table_presets = $wpdb->prefix . 'iris_admin_presets';
        
        $jobs = $wpdb->get_results($wpdb->prepare(
            "SELECT j.*, p.preset_name 
             FROM $table_jobs j 
             LEFT JOIN $table_presets p ON j.preset_id = p.id
             WHERE j.user_id = %d 
             ORDER BY j.created_at DESC 
             LIMIT %d",
            $user_id, $limit
        ));
        
        if (empty($jobs)) {
            return '<div class="iris-no-recent-jobs">
                        <p>📭 Aucun traitement récent</p>
                        <p><a href="' . home_url('/traitement-images/') . '">Traitez votre première image</a></p>
                    </div>';
        }
        
        $html = '<div class="iris-recent-jobs-list">';
        
        foreach ($jobs as $job) {
            $status_icon = $this->get_status_icon($job->status);
            $status_class = 'iris-status-' . $job->status;
            
            $html .= '<div class="iris-job-item ' . $status_class . '">';
            $html .= '<div class="iris-job-info">';
            $html .= '<div class="iris-job-file">' . esc_html($job->original_file) . '</div>';
            
            if ($job->preset_name) {
                $html .= '<div class="iris-job-preset">🎨 ' . esc_html($job->preset_name) . '</div>';
            }
            
            $html .= '<div class="iris-job-meta">';
            $html .= '<span class="iris-job-date">' . human_time_diff(strtotime($job->created_at)) . ' ago</span>';
            $html .= '</div>';
            $html .= '</div>';
            
            $html .= '<div class="iris-job-status">';
            $html .= '<span class="iris-status-badge">' . $status_icon . ' ' . $this->get_status_text($job->status) . '</span>';
            
            if ($job->status === 'completed' && $job->result_files) {
                $html .= '<div class="iris-job-actions">';
                $html .= '<a href="' . wp_nonce_url(home_url('/wp-json/iris/v1/download/' . $job->job_id . '/result.tiff'), 'iris_download_' . $job->job_id) . '" class="iris-download-btn">⬇️ Télécharger</a>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Récupérer les jobs actifs HTML
     * 
     * @since 1.0.0
     * @param int $user_id ID de l'utilisateur
     * @return string HTML des jobs actifs
     */
    private function get_active_jobs_html($user_id) {
        global $wpdb;
        
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        
        $active_jobs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_jobs 
             WHERE user_id = %d AND status IN ('pending', 'processing') 
             ORDER BY created_at DESC",
            $user_id
        ));
        
        if (empty($active_jobs)) {
            return '<div class="iris-no-active-jobs">
                        <p>😴 Aucun traitement en cours</p>
                    </div>';
        }
        
        $html = '<div class="iris-active-jobs-list">';
        
        foreach ($active_jobs as $job) {
            $progress = $job->status === 'processing' ? 50 : 10; // Estimation
            
            $html .= '<div class="iris-active-job" data-job-id="' . esc_attr($job->job_id) . '">';
            $html .= '<div class="iris-active-job-info">';
            $html .= '<div class="iris-active-job-file">' . esc_html($job->original_file) . '</div>';
            $html .= '<div class="iris-active-job-time">Démarré ' . human_time_diff(strtotime($job->created_at)) . ' ago</div>';
            $html .= '</div>';
            
            $html .= '<div class="iris-active-job-progress">';
            $html .= '<div class="iris-progress-bar-mini">';
            $html .= '<div class="iris-progress-fill-mini" style="width: ' . $progress . '%"></div>';
            $html .= '</div>';
            $html .= '<div class="iris-progress-text-mini">' . $this->get_status_text($job->status) . '</div>';
            $html .= '</div>';
            
            $html .= '<div class="iris-active-job-actions">';
            $html .= '<button type="button" class="iris-cancel-job-btn" data-job-id="' . esc_attr($job->job_id) . '">❌ Annuler</button>';
            $html .= '</div>';
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Obtenir l'icône de statut
     * 
     * @since 1.0.0
     * @param string $status Statut du job
     * @return string Icône
     */
    private function get_status_icon($status) {
        $icons = array(
            'pending' => '⏳',
            'processing' => '⚡',
            'completed' => '✅',
            'failed' => '❌',
            'cancelled' => '🚫'
        );
        
        return $icons[$status] ?? '❓';
    }
    
    /**
     * Obtenir le texte de statut
     * 
     * @since 1.0.0
     * @param string $status Statut du job
     * @return string Texte localisé
     */
    private function get_status_text($status) {
        $texts = array(
            'pending' => 'En attente',
            'processing' => 'En cours',
            'completed' => 'Terminé',
            'failed' => 'Échoué',
            'cancelled' => 'Annulé'
        );
        
        return $texts[$status] ?? ucfirst($status);
    }
    
    /**
     * Afficher un message de connexion requise
     * 
     * @since 1.0.0
     * @param string $context Contexte d'utilisation
     * @return string HTML du message
     */
    private function render_login_required($context = '') {
        $login_url = wp_login_url(get_permalink());
        
        return '<div class="iris-login-required">
                    <div class="iris-login-content">
                        <h3>🔐 Connexion requise</h3>
                        <p>Vous devez être connecté ' . esc_html($context) . '.</p>
                        <div class="iris-login-actions">
                            <a href="' . esc_url($login_url) . '" class="iris-btn iris-btn-primary">
                                👤 Se connecter
                            </a>
                            <a href="' . esc_url(wp_registration_url()) . '" class="iris-btn iris-btn-secondary">
                                📝 S\'inscrire
                            </a>
                        </div>
                    </div>
                </div>';
    }
    
    /**
     * Handler AJAX pour actualiser le dashboard
     * 
     * @since 1.0.0
     * @return void
     */
    public function ajax_refresh_dashboard() {
        try {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iris_dashboard_nonce')) {
                wp_send_json_error('Erreur de sécurité');
                return;
            }
            
            $user_id = get_current_user_id();
            if (!$user_id) {
                wp_send_json_error('Utilisateur non connecté');
                return;
            }
            
            $component = sanitize_text_field($_POST['component'] ?? '');
            
            $data = array();
            
            switch ($component) {
                case 'balance':
                    $data['balance'] = Token_Manager::get_user_balance($user_id);
                    break;
                    
                case 'stats':
                    $data['stats'] = $this->get_user_statistics($user_id);
                    break;
                    
                case 'recent_jobs':
                    $data['html'] = $this->get_recent_jobs_html($user_id, 5);
                    break;
                    
                case 'active_jobs':
                    $data['html'] = $this->get_active_jobs_html($user_id);
                    break;
                    
                default:
                    // Actualiser tout
                    $data['balance'] = Token_Manager::get_user_balance($user_id);
                    $data['stats'] = $this->get_user_statistics($user_id);
                    $data['recent_jobs_html'] = $this->get_recent_jobs_html($user_id, 5);
                    $data['active_jobs_html'] = $this->get_active_jobs_html($user_id);
                    break;
            }
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            iris_log_error('Erreur ajax_refresh_dashboard: ' . $e->getMessage());
            wp_send_json_error('Erreur lors de l\'actualisation');
        }
    }
    
    /**
     * Handler AJAX pour récupérer le progrès d'un job
     * 
     * @since 1.0.0
     * @return void
     */
    public function ajax_get_job_progress() {
        try {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iris_dashboard_nonce')) {
                wp_send_json_error('Erreur de sécurité');
                return;
            }
            
            $user_id = get_current_user_id();
            if (!$user_id) {
                wp_send_json_error('Utilisateur non connecté');
                return;
            }
            
            $job_id = sanitize_text_field($_POST['job_id'] ?? '');
            if (empty($job_id)) {
                wp_send_json_error('ID de job manquant');
                return;
            }
            
            global $wpdb;
            $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
            
            $job = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_jobs WHERE job_id = %s AND user_id = %d",
                $job_id, $user_id
            ));
            
            if (!$job) {
                wp_send_json_error('Job non trouvé');
                return;
            }
            
            // Estimation du progrès basée sur le statut et le temps écoulé
            $progress = 0;
            $elapsed_minutes = (time() - strtotime($job->created_at)) / 60;
            
            switch ($job->status) {
                case 'pending':
                    $progress = min(10, $elapsed_minutes * 2);
                    break;
                case 'processing':
                    $progress = min(90, 10 + ($elapsed_minutes * 8));
                    break;
                case 'completed':
                    $progress = 100;
                    break;
                case 'failed':
                case 'cancelled':
                    $progress = 0;
                    break;
            }
            
            wp_send_json_success(array(
                'job_id' => $job->job_id,
                'status' => $job->status,
                'progress' => round($progress),
                'status_text' => $this->get_status_text($job->status),
                'elapsed_time' => $elapsed_minutes,
                'is_finished' => in_array($job->status, array('completed', 'failed', 'cancelled'))
            ));
            
        } catch (Exception $e) {
            iris_log_error('Erreur ajax_get_job_progress: ' . $e->getMessage());
            wp_send_json_error('Erreur lors de la récupération du progrès');
        }
    }
}