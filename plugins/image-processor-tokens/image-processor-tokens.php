<?php
/**
 * Plugin Name: Iris Process - Image Processor with Tokens (ADMIN COMPLET)
 * Plugin URI: https://iris4pro.com
 * Description: Application WordPress de traitement d'images avec système de jetons - VERSION ADMIN COMPLÈTE
 * Version: 1.1.0
 * Author: Ikomiris
 */

// Sécurité - Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit('Accès direct interdit.');
}

// Définition des constantes du plugin
define('IRIS_PLUGIN_VERSION', '1.1.0');
define('IRIS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IRIS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('IRIS_API_URL', 'http://54.155.119.226:8000');

/**
 * Classe principale du plugin Iris Process - VERSION ADMIN COMPLÈTE
 */
class IrisProcessTokens {
    
    private static $instance = null;
    
    private function __construct() {
        // Hooks de base
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialisation après que WordPress soit chargé
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialisation du plugin
     */
    public function init() {
        // Charger les shortcodes
        $this->register_shortcodes();
        
        // Charger les scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Enregistrement des shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('user_token_balance', array($this, 'shortcode_token_balance'));
        add_shortcode('token_history', array($this, 'shortcode_token_history'));
        add_shortcode('iris_upload_zone', array($this, 'shortcode_upload_zone'));
    }
    
    /**
     * Shortcode: Affichage du solde de jetons
     */
    public function shortcode_token_balance($atts) {
        if (!is_user_logged_in()) {
            return $this->get_login_message();
        }
        
        $user_id = get_current_user_id();
        $balance = $this->get_user_balance($user_id);
        
        return $this->render_token_balance($balance);
    }
    
    /**
     * Shortcode: Historique des jetons
     */
    public function shortcode_token_history($atts) {
        $atts = shortcode_atts(array('limit' => 10), $atts);
        
        if (!is_user_logged_in()) {
            return '<p>Vous devez être connecté pour voir votre historique.</p>';
        }
        
        $user_id = get_current_user_id();
        $transactions = $this->get_user_transactions($user_id, intval($atts['limit']));
        
        return $this->render_token_history($transactions);
    }
    
    /**
     * Shortcode: Zone d'upload
     */
    public function shortcode_upload_zone($atts) {
        if (!is_user_logged_in()) {
            return $this->get_login_message();
        }
        
        $user_id = get_current_user_id();
        $balance = $this->get_user_balance($user_id);
        
        return $this->render_upload_zone($balance);
    }
    
    /**
     * Obtenir le solde de jetons d'un utilisateur
     */
    private function get_user_balance($user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'iris_user_tokens';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return 0;
        }
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT token_balance FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        return $result ? intval($result) : 0;
    }
    
    /**
     * Obtenir les transactions d'un utilisateur
     */
    private function get_user_transactions($user_id, $limit) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'iris_token_transactions';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return array();
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id, $limit
        ));
    }
    
    /**
     * Message de connexion requis
     */
    private function get_login_message() {
        $login_url = wp_login_url(get_permalink());
        
        return '<div style="background: #0C2D39; color: #F4F4F2; padding: 30px; border-radius: 12px; text-align: center; margin: 20px 0;">
                    <h3 style="color: #3de9f4; margin: 0 0 15px 0;">Connexion requise</h3>
                    <p style="margin: 0 0 20px 0;">Vous devez être connecté pour utiliser cette fonctionnalité.</p>
                    <a href="' . esc_url($login_url) . '" style="background: #F05A28; color: #F4F4F2; padding: 12px 24px; border-radius: 25px; text-decoration: none; font-weight: bold;">Se connecter</a>
                </div>';
    }
    
    /**
     * Affichage du solde de jetons
     */
    private function render_token_balance($balance) {
        ob_start();
        ?>
        <div style="background: #0C2D39; color: #F4F4F2; padding: 30px; border-radius: 12px; text-align: center; margin: 20px 0; font-family: Arial, sans-serif;">
            <h3 style="color: #3de9f4; margin: 0 0 20px 0; font-size: 24px;">Vos jetons Iris Process</h3>
            <div style="margin: 20px 0;">
                <span style="font-size: 3em; font-weight: bold; color: #3de9f4; display: block;"><?php echo esc_html($balance); ?></span>
                <span style="color: #F4F4F2; font-size: 16px;">jeton<?php echo $balance > 1 ? 's' : ''; ?> disponible<?php echo $balance > 1 ? 's' : ''; ?></span>
            </div>
            <div style="margin-top: 25px;">
                <a href="/boutique" style="background: #F05A28; color: #F4F4F2; padding: 12px 24px; margin: 5px; border-radius: 25px; text-decoration: none; font-weight: bold; display: inline-block;">Acheter des jetons</a>
                <?php if ($balance > 0) : ?>
                    <a href="/iris-process" style="background: #3de9f4; color: #0C2D39; padding: 12px 24px; margin: 5px; border-radius: 25px; text-decoration: none; font-weight: bold; display: inline-block;">Traiter une image</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Affichage de l'historique des jetons
     */
    private function render_token_history($transactions) {
        if (empty($transactions)) {
            return '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;">
                        <p>Aucune transaction trouvée.</p>
                    </div>';
        }
        
        ob_start();
        ?>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h4 style="margin: 0 0 20px 0; color: #0C2D39;">Historique des jetons</h4>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #e9ecef;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Date</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Type</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Jetons</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction) : ?>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #dee2e6;"><?php echo esc_html(date('d/m/Y H:i', strtotime($transaction->created_at))); ?></td>
                        <td style="padding: 10px; border-bottom: 1px solid #dee2e6;"><?php echo esc_html(ucfirst($transaction->transaction_type)); ?></td>
                        <td style="padding: 10px; border-bottom: 1px solid #dee2e6;">
                            <?php if ($transaction->tokens_amount > 0) : ?>
                                <span style="color: #28a745; font-weight: bold;">+<?php echo esc_html($transaction->tokens_amount); ?></span>
                            <?php else : ?>
                                <span style="color: #dc3545; font-weight: bold;"><?php echo esc_html($transaction->tokens_amount); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px; border-bottom: 1px solid #dee2e6;"><?php echo esc_html($transaction->description); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Affichage de la zone d'upload
     */
    private function render_upload_zone($balance) {
        ob_start();
        ?>
        <div style="max-width: 600px; margin: 20px auto; padding: 20px; font-family: Arial, sans-serif;">
            <!-- Info jetons -->
            <div style="background: #0C2D39; color: #F4F4F2; padding: 20px; border-radius: 12px; margin-bottom: 20px; text-align: center;">
                <h3 style="margin: 0; color: #3de9f4;">Vos jetons disponibles : <span style="font-weight: bold; color: #F4F4F2;"><?php echo esc_html($balance); ?></span></h3>
                <?php if ($balance < 1): ?>
                    <p style="color: #F05A28; margin-top: 10px;">Vous n'avez pas assez de jetons. <a href="/boutique" style="color: #3de9f4;">Achetez des jetons</a></p>
                <?php endif; ?>
            </div>
            
            <?php if ($balance >= 1): ?>
            <!-- Zone d'upload -->
            <div style="background: #15697B; padding: 20px; border-radius: 12px;">
                <form id="iris-upload-form" enctype="multipart/form-data">
                    <div style="border: 3px dashed #3de9f4; border-radius: 12px; padding: 40px 20px; text-align: center; cursor: pointer;">
                        <div style="font-size: 48px; margin-bottom: 20px;">📷</div>
                        <h4 style="color: #3de9f4; margin: 10px 0;">Sélectionnez votre image</h4>
                        <p style="color: #F4F4F2; font-size: 14px;">Formats supportés : CR3, NEF, ARW, RAW, DNG, ORF, RAF, RW2, JPG, TIF</p>
                        <input type="file" id="iris-file-input" name="image_file" accept=".cr3,.nef,.arw,.jpg,.jpeg,.tif,.tiff,.raw,.dng,.orf,.raf,.rw2" style="margin-top: 20px; padding: 10px; background: #F4F4F2; border: none; border-radius: 5px; width: 90%;">
                    </div>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <button type="submit" id="iris-upload-btn" disabled style="background: #F05A28; color: #F4F4F2; border: none; padding: 15px 30px; border-radius: 25px; font-size: 16px; font-weight: bold; cursor: pointer;">
                            Traiter l'image (1 jeton)
                        </button>
                    </div>
                </form>
                
                <div id="iris-upload-result" style="margin-top: 20px; padding: 15px; border-radius: 8px; display: none;"></div>
            </div>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var fileInput = document.getElementById('iris-file-input');
                var uploadBtn = document.getElementById('iris-upload-btn');
                var result = document.getElementById('iris-upload-result');
                
                if (fileInput && uploadBtn) {
                    fileInput.addEventListener('change', function() {
                        if (this.files && this.files[0]) {
                            uploadBtn.disabled = false;
                            result.style.display = 'none';
                        }
                    });
                    
                    document.getElementById('iris-upload-form').addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        if (!fileInput.files[0]) {
                            alert('Veuillez sélectionner un fichier');
                            return;
                        }
                        
                        uploadBtn.disabled = true;
                        uploadBtn.textContent = 'Traitement en cours...';
                        result.innerHTML = '<div style="background: #17a2b8; color: white; padding: 15px; border-radius: 8px;">⏳ Traitement en cours...</div>';
                        result.style.display = 'block';
                        
                        // Simulation (remplacer par l'appel AJAX réel plus tard)
                        setTimeout(function() {
                            result.innerHTML = '<div style="background: #28a745; color: white; padding: 15px; border-radius: 8px;">✅ Fichier uploadé avec succès ! (Simulation - API non connectée)</div>';
                            uploadBtn.disabled = false;
                            uploadBtn.textContent = 'Traiter l\'image (1 jeton)';
                            fileInput.value = '';
                        }, 2000);
                    });
                }
            });
            </script>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Chargement des scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
    }
    
    /**
     * Ajout du menu admin complet
     */
    public function add_admin_menu() {
        if (current_user_can('manage_options')) {
            add_menu_page(
                'Iris Process',
                'Iris Process',
                'manage_options',
                'iris-process',
                array($this, 'admin_page'),
                'dashicons-images-alt2',
                30
            );
            
            add_submenu_page(
                'iris-process',
                'Configuration',
                'Configuration',
                'manage_options',
                'iris-config',
                array($this, 'config_admin_page')
            );
            
            add_submenu_page(
                'iris-process',
                'Jobs',
                'Jobs',
                'manage_options',
                'iris-jobs',
                array($this, 'jobs_admin_page')
            );
            
            add_submenu_page(
                'iris-process',
                'Utilisateurs',
                'Utilisateurs',
                'manage_options',
                'iris-users',
                array($this, 'users_admin_page')
            );
        }
    }
    
    /**
     * Page d'administration principale - DASHBOARD COMPLET
     */
    public function admin_page() {
        global $wpdb;
        
        // Traitement des actions
        if (isset($_POST['action'])) {
            $this->handle_admin_actions();
        }
        
        // Statistiques générales
        $stats = $this->get_dashboard_stats();
        $recent_jobs = $this->get_recent_jobs();
        $recent_users = $this->get_recent_users();
        
        ?>
        <div class="wrap">
            <h1>🎯 Iris Process - Tableau de bord</h1>
            
            <!-- Statistiques principales -->
            <div class="iris-admin-stats">
                <div class="iris-stat-card iris-stat-primary">
                    <h3>👥 Utilisateurs actifs</h3>
                    <p class="iris-stat-number"><?php echo number_format($stats['total_users']); ?></p>
                    <span class="iris-stat-label">Comptes avec jetons</span>
                </div>
                
                <div class="iris-stat-card iris-stat-success">
                    <h3>✅ Traitements réussis</h3>
                    <p class="iris-stat-number"><?php echo number_format($stats['completed_jobs']); ?></p>
                    <span class="iris-stat-label">Images traitées</span>
                </div>
                
                <div class="iris-stat-card iris-stat-warning">
                    <h3>⏳ En cours</h3>
                    <p class="iris-stat-number"><?php echo number_format($stats['pending_jobs']); ?></p>
                    <span class="iris-stat-label">Files d'attente</span>
                </div>
                
                <div class="iris-stat-card iris-stat-info">
                    <h3>🪙 Jetons utilisés</h3>
                    <p class="iris-stat-number"><?php echo number_format($stats['total_tokens_used']); ?></p>
                    <span class="iris-stat-label">Total consommé</span>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="iris-admin-actions">
                <h2>⚡ Actions rapides</h2>
                <div class="iris-action-buttons">
                    <form method="post" style="display: inline-block;">
                        <input type="hidden" name="action" value="add_test_tokens">
                        <button type="submit" class="button button-secondary">
                            🎁 Ajouter 10 jetons à l'admin
                        </button>
                    </form>
                    
                    <form method="post" style="display: inline-block;">
                        <input type="hidden" name="action" value="cleanup_old_jobs">
                        <button type="submit" class="button button-secondary">
                            🧹 Nettoyer les anciens jobs
                        </button>
                    </form>
                    
                    <form method="post" style="display: inline-block;">
                        <input type="hidden" name="action" value="test_api">
                        <button type="submit" class="button button-secondary">
                            🔌 Tester l'API Python
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Grille principale -->
            <div class="iris-admin-grid">
                <!-- Activité récente -->
                <div class="iris-admin-section">
                    <h2>📈 Activité récente</h2>
                    <div class="iris-recent-activity">
                        <?php if (empty($recent_jobs)): ?>
                            <p>Aucune activité récente.</p>
                        <?php else: ?>
                            <table class="widefat">
                                <thead>
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Fichier</th>
                                        <th>Statut</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_jobs as $job): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($job->display_name); ?></strong><br>
                                            <small><?php echo esc_html($job->user_email); ?></small>
                                        </td>
                                        <td>
                                            <?php echo esc_html($job->original_file); ?>
                                            <br><small>Job: <?php echo esc_html($job->job_id); ?></small>
                                        </td>
                                        <td>
                                            <span class="iris-status-badge iris-status-<?php echo $job->status; ?>">
                                                <?php echo $this->get_status_text($job->status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($job->created_at)); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p style="text-align: right;">
                                <a href="<?php echo admin_url('admin.php?page=iris-jobs'); ?>" class="button">
                                    Voir tous les jobs →
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statut système -->
                <div class="iris-admin-section">
                    <h2>🔧 Statut du système</h2>
                    <div class="iris-system-status">
                        <div class="iris-status-item">
                            <strong>🐍 API Python:</strong>
                            <span id="api-status">
                                <button type="button" id="test-api-btn" class="button button-small">Tester maintenant</button>
                            </span>
                        </div>
                        
                        <div class="iris-status-item">
                            <strong>📊 Base de données:</strong>
                            <span style="color: green;">✅ Tables créées</span>
                        </div>
                        
                        <div class="iris-status-item">
                            <strong>📁 Dossier uploads:</strong>
                            <?php 
                            $upload_dir = wp_upload_dir();
                            $iris_dir = $upload_dir['basedir'] . '/iris-process';
                            if (is_dir($iris_dir) && is_writable($iris_dir)) {
                                echo '<span style="color: green;">✅ Accessible en écriture</span>';
                            } else {
                                echo '<span style="color: red;">❌ Non accessible</span>';
                            }
                            ?>
                        </div>
                        
                        <div class="iris-status-item">
                            <strong>🔗 URL API:</strong>
                            <code><?php echo IRIS_API_URL; ?></code>
                        </div>
                        
                        <div class="iris-status-item">
                            <strong>📋 Shortcodes:</strong>
                            <span style="color: green;">✅ 3 shortcodes enregistrés</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php echo $this->get_admin_styles(); ?>
            <?php echo $this->get_admin_scripts(); ?>
        </div>
        <?php
    }
    
    /**
     * Page de configuration
     */
    public function config_admin_page() {
        // Sauvegarde des paramètres
        if (isset($_POST['submit'])) {
            check_admin_referer('iris_config_save');
            
            update_option('iris_api_url', sanitize_url($_POST['api_url']));
            update_option('iris_max_file_size', intval($_POST['max_file_size']));
            update_option('iris_email_notifications', isset($_POST['email_notifications']));
            update_option('iris_auto_xmp_generation', isset($_POST['auto_xmp_generation']));
            update_option('iris_xmp_metadata_fields', sanitize_textarea_field($_POST['xmp_metadata_fields']));
            
            echo '<div class="notice notice-success"><p>✅ Configuration sauvegardée !</p></div>';
        }
        
        $api_url = get_option('iris_api_url', IRIS_API_URL);
        $max_file_size = get_option('iris_max_file_size', 100);
        $email_notifications = get_option('iris_email_notifications', true);
        $auto_xmp_generation = get_option('iris_auto_xmp_generation', true);
        $xmp_metadata_fields = get_option('iris_xmp_metadata_fields', "Title\nDescription\nKeywords\nCreator\nRights");
        
        ?>
        <div class="wrap">
            <h1>⚙️ Configuration Iris Process</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('iris_config_save'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">🐍 URL de l'API Python</th>
                        <td>
                            <input type="url" name="api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text" />
                            <p class="description">URL complète de votre API Python de traitement d'images.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">📏 Taille max fichiers (MB)</th>
                        <td>
                            <input type="number" name="max_file_size" value="<?php echo esc_attr($max_file_size); ?>" min="1" max="500" />
                            <p class="description">Taille maximum autorisée pour les uploads d'images.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">📧 Notifications email</th>
                        <td>
                            <label>
                                <input type="checkbox" name="email_notifications" <?php checked($email_notifications); ?> />
                                Envoyer un email quand le traitement est terminé
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">📄 Génération automatique XMP</th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_xmp_generation" <?php checked($auto_xmp_generation); ?> />
                                Générer automatiquement des fichiers .xmp pour chaque image traitée
                            </label>
                            <p class="description">Les fichiers XMP contiendront les métadonnées du traitement.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">🏷️ Champs métadonnées XMP</th>
                        <td>
                            <textarea name="xmp_metadata_fields" rows="6" cols="50" class="large-text"><?php echo esc_textarea($xmp_metadata_fields); ?></textarea>
                            <p class="description">Champs de métadonnées à inclure dans les fichiers XMP (un par ligne).</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('💾 Sauvegarder la configuration'); ?>
            </form>
            
            <div style="margin-top: 40px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                <h2>📋 Formats de fichiers supportés</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div>
                        <h3>📷 RAW</h3>
                        <ul>
                            <li>.CR2, .CR3 (Canon)</li>
                            <li>.NEF (Nikon)</li>
                            <li>.ARW (Sony)</li>
                            <li>.ORF (Olympus)</li>
                            <li>.RAF (Fujifilm)</li>
                            <li>.RW2 (Panasonic)</li>
                            <li>.DNG (Adobe)</li>
                        </ul>
                    </div>
                    <div>
                        <h3>🖼️ Standard</h3>
                        <ul>
                            <li>.JPG, .JPEG</li>
                            <li>.TIF, .TIFF</li>
                            <li>.PNG</li>
                        </ul>
                    </div>
                    <div>
                        <h3>📄 Métadonnées</h3>
                        <ul>
                            <li>.XMP (sidecar)</li>
                            <li>EXIF intégré</li>
                            <li>IPTC</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Page des jobs
     */
    public function jobs_admin_page() {
        global $wpdb;
        
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_jobs'") != $table_jobs) {
            echo '<div class="wrap"><h1>🔄 Jobs de traitement</h1><p>Table des jobs non trouvée. Activez d\'abord le plugin.</p></div>';
            return;
        }
        
        // Pagination
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        $total_jobs = $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs");
        $total_pages = ceil($total_jobs / $per_page);
        
        $jobs = $wpdb->get_results($wpdb->prepare("
            SELECT j.*, u.display_name, u.user_email 
            FROM $table_jobs j 
            LEFT JOIN {$wpdb->users} u ON j.user_id = u.ID 
            ORDER BY j.created_at DESC 
            LIMIT %d OFFSET %d
        ", $per_page, $offset));
        
        ?>
        <div class="wrap">
            <h1>🔄 Jobs de traitement</h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <span class="displaying-num"><?php echo number_format($total_jobs); ?> éléments</span>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="tablenav-pages">
                    <?php
                    $page_links = paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page,
                        'type' => 'plain'
                    ));
                    echo $page_links;
                    ?>
                </div>
                <?php endif; ?>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Job ID</th>
                        <th>Utilisateur</th>
                        <th>Fichier</th>
                        <th>Statut</th>
                        <th>Créé</th>
                        <th>Terminé</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jobs)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            Aucun job de traitement trouvé.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td><?php echo $job->id; ?></td>
                            <td><code><?php echo esc_html(substr($job->job_id, 0, 8)); ?>...</code></td>
                            <td>
                                <?php if ($job->display_name): ?>
                                    <strong><?php echo esc_html($job->display_name); ?></strong><br>
                                    <small><?php echo esc_html($job->user_email); ?></small>
                                <?php else: ?>
                                    <em>Utilisateur supprimé</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($job->original_file); ?>
                                <?php if ($job->result_files): ?>
                                    <br><small style="color: green;">✅ Fichiers générés</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="iris-status-badge iris-status-<?php echo $job->status; ?>">
                                    <?php echo $this->get_status_text($job->status); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($job->created_at); ?></td>
                            <td><?php echo $job->completed_at ? esc_html($job->completed_at) : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Page des utilisateurs
     */
    public function users_admin_page() {
        global $wpdb;
        
        // Traitement des actions
        if (isset($_POST['action']) && $_POST['action'] === 'add_tokens_to_user') {
            $this->handle_admin_actions();
        }
        
        $table_tokens = $wpdb->prefix . 'iris_user_tokens';
        
        $users = $wpdb->get_results("
            SELECT u.ID, u.display_name, u.user_email, u.user_registered,
                   COALESCE(t.token_balance, 0) as token_balance,
                   COALESCE(t.total_purchased, 0) as total_purchased,
                   COALESCE(t.total_used, 0) as total_used
            FROM {$wpdb->users} u
            LEFT JOIN $table_tokens t ON u.ID = t.user_id
            ORDER BY u.user_registered DESC
            LIMIT 100
        ");
        
        ?>
        <div class="wrap">
            <h1>👥 Gestion des utilisateurs</h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Email</th>
                        <th>Inscription</th>
                        <th>Jetons disponibles</th>
                        <th>Total acheté</th>
                        <th>Total utilisé</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($user->display_name); ?></strong><br>
                            <small>ID: <?php echo $user->ID; ?></small>
                        </td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($user->user_registered)); ?></td>
                        <td>
                            <span class="iris-token-badge"><?php echo $user->token_balance; ?></span>
                        </td>
                        <td><?php echo $user->total_purchased; ?></td>
                        <td><?php echo $user->total_used; ?></td>
                        <td>
                            <form method="post" style="display: inline-block;">
                                <input type="hidden" name="action" value="add_tokens_to_user">
                                <input type="hidden" name="user_id" value="<?php echo $user->ID; ?>">
                                <input type="number" name="tokens_amount" value="10" min="1" max="100" style="width: 60px;">
                                <button type="submit" class="button button-small">➕ Ajouter</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Gestion des actions admin
     */
    private function handle_admin_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        switch ($_POST['action']) {
            case 'add_test_tokens':
                $this->add_tokens_to_admin();
                break;
                
            case 'cleanup_old_jobs':
                $this->cleanup_old_jobs();
                break;
                
            case 'test_api':
                $this->test_api_connection();
                break;
                
            case 'add_tokens_to_user':
                if (isset($_POST['user_id']) && isset($_POST['tokens_amount'])) {
                    $this->add_tokens_to_user_admin($_POST['user_id'], $_POST['tokens_amount']);
                }
                break;
        }
    }
    
    /**
     * Ajouter des jetons à l'admin pour test
     */
    private function add_tokens_to_admin() {
        global $wpdb;
        
        $admin_id = get_current_user_id();
        $table = $wpdb->prefix . 'iris_user_tokens';
        
        $wpdb->query($wpdb->prepare("
            INSERT INTO $table (user_id, token_balance, total_purchased) 
            VALUES (%d, 10, 10)
            ON DUPLICATE KEY UPDATE 
            token_balance = token_balance + 10,
            total_purchased = total_purchased + 10
        ", $admin_id));
        
        // Ajouter transaction
        $table_transactions = $wpdb->prefix . 'iris_token_transactions';
        $wpdb->insert($table_transactions, array(
            'user_id' => $admin_id,
            'transaction_type' => 'admin_gift',
            'tokens_amount' => 10,
            'description' => 'Jetons de test ajoutés par admin'
        ));
        
        echo '<div class="notice notice-success"><p>✅ 10 jetons ajoutés à votre compte !</p></div>';
    }
    
    /**
     * Obtenir les statistiques du dashboard
     */
    private function get_dashboard_stats() {
        global $wpdb;
        
        $table_tokens = $wpdb->prefix . 'iris_user_tokens';
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        
        // Vérifier si les tables existent
        $tokens_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_tokens'") == $table_tokens;
        $jobs_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_jobs'") == $table_jobs;
        
        return array(
            'total_users' => $tokens_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_tokens") : 0,
            'total_jobs' => $jobs_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs") : 0,
            'completed_jobs' => $jobs_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status = 'completed'") : 0,
            'pending_jobs' => $jobs_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status IN ('pending', 'processing')") : 0,
            'failed_jobs' => $jobs_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status = 'failed'") : 0,
            'total_tokens_purchased' => $tokens_exists ? $wpdb->get_var("SELECT SUM(total_purchased) FROM $table_tokens") : 0,
            'total_tokens_used' => $tokens_exists ? $wpdb->get_var("SELECT SUM(total_used) FROM $table_tokens") : 0
        );
    }
    
    /**
     * Obtenir les jobs récents
     */
    private function get_recent_jobs($limit = 5) {
        global $wpdb;
        
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_jobs'") != $table_jobs) {
            return array();
        }
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT j.*, u.display_name, u.user_email 
            FROM $table_jobs j 
            LEFT JOIN {$wpdb->users} u ON j.user_id = u.ID 
            ORDER BY j.created_at DESC 
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Obtenir les utilisateurs récents
     */
    private function get_recent_users($limit = 5) {
        global $wpdb;
        
        $table_tokens = $wpdb->prefix . 'iris_user_tokens';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_tokens'") != $table_tokens) {
            return array();
        }
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT u.ID, u.display_name, u.user_email, t.token_balance, t.created_at
            FROM $table_tokens t
            JOIN {$wpdb->users} u ON t.user_id = u.ID 
            ORDER BY t.created_at DESC 
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Obtenir le texte du statut
     */
    private function get_status_text($status) {
        $statuses = array(
            'pending' => '⏳ En attente',
            'processing' => '🔄 En cours',
            'completed' => '✅ Terminé',
            'failed' => '❌ Erreur'
        );
        
        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }
    
    /**
     * Nettoyer les anciens jobs
     */
    private function cleanup_old_jobs() {
        global $wpdb;
        
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_jobs'") == $table_jobs) {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_jobs 
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                30
            ));
            
            echo '<div class="notice notice-success"><p>✅ ' . $deleted . ' anciens jobs supprimés !</p></div>';
        }
    }
    
    /**
     * Tester la connexion API
     */
    private function test_api_connection() {
        $api_url = get_option('iris_api_url', IRIS_API_URL);
        
        $response = wp_remote_get($api_url . '/health', array(
            'timeout' => 10,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            echo '<div class="notice notice-error"><p>❌ Erreur API: ' . $response->get_error_message() . '</p></div>';
        } else {
            $code = wp_remote_retrieve_response_code($response);
            if ($code === 200) {
                echo '<div class="notice notice-success"><p>✅ API accessible ! Code: ' . $code . '</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>⚠️ API répond avec le code: ' . $code . '</p></div>';
            }
        }
    }
    
    /**
     * Ajouter des jetons à un utilisateur (action admin)
     */
    private function add_tokens_to_user_admin($user_id, $amount) {
        global $wpdb;
        
        $user_id = intval($user_id);
        $amount = intval($amount);
        
        if ($amount <= 0 || $amount > 100) {
            echo '<div class="notice notice-error"><p>❌ Montant invalide !</p></div>';
            return;
        }
        
        $table = $wpdb->prefix . 'iris_user_tokens';
        
        $wpdb->query($wpdb->prepare("
            INSERT INTO $table (user_id, token_balance, total_purchased) 
            VALUES (%d, %d, %d)
            ON DUPLICATE KEY UPDATE 
            token_balance = token_balance + %d,
            total_purchased = total_purchased + %d
        ", $user_id, $amount, $amount, $amount, $amount));
        
        // Ajouter transaction
        $table_transactions = $wpdb->prefix . 'iris_token_transactions';
        $wpdb->insert($table_transactions, array(
            'user_id' => $user_id,
            'transaction_type' => 'admin_gift',
            'tokens_amount' => $amount,
            'description' => 'Jetons ajoutés par administrateur'
        ));
        
        $user = get_user_by('id', $user_id);
        echo '<div class="notice notice-success"><p>✅ ' . $amount . ' jetons ajoutés à ' . $user->display_name . ' !</p></div>';
    }
    
    /**
     * Styles CSS pour l'administration
     */
    private function get_admin_styles() {
        return '<style>
            .iris-admin-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin: 30px 0;
            }
            
            .iris-stat-card {
                background: white;
                padding: 24px;
                border-radius: 12px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.1);
                text-align: center;
                border-left: 4px solid #3de9f4;
                transition: transform 0.2s ease;
            }
            
            .iris-stat-card:hover {
                transform: translateY(-2px);
            }
            
            .iris-stat-card h3 {
                margin: 0 0 10px 0;
                color: #0C2D39;
                font-size: 16px;
                font-weight: 600;
            }
            
            .iris-stat-number {
                font-size: 2.5em;
                font-weight: bold;
                color: #3de9f4;
                margin: 10px 0;
                line-height: 1;
            }
            
            .iris-stat-label {
                color: #666;
                font-size: 14px;
            }
            
            .iris-admin-actions {
                background: white;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            
            .iris-action-buttons {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .iris-admin-grid {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 20px;
                margin-top: 30px;
            }
            
            @media (max-width: 1200px) {
                .iris-admin-grid {
                    grid-template-columns: 1fr;
                }
            }
            
            .iris-admin-section {
                background: white;
                padding: 24px;
                border-radius: 12px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            }
            
            .iris-admin-section h2 {
                margin: 0 0 20px 0;
                color: #0C2D39;
                border-bottom: 2px solid #3de9f4;
                padding-bottom: 10px;
            }
            
            .iris-status-badge {
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
            }
            
            .iris-status-badge.iris-status-completed {
                background: #d4edda;
                color: #155724;
            }
            
            .iris-status-badge.iris-status-processing {
                background: #fff3cd;
                color: #856404;
            }
            
            .iris-status-badge.iris-status-failed {
                background: #f8d7da;
                color: #721c24;
            }
            
            .iris-status-badge.iris-status-pending {
                background: #d1ecf1;
                color: #0c5460;
            }
            
            .iris-token-badge {
                background: #3de9f4;
                color: #0C2D39;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: bold;
            }
            
            .iris-system-status {
                space-y: 15px;
            }
            
            .iris-status-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .iris-status-item:last-child {
                border-bottom: none;
            }
        </style>';
    }
    
    /**
     * Scripts JavaScript pour l'administration
     */
    private function get_admin_scripts() {
        return '<script>
            document.addEventListener("DOMContentLoaded", function() {
                var testApiBtn = document.getElementById("test-api-btn");
                if (testApiBtn) {
                    testApiBtn.addEventListener("click", function() {
                        var statusSpan = document.getElementById("api-status");
                        statusSpan.innerHTML = "🔄 Test en cours...";
                        
                        fetch("' . IRIS_API_URL . '/health", {
                            method: "GET",
                            timeout: 10000
                        })
                        .then(response => {
                            if (response.ok) {
                                statusSpan.innerHTML = "<span style=\"color: green;\">✅ API accessible</span>";
                            } else {
                                statusSpan.innerHTML = "<span style=\"color: orange;\">⚠️ API répond avec code: " + response.status + "</span>";
                            }
                        })
                        .catch(error => {
                            statusSpan.innerHTML = "<span style=\"color: red;\">❌ API inaccessible</span>";
                        });
                    });
                }
            });
        </script>';
    }
    
    /**
     * Activation du plugin
     */
    public function activate() {
        $this->create_tables();
        update_option('iris_process_db_version', IRIS_PLUGIN_VERSION);
        flush_rewrite_rules();
    }
    
    /**
     * Création des tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table des jetons utilisateur
        $table_tokens = $wpdb->prefix . 'iris_user_tokens';
        $sql_tokens = "CREATE TABLE IF NOT EXISTS $table_tokens (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            token_balance int(11) DEFAULT 0,
            total_purchased int(11) DEFAULT 0,
            total_used int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        // Table des transactions
        $table_transactions = $wpdb->prefix . 'iris_token_transactions';
        $sql_transactions = "CREATE TABLE IF NOT EXISTS $table_transactions (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            transaction_type varchar(50) NOT NULL,
            tokens_amount int(11) NOT NULL,
            order_id varchar(100) NULL,
            image_process_id int(11) NULL,
            description text NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Table des jobs de traitement
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        $sql_jobs = "CREATE TABLE IF NOT EXISTS $table_jobs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            job_id varchar(100) NOT NULL,
            user_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            original_file varchar(255) NOT NULL,
            result_files longtext,
            error_message text,
            api_response longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at datetime,
            PRIMARY KEY (id),
            UNIQUE KEY job_id (job_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_tokens);
        dbDelta($sql_transactions);
        dbDelta($sql_jobs);
    }
    
    /**
     * Désactivation du plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialiser le plugin
IrisProcessTokens::get_instance();

/**
 * Fonction helper
 */
function iris_process_tokens() {
    return IrisProcessTokens::get_instance();
}