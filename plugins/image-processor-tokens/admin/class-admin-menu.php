<?php
if (!defined('ABSPATH')) {
    exit;
}

class Iris_Process_Admin_Menu {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menus'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    
    public function add_menus() {
        add_menu_page(
            'Iris Process',
            'Iris Process',
            'manage_options',
            'iris-process',
            array($this, 'main_page'),
            'dashicons-images-alt2',
            30
        );
        
        add_submenu_page(
            'iris-process',
            'Configuration',
            'Configuration',
            'manage_options',
            'iris-config',
            array($this, 'config_page')
        );
        
        add_submenu_page(
            'iris-process',
            'Jobs',
            'Jobs',
            'manage_options',
            'iris-jobs',
            array($this, 'jobs_page')
        );
    }
    
    public function main_page() {
        global $wpdb;
        
        // Statistiques générales
        $table_tokens = $wpdb->prefix . 'iris_user_tokens';
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM $table_tokens");
        $total_jobs = $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs");
        $pending_jobs = $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status IN ('pending', 'processing')");
        $completed_jobs = $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status = 'completed'");
        $failed_jobs = $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status = 'failed'");
        $total_tokens_used = $wpdb->get_var("SELECT SUM(total_used) FROM $table_tokens") ?: 0;
        $total_tokens_purchased = $wpdb->get_var("SELECT SUM(total_purchased) FROM $table_tokens") ?: 0;
        
        // Jobs récents
        $recent_jobs = $wpdb->get_results("
            SELECT j.*, u.display_name, u.user_email 
            FROM $table_jobs j 
            JOIN {$wpdb->users} u ON j.user_id = u.ID 
            ORDER BY j.created_at DESC 
            LIMIT 10
        ");
        
        ?>
        <div class="wrap">
            <h1>Iris Process - Tableau de bord</h1>
            
            <div class="iris-admin-stats">
                <div class="iris-stat-card iris-stat-primary">
                    <h3>Utilisateurs actifs</h3>
                    <p class="iris-stat-number"><?php echo number_format($total_users); ?></p>
                    <span class="iris-stat-label">Comptes avec jetons</span>
                </div>
                
                <div class="iris-stat-card iris-stat-success">
                    <h3>Traitements réussis</h3>
                    <p class="iris-stat-number"><?php echo number_format($completed_jobs); ?></p>
                    <span class="iris-stat-label">Images traitées</span>
                </div>
                
                <div class="iris-stat-card iris-stat-warning">
                    <h3>En cours</h3>
                    <p class="iris-stat-number"><?php echo number_format($pending_jobs); ?></p>
                    <span class="iris-stat-label">Files d'attente</span>
                </div>
                
                <div class="iris-stat-card iris-stat-info">
                    <h3>Jetons utilisés</h3>
                    <p class="iris-stat-number"><?php echo number_format($total_tokens_used); ?></p>
                    <span class="iris-stat-label">Total consommé</span>
                </div>
            </div>
            
            <div class="iris-admin-grid">
                <div class="iris-admin-section">
                    <h2>Activité récente</h2>
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
                                        <td><?php echo esc_html($job->display_name); ?></td>
                                        <td><?php echo esc_html($job->original_file); ?></td>
                                        <td>
                                            <span class="iris-status-badge iris-status-<?php echo $job->status; ?>">
                                                <?php echo iris_get_status_text($job->status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($job->created_at)); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="iris-admin-section">
                    <h2>API Status</h2>
                    <div class="iris-api-status">
                        <p><strong>URL API:</strong> <?php echo IRIS_API_URL; ?></p>
                        <button type="button" id="test-api" class="button">Tester l'API</button>
                        <div id="api-result"></div>
                    </div>
                </div>
            </div>
            
            <?php echo $this->get_admin_styles(); ?>
            
            <script>
            jQuery(document).ready(function($) {
                $('#test-api').on('click', function() {
                    var btn = $(this);
                    var result = $('#api-result');
                    
                    btn.prop('disabled', true).text('Test...');
                    
                    $.post(ajaxurl, {
                        action: 'iris_test_api'
                    }, function(response) {
                        if (response.success) {
                            result.html('<div style="color:green;padding:10px;">✅ ' + response.data + '</div>');
                        } else {
                            result.html('<div style="color:red;padding:10px;">❌ ' + response.data + '</div>');
                        }
                    }).always(function() {
                        btn.prop('disabled', false).text('Tester l\'API');
                    });
                });
            });
            </script>
        </div>
        <?php
    }
    
    public function config_page() {
        // Sauvegarde des paramètres
        if (isset($_POST['submit'])) {
            check_admin_referer('iris_config_save');
            
            update_option('iris_api_url', sanitize_url($_POST['api_url']));
            update_option('iris_max_file_size', intval($_POST['max_file_size']));
            update_option('iris_email_notifications', isset($_POST['email_notifications']));
            
            echo '<div class="notice notice-success"><p>Configuration sauvegardée !</p></div>';
        }
        
        $api_url = get_option('iris_api_url', IRIS_API_URL);
        $max_file_size = get_option('iris_max_file_size', 100);
        $email_notifications = get_option('iris_email_notifications', true);
        
        ?>
        <div class="wrap">
            <h1>Configuration Iris Process</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('iris_config_save'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">URL de l'API Python</th>
                        <td>
                            <input type="url" name="api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text" />
                            <p class="description">URL complète de votre API Python.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Taille max fichiers (MB)</th>
                        <td>
                            <input type="number" name="max_file_size" value="<?php echo esc_attr($max_file_size); ?>" min="1" max="500" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Notifications email</th>
                        <td>
                            <label>
                                <input type="checkbox" name="email_notifications" <?php checked($email_notifications); ?> />
                                Envoyer un email quand le traitement est terminé
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Sauvegarder'); ?>
            </form>
        </div>
        <?php
    }
    
    public function jobs_page() {
        global $wpdb;
        
        $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
        $jobs = $wpdb->get_results("
            SELECT j.*, u.display_name, u.user_email 
            FROM $table_jobs j 
            JOIN {$wpdb->users} u ON j.user_id = u.ID 
            ORDER BY j.created_at DESC 
            LIMIT 50
        ");
        
        ?>
        <div class="wrap">
            <h1>Jobs de traitement</h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Job ID</th>
                        <th>Utilisateur</th>
                        <th>Fichier</th>
                        <th>Statut</th>
                        <th>Créé</th>
                        <th>Terminé</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td><code><?php echo esc_html($job->job_id); ?></code></td>
                        <td><?php echo esc_html($job->display_name); ?></td>
                        <td><?php echo esc_html($job->original_file); ?></td>
                        <td>
                            <span class="iris-status-badge iris-status-<?php echo $job->status; ?>">
                                <?php echo iris_get_status_text($job->status); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($job->created_at); ?></td>
                        <td><?php echo $job->completed_at ? esc_html($job->completed_at) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'iris_tokens_widget',
            'Iris Process - Jetons',
            array($this, 'dashboard_widget')
        );
    }
    
    public function dashboard_widget() {
        if (!current_user_can('iris_process_images')) {
            return;
        }
        
        $user_id = get_current_user_id();
        $balance = Token_Manager::get_user_balance($user_id);
        
        echo '<div class="iris-dashboard-widget">';
        echo '<h3>Vos jetons Iris Process</h3>';
        echo '<p class="iris-token-count">' . $balance . ' jeton' . ($balance > 1 ? 's' : '') . ' disponible' . ($balance > 1 ? 's' : '') . '</p>';
        
        if ($balance > 0) {
            echo '<p><a href="' . home_url('/traitement-images/') . '" class="button button-primary">Traiter une image</a></p>';
        } else {
            echo '<p><a href="' . home_url('/boutique/') . '" class="button">Acheter des jetons</a></p>';
        }
        echo '</div>';
        
        echo '<style>
        .iris-dashboard-widget .iris-token-count {
            font-size: 1.5em;
            font-weight: bold;
            color: #3de9f4;
            text-align: center;
            margin: 15px 0;
        }
        </style>';
    }
    
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
            
            .iris-admin-grid {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 30px;
                margin-top: 30px;
            }
            
            .iris-admin-section {
                background: white;
                padding: 24px;
                border-radius: 12px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            }
            
            .iris-status-badge {
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
            }
            
            .iris-status-badge.iris-status-completed {
                background: #3de9f4;
                color: #0C2D39;
            }
            
            .iris-status-badge.iris-status-processing {
                background: #F05A28;
                color: white;
            }
            
            .iris-status-badge.iris-status-failed {
                background: #dc3545;
                color: white;
            }
            
            .iris-status-badge.iris-status-pending {
                background: #124C58;
                color: white;
            }
            
            @media (max-width: 768px) {
                .iris-admin-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>';
    }
}