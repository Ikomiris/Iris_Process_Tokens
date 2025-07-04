<?php
/**
 * Fonctions d'administration
 * 
 * @package IrisProcessTokens
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pages d'administration (MODIFIÉ v1.1.0)
 * 
 * @since 1.0.0
 * @since 1.1.0 Ajout page presets
 * @return void
 */
function iris_add_admin_menu() {
    add_menu_page(
        'Iris Process',
        'Iris Process',
        'manage_options',
        'iris-process',
        'iris_admin_page',
        'dashicons-images-alt2',
        30
    );
    
    // NOUVEAU v1.1.0 - Page presets JSON
    add_submenu_page(
        'iris-process',
        'Presets JSON',
        'Presets',
        'manage_options',
        'iris-presets',
        'iris_presets_admin_page'
    );
    
    add_submenu_page(
        'iris-process',
        'Configuration',
        'Configuration',
        'manage_options',
        'iris-config',
        'iris_config_admin_page'
    );
    
    add_submenu_page(
        'iris-process',
        'Jobs',
        'Jobs',
        'manage_options',
        'iris-jobs',
        'iris_jobs_admin_page'
    );
}

/**
 * Page d'administration des presets JSON
 * 
 * @since 1.1.0
 * @return void
 */
function iris_presets_admin_page() {
    global $wpdb;
    
    // Traitement de l'upload
    if (isset($_POST['upload_preset']) && wp_verify_nonce($_POST['preset_nonce'], 'iris_preset_upload')) {
        $result = Preset_Manager::handle_upload();
        if (is_wp_error($result)) {
            echo '<div class="notice notice-error"><p>' . $result->get_error_message() . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>Preset uploadé avec succès !</p></div>';
        }
    }
    
    // Traitement de la suppression
    if (isset($_POST['delete_preset']) && wp_verify_nonce($_POST['delete_nonce'], 'iris_preset_delete')) {
        $preset_id = intval($_POST['preset_id']);
        if (Preset_Manager::delete($preset_id)) {
            echo '<div class="notice notice-success"><p>Preset supprimé avec succès !</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Erreur lors de la suppression.</p></div>';
        }
    }
    
    // Récupérer tous les presets
    $table_presets = $wpdb->prefix . 'iris_admin_presets';
    $presets = $wpdb->get_results("SELECT * FROM $table_presets ORDER BY created_at DESC");
    
    ?>
    <div class="wrap">
        <h1>🎨 Presets Iris Process</h1>
        <p class="description">Gérez les presets JSON générés par Iris Rawpy. Ces presets remplacent les anciens fichiers XMP.</p>
        
        <!-- Upload de nouveau preset -->
        <div class="iris-upload-preset">
            <h2>📁 Ajouter un nouveau preset</h2>
            <form method="post" enctype="multipart/form-data" class="preset-upload-form">
                <?php wp_nonce_field('iris_preset_upload', 'preset_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Nom du preset</th>
                        <td>
                            <input type="text" name="preset_name" class="regular-text" placeholder="Ex: Portrait Lumineux" required />
                            <p class="description">Nom descriptif pour identifier ce preset</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Fichier JSON</th>
                        <td>
                            <input type="file" name="preset_file" accept=".json" required />
                            <p class="description">Fichier JSON généré par Iris Rawpy (format v2.1 supporté)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Description</th>
                        <td>
                            <textarea name="preset_description" rows="3" cols="50" placeholder="Description optionnelle du preset..."></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Preset par défaut</th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_default" value="1" />
                                Utiliser comme preset par défaut pour tous les traitements
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="upload_preset" class="button-primary" value="Uploader le preset" />
                </p>
            </form>
        </div>
        
        <!-- Liste des presets existants -->
        <div class="iris-presets-list">
            <h2>📋 Presets disponibles</h2>
            
            <?php if (empty($presets)): ?>
                <div class="notice notice-info">
                    <p>Aucun preset disponible. Uploadez votre premier preset JSON ci-dessus.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Description</th>
                            <th>Version</th>
                            <th>Créé par</th>
                            <th>Utilisations</th>
                            <th>Défaut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($presets as $preset): 
                            $preset_data = json_decode($preset->preset_data, true);
                            $version = isset($preset_data['version']) ? $preset_data['version'] : 'Ancien';
                            $created_with = isset($preset_data['created_with']) ? $preset_data['created_with'] : 'Inconnu';
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($preset->preset_name); ?></strong></td>
                            <td><?php echo esc_html($preset->description ?: 'Aucune description'); ?></td>
                            <td>
                                <span class="iris-version-badge">v<?php echo esc_html($version); ?></span><br>
                                <small><?php echo esc_html($created_with); ?></small>
                            </td>
                            <td><?php echo esc_html($preset->created_by); ?></td>
                            <td><span class="usage-count"><?php echo intval($preset->usage_count); ?></span></td>
                            <td>
                               <?php if ($preset->is_default): ?>
                                   <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                               <?php else: ?>
                                   <span class="dashicons dashicons-minus" style="color: #ccc;"></span>
                               <?php endif; ?>
                           </td>
                           <td>
                               <button class="button button-small view-preset" 
                                       data-preset-id="<?php echo $preset->id; ?>"
                                       data-preset='<?php echo esc_attr(json_encode($preset_data, JSON_PRETTY_PRINT)); ?>'>
                                   👁️ Voir
                               </button>
                               
                               <?php if (!$preset->is_default): ?>
                               <form method="post" style="display: inline;">
                                   <?php wp_nonce_field('iris_preset_delete', 'delete_nonce'); ?>
                                   <input type="hidden" name="preset_id" value="<?php echo $preset->id; ?>" />
                                   <input type="submit" name="delete_preset" class="button button-small button-link-delete" 
                                          value="🗑️ Supprimer" 
                                          onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce preset ?');" />
                               </form>
                               <?php endif; ?>
                           </td>
                       </tr>
                       <?php endforeach; ?>
                   </tbody>
               </table>
           <?php endif; ?>
       </div>
   </div>
   
   <!-- Modal pour visualiser les presets -->
   <div id="preset-modal" class="iris-modal" style="display: none;">
       <div class="iris-modal-content">
           <div class="iris-modal-header">
               <h3>🎨 Détails du preset</h3>
               <span class="iris-modal-close">&times;</span>
           </div>
           <div class="iris-modal-body">
               <pre id="preset-details"></pre>
           </div>
       </div>
   </div>
   
   <style>
   .iris-upload-preset {
       background: white;
       border: 1px solid #ccd0d4;
       border-radius: 8px;
       padding: 20px;
       margin-bottom: 20px;
   }
   
   .iris-version-badge {
       background: #3de9f4;
       color: #0C2D39;
       padding: 2px 8px;
       border-radius: 12px;
       font-size: 11px;
       font-weight: bold;
   }
   
   .usage-count {
       background: #F05A28;
       color: white;
       padding: 2px 6px;
       border-radius: 50%;
       font-size: 12px;
       font-weight: bold;
   }
   
   .iris-modal {
       position: fixed;
       z-index: 100000;
       left: 0;
       top: 0;
       width: 100%;
       height: 100%;
       background-color: rgba(0,0,0,0.8);
   }
   
   .iris-modal-content {
       background-color: #fefefe;
       margin: 5% auto;
       border-radius: 8px;
       width: 80%;
       max-width: 800px;
       max-height: 80%;
       overflow: hidden;
   }
   
   .iris-modal-header {
       background: #0C2D39;
       color: #3de9f4;
       padding: 15px 20px;
       display: flex;
       justify-content: space-between;
       align-items: center;
   }
   
   .iris-modal-close {
       color: #F05A28;
       font-size: 28px;
       font-weight: bold;
       cursor: pointer;
   }
   
   .iris-modal-body {
       padding: 20px;
       max-height: 500px;
       overflow: auto;
   }
   
   #preset-details {
       background: #f5f5f5;
       padding: 15px;
       border-radius: 4px;
       font-size: 12px;
       line-height: 1.4;
       overflow: auto;
   }
   </style>
   
   <script>
   jQuery(document).ready(function($) {
       // Visualisation des presets
       $('.view-preset').on('click', function() {
           var presetData = $(this).data('preset');
           $('#preset-details').text(JSON.stringify(presetData, null, 2));
           $('#preset-modal').show();
       });
       
       // Fermeture du modal
       $('.iris-modal-close, .iris-modal').on('click', function(e) {
           if (e.target === this) {
               $('#preset-modal').hide();
           }
       });
       
       $(document).keyup(function(e) {
           if (e.keyCode === 27) { // Escape
               $('#preset-modal').hide();
           }
       });
   });
   </script>
   <?php
}

/**
* Page d'administration principale (MODIFIÉ v1.1.0)
* 
* @since 1.0.0
* @since 1.1.0 Ajout statistiques presets
* @return void
*/
function iris_admin_page() {
   global $wpdb;
   
   // Statistiques générales
   $table_tokens = $wpdb->prefix . 'iris_user_tokens';
   $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
   $table_presets = $wpdb->prefix . 'iris_admin_presets'; // NOUVEAU v1.1.0
   
   $total_users = $wpdb->get_var("SELECT COUNT(*) FROM $table_tokens");
   $total_jobs = $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs");
   $pending_jobs = $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status IN ('pending', 'processing')");
   $completed_jobs = $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status = 'completed'");
   $failed_jobs = $wpdb->get_var("SELECT COUNT(*) FROM $table_jobs WHERE status = 'failed'");
   $total_tokens_used = $wpdb->get_var("SELECT SUM(total_used) FROM $table_tokens");
   $total_tokens_purchased = $wpdb->get_var("SELECT SUM(total_purchased) FROM $table_tokens");
   $total_presets = $wpdb->get_var("SELECT COUNT(*) FROM $table_presets"); // NOUVEAU v1.1.0
   
   // Jobs récents avec presets (MODIFIÉ v1.1.0)
   $recent_jobs = $wpdb->get_results("
       SELECT j.*, u.display_name, u.user_email, p.preset_name
       FROM $table_jobs j 
       JOIN {$wpdb->users} u ON j.user_id = u.ID 
       LEFT JOIN $table_presets p ON j.preset_id = p.id
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
               <h3>Presets JSON</h3>
               <p class="iris-stat-number"><?php echo number_format($total_presets); ?></p>
               <span class="iris-stat-label">Presets disponibles</span>
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
                                   <th>Preset</th>
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
                                       <?php if ($job->preset_name): ?>
                                           <span style="color: #3de9f4;">🎨 <?php echo esc_html($job->preset_name); ?></span>
                                       <?php else: ?>
                                           <span style="color: #ccc;">Défaut</span>
                                       <?php endif; ?>
                                   </td>
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
       
       <?php echo iris_get_admin_styles(); ?>
       
       <script>
       jQuery(document).ready(function($) {
           $('#test-api').on('click', function() {
               var btn = $(this);
               var result = $('#api-result');
               
               btn.prop('disabled', true).text('Test...');
               
               $.get('<?php echo IRIS_API_URL; ?>/health')
                   .done(function(data) {
                       result.html('<div style="color:green;padding:10px;">✅ API accessible - Status: ' + data.status + '</div>');
                   })
                   .fail(function() {
                       result.html('<div style="color:red;padding:10px;">❌ API inaccessible</div>');
                   })
                   .always(function() {
                       btn.prop('disabled', false).text('Tester l\'API');
                   });
           });
       });
       </script>
   </div>
   <?php
}

/**
* Styles CSS pour l'administration
* 
* @since 1.0.0
* @return string CSS pour l'admin
*/
function iris_get_admin_styles() {
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
   </style>';
}

/**
* Page des jobs
* 
* @since 1.0.0
* @return void
*/
function iris_jobs_admin_page() {
   global $wpdb;
   
   $table_jobs = $wpdb->prefix . 'iris_processing_jobs';
   $table_presets = $wpdb->prefix . 'iris_admin_presets';
   
   $jobs = $wpdb->get_results("
       SELECT j.*, u.display_name, u.user_email, p.preset_name
       FROM $table_jobs j 
       JOIN {$wpdb->users} u ON j.user_id = u.ID 
       LEFT JOIN $table_presets p ON j.preset_id = p.id
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
                   <th>Preset</th>
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
                       <?php if ($job->preset_name): ?>
                           <span style="color: #3de9f4;">🎨 <?php echo esc_html($job->preset_name); ?></span>
                       <?php else: ?>
                           <span style="color: #999;">Défaut</span>
                       <?php endif; ?>
                   </td>
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

/**
* Page de configuration
* 
* @since 1.0.0
* @return void
*/
function iris_config_admin_page() {
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

/**
* Widget WordPress pour afficher les jetons dans le dashboard
* 
* @since 1.0.0
* @return void
*/
function iris_dashboard_widget() {
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
* Ajouter le widget au dashboard
* 
* @since 1.0.0
* @return void
*/
function iris_add_dashboard_widget() {
   wp_add_dashboard_widget(
       'iris_tokens_widget',
       'Iris Process - Jetons',
       'iris_dashboard_widget'
   );
}