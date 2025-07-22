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
        // Menu parent : 'Iris Process'
        add_menu_page(
            'Iris Process - Tableau de bord', // Titre de la page
            'Iris Process', // Label du menu parent
            'manage_options',
            'iris-dashboard', // Slug du menu parent
            array($this, 'main_page'),
            'dashicons-images-alt2',
            30
        );
        // Premier sous-menu : 'Tableau de bord' (même slug que le parent)
        add_submenu_page(
            'iris-dashboard', // Parent slug
            'Iris Process - Tableau de bord', // Titre de la page
            'Tableau de bord', // Label du sous-menu
            'manage_options',
            'iris-dashboard', // Slug identique au parent
            array($this, 'main_page')
        );
        // Les autres sous-menus restent inchangés
        add_submenu_page(
            'iris-dashboard',
            'Configuration',
            'Configuration',
            'manage_options',
            'iris-config',
            array($this, 'config_page')
        );
        add_submenu_page(
            'iris-dashboard',
            'Presets JSON',
            'Presets',
            'manage_options',
            'iris-presets',
            'iris_presets_admin_page'
        );
        add_submenu_page(
            'iris-dashboard',
            'Jobs',
            'Jobs',
            'manage_options',
            'iris-jobs',
            array($this, 'jobs_page')
        );
        add_submenu_page(
            'iris-dashboard',
            'Aide',
            'Aide',
            'manage_options',
            'iris-help',
            array($this, 'help_page')
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

            update_option('iris_s3_access_key', sanitize_text_field($_POST['s3_access_key']));
            update_option('iris_s3_secret_key', sanitize_text_field($_POST['s3_secret_key']));
            update_option('iris_s3_region', sanitize_text_field($_POST['s3_region']));
            update_option('iris_s3_bucket', sanitize_text_field($_POST['s3_bucket']));
            update_option('iris_s3_output_access_key', sanitize_text_field($_POST['s3_output_access_key']));
            update_option('iris_s3_output_secret_key', sanitize_text_field($_POST['s3_output_secret_key']));
            update_option('iris_s3_output_region', sanitize_text_field($_POST['s3_output_region']));
            update_option('iris_s3_output_bucket', sanitize_text_field($_POST['s3_output_bucket']));
            update_option('iris_max_file_size', intval($_POST['max_file_size']));
            update_option('iris_email_notifications', isset($_POST['email_notifications']));

            echo '<div class="notice notice-success"><p>Configuration sauvegardée !</p></div>';
        }

        $s3_access_key = get_option('iris_s3_access_key', '');
        $s3_secret_key = get_option('iris_s3_secret_key', '');
        $s3_region = get_option('iris_s3_region', 'eu-west-1');
        $s3_bucket = get_option('iris_s3_bucket', 'ikomiris-extractiris-source');
        $s3_output_access_key = get_option('iris_s3_output_access_key', '');
        $s3_output_secret_key = get_option('iris_s3_output_secret_key', '');
        $s3_output_region = get_option('iris_s3_output_region', 'eu-west-1');
        $s3_output_bucket = get_option('iris_s3_output_bucket', 'ikomiris-extractiris-outputs');
        $max_file_size = get_option('iris_max_file_size', 100);
        $email_notifications = get_option('iris_email_notifications', true);

        ?>
        <div class="wrap">
            <h1>Configuration Iris Process</h1>
            <form method="post" action="">
                <?php wp_nonce_field('iris_config_save'); ?>
                <table class="form-table">
                    <tr>
                        <th colspan="2"><h3>Bucket S3 Source (upload)</h3></th>
                    </tr>
                    <tr>
                        <th scope="row">Clé d'accès AWS (Access Key ID)</th>
                        <td>
                            <input type="text" name="s3_access_key" value="<?php echo esc_attr($s3_access_key); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Clé secrète AWS (Secret Access Key)</th>
                        <td>
                            <input type="password" name="s3_secret_key" value="<?php echo esc_attr($s3_secret_key); ?>" class="regular-text" autocomplete="new-password" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Région AWS</th>
                        <td>
                            <input type="text" name="s3_region" value="<?php echo esc_attr($s3_region); ?>" class="regular-text" />
                            <p class="description">Exemple : eu-west-1</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Nom du bucket S3</th>
                        <td>
                            <input type="text" name="s3_bucket" value="<?php echo esc_attr($s3_bucket); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr><td colspan="2"><hr></td></tr>
                    <tr>
                        <th colspan="2"><h3>Bucket S3 Output (photos traitées)</h3></th>
                    </tr>
                    <tr>
                        <th scope="row">Clé d'accès AWS (Access Key ID)</th>
                        <td>
                            <input type="text" name="s3_output_access_key" value="<?php echo esc_attr($s3_output_access_key); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Clé secrète AWS (Secret Access Key)</th>
                        <td>
                            <input type="password" name="s3_output_secret_key" value="<?php echo esc_attr($s3_output_secret_key); ?>" class="regular-text" autocomplete="new-password" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Région AWS</th>
                        <td>
                            <input type="text" name="s3_output_region" value="<?php echo esc_attr($s3_output_region); ?>" class="regular-text" />
                            <p class="description">Exemple : eu-west-1</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Nom du bucket S3</th>
                        <td>
                            <input type="text" name="s3_output_bucket" value="<?php echo esc_attr($s3_output_bucket); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr><td colspan="2"><hr></td></tr>
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
    
    /**
     * Page d'aide listant tous les shortcodes disponibles
     */
    public function help_page() {
        ?>
        <div class="wrap">
            <h1>📖 Aide & Shortcodes Iris Process</h1>
            <p>Voici la liste des shortcodes disponibles pour ce plugin, à utiliser dans vos pages ou articles WordPress :</p>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Shortcode</th>
                        <th>Explication</th>
                        <th>Exemple</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[iris_upload_zone]</code></td>
                        <td>Affiche la zone d'upload sécurisée pour traiter une image (consomme 1 jeton).</td>
                        <td><code>[iris_upload_zone]</code></td>
                    </tr>
                    <tr>
                        <td><code>[user_token_balance]</code></td>
                        <td>Affiche le solde de jetons de l'utilisateur connecté. Options : <code>style</code> (card/simple/inline), <code>show_actions</code> (true/false).</td>
                        <td><code>[user_token_balance style="card" show_actions="true"]</code></td>
                    </tr>
                    <tr>
                        <td><code>[token_history]</code></td>
                        <td>Affiche l'historique des transactions de jetons de l'utilisateur. Options : <code>limit</code>, <code>show_pagination</code>, <code>show_filters</code>.</td>
                        <td><code>[token_history limit="10" show_pagination="true" show_filters="true"]</code></td>
                    </tr>
                    <tr>
                        <td><code>[iris_process_page]</code></td>
                        <td>Affiche la page complète de traitement d'image avec sélection de preset et suivi du job.</td>
                        <td><code>[iris_process_page]</code></td>
                    </tr>
                    <tr>
                        <td><code>[iris_user_dashboard]</code></td>
                        <td>Affiche le tableau de bord utilisateur complet (solde, stats, jobs en cours, actions rapides).</td>
                        <td><code>[iris_user_dashboard layout="grid"]</code></td>
                    </tr>
                    <tr>
                        <td><code>[iris_user_stats]</code></td>
                        <td>Affiche les statistiques détaillées de l'utilisateur connecté (jetons, jobs, temps moyen, etc).</td>
                        <td><code>[iris_user_stats]</code></td>
                    </tr>
                </tbody>
            </table>
            <p>Pour toute question ou problème, contactez le support via <a href="https://iris4pro.com" target="_blank">iris4pro.com</a>.</p>
        </div>
        <?php
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