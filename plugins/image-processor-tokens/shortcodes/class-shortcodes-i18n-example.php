<?php
/**
 * EXEMPLE : Shortcode internationalisé pour Iris Process
 * 
 * Ce fichier montre comment adapter les shortcodes existants
 * pour utiliser le système de traduction
 * 
 * @package IrisProcessTokens
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Iris_Process_Shortcodes_I18n_Example {
    
    public function __construct() {
        add_action('init', array($this, 'register_shortcodes'));
    }
    
    public function register_shortcodes() {
        add_shortcode('iris_upload_zone_i18n', array($this, 'upload_zone_i18n'));
    }
    
    /**
     * Exemple de shortcode zone d'upload internationalisé
     * 
     * @param array $atts Attributs du shortcode
     * @return string HTML du shortcode
     */
    public function upload_zone_i18n($atts) {
        if (!is_user_logged_in()) {
            return $this->render_login_required_i18n();
        }
        
        $user_id = get_current_user_id();
        $token_balance = Token_Manager::get_user_balance($user_id);
        
        ob_start();
        ?>
        <div id="iris-upload-container" data-language="<?php echo iris_get_language_manager()->get_current_language(); ?>">
            
            <!-- Sélecteur de langue pour debug -->
            <?php if (isset($_GET['iris_debug'])): ?>
                <div class="iris-debug-lang" style="background:#f0f0f0; padding:10px; margin-bottom:20px; border-radius:5px;">
                    <strong>🔧 Debug Mode:</strong><br>
                    <?php echo iris_get_language_manager()->get_language_selector(); ?>
                    <br><small>Langue détectée : <strong><?php echo iris_get_language_manager()->get_current_language(); ?></strong></small>
                </div>
            <?php endif; ?>
            
            <div class="iris-token-info">
                <!-- Utilisation des fonctions de traduction -->
                <h3><?php iris_e('Vos jetons disponibles :'); ?> <span id="token-balance"><?php echo $token_balance; ?></span></h3>
                
                <?php if ($token_balance < 1): ?>
                    <p class="iris-warning">
                        <?php iris_e('Vous n\'avez pas assez de jetons.'); ?> 
                        <a href="/<?php echo iris_is_english() ? 'shop' : 'boutique'; ?>">
                            <?php iris_e('Achetez des jetons'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="iris-upload-zone" <?php echo $token_balance < 1 ? 'style="opacity: 0.5; pointer-events: none;"' : ''; ?>>
                <form id="iris-upload-form" enctype="multipart/form-data">
                    <div class="iris-drop-zone" id="iris-drop-zone">
                        <div class="iris-drop-content">
                            <div class="iris-upload-icon">
                                <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <line x1="16" y1="52" x2="48" y2="52" stroke="#3de9f4" stroke-width="3" stroke-linecap="round"/>
                                    <path d="M32 12 L32 44" stroke="#3de9f4" stroke-width="3" stroke-linecap="round"/>
                                    <path d="M24 36 L32 44 L40 36" stroke="#3de9f4" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                                    <circle cx="32" cy="32" r="28" stroke="#3de9f4" stroke-width="1" opacity="0.2" fill="none"/>
                                </svg>
                            </div>
                            
                            <h4><?php iris_e('Glissez votre image ici ou cliquez pour sélectionner'); ?></h4>
                            <p><?php iris_e('Formats supportés : CR3, CR2, NEF, ARW, RAW, DNG, ORF, RAF, RW2, JPG, TIF, PNG'); ?></p>
                            <p><?php iris_e('Taille maximum :'); ?> <?php echo size_format(wp_max_upload_size()); ?></p>
                            
                            <input type="file" id="iris-file-input" name="image_file" 
                                   accept=".cr3,.cr2,.nef,.arw,.jpg,.jpeg,.tif,.tiff,.raw,.dng,.orf,.raf,.rw2,.png" 
                                   class="iris-file-input-styled">
                        </div>
                    </div>
                    
                    <div id="iris-file-preview" style="display: none;">
                        <div class="iris-file-info">
                            <span id="iris-file-name"></span>
                            <span id="iris-file-size"></span>
                            <button type="button" id="iris-remove-file">×</button>
                        </div>
                    </div>
    
                    <div class="iris-upload-actions">
                        <button type="submit" id="iris-upload-btn" disabled>
                            <span class="iris-btn-text"><?php iris_e('Traiter l\'image (1 jeton)'); ?></span>
                            <span class="iris-btn-loading" style="display: none;"><?php iris_e('⏳ Traitement en cours...'); ?></span>
                        </button>
                    </div>
                </form>
            </div>
            
            <div id="iris-upload-result" style="display: none;"></div>
            
            <div id="iris-process-history-uploadzone">
                <h3><?php iris_e('Historique des traitements'); ?></h3>
                <div id="iris-history-list-container-uploadzone">
                    <div id="iris-history-list-uploadzone" style="max-height:320px;overflow-y:auto;"></div>
                    <div id="iris-history-pagination-uploadzone" style="margin-top:10px;text-align:center;"></div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        // Localisations JavaScript
        window.irisTranslations = {
            'noProcessing': '<?php echo esc_js(iris__('Aucun traitement effectué pour le moment.')); ?>',
            'download': '<?php echo esc_js(iris__('Télécharger')); ?>',
            'prev': '<?php echo esc_js(iris__('Préc.')); ?>',
            'next': '<?php echo esc_js(iris__('Suiv.')); ?>',
            'processing': '<?php echo esc_js(iris__('⏳ Traitement en cours...')); ?>',
            'uploadSuccess': '<?php echo esc_js(iris__('Votre fichier a été envoyé avec succès, il est en cours de traitement...')); ?>',
            'connectionError': '<?php echo esc_js(iris__('❌ Erreur de connexion')); ?>',
            'genericError': '<?php echo esc_js(iris__('❌ Erreur')); ?>',
            'unknownError': '<?php echo esc_js(iris__('Erreur inconnue')); ?>'
        };
        
        // Exemple d'utilisation des traductions en JavaScript
        jQuery(function($){
            const ITEMS_PER_PAGE = 10;
            
            function renderEmptyHistory() {
                return '<p style="color:#124C58;text-align:center;padding:20px;">' + 
                       window.irisTranslations.noProcessing + '</p>';
            }
            
            function createDownloadLink(file, jobId) {
                const fileName = file.split('/').pop();
                const downloadUrl = '/wp-json/iris/v1/download/' + jobId + '/' + fileName;
                return '<a href="' + downloadUrl + '" class="iris-download-btn" download>' + 
                       window.irisTranslations.download + ' ' + fileName + '</a>';
            }
            
            function createPaginationButtons(currentPage, totalPages) {
                let html = '';
                if (totalPages > 1) {
                    html += '<button class="iris-page-btn iris-page-prev"' + 
                           (currentPage === 1 ? ' disabled' : '') + '>' + 
                           window.irisTranslations.prev + '</button>';
                    html += '<span class="iris-page-info">' + currentPage + '/' + totalPages + '</span>';
                    html += '<button class="iris-page-btn iris-page-next"' + 
                           (currentPage === totalPages ? ' disabled' : '') + '>' + 
                           window.irisTranslations.next + '</button>';
                }
                return html;
            }
        });
        </script>
        
        <?php
        echo $this->get_upload_styles();
        
        return ob_get_clean();
    }
    
    /**
     * Message de connexion requise internationalisé
     * 
     * @return string HTML
     */
    private function render_login_required_i18n() {
        ob_start();
        ?>
        <div class="iris-login-required">
            <h3><?php iris_e('Connexion requise'); ?></h3>
            <p><?php iris_e('Vous devez être connecté pour utiliser cette fonctionnalité.'); ?></p>
            <a href="<?php echo wp_login_url(get_permalink()); ?>" class="iris-login-btn">
                <?php iris_e('Se connecter'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Styles CSS (identiques)
     */
    private function get_upload_styles() {
        return '<style>
        .iris-login-required {
            background: #0C2D39;
            color: #F4F4F2;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            border: none;
            font-family: "Lato", sans-serif;
        }
        
        .iris-debug-lang .iris-language-selector {
            margin: 10px 0;
        }
        
        .iris-debug-lang .iris-lang-link {
            display: inline-block;
            padding: 5px 10px;
            margin: 0 5px;
            background: #fff;
            color: #333;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
        }
        
        .iris-debug-lang .iris-lang-link.selected {
            background: #3de9f4;
            color: #0C2D39;
            font-weight: bold;
        }
        
        /* ... autres styles identiques ... */
        </style>';
    }
}

// Initialiser seulement en mode debug/exemple
if (defined('IRIS_DEBUG_I18N') && IRIS_DEBUG_I18N) {
    new Iris_Process_Shortcodes_I18n_Example();
} 