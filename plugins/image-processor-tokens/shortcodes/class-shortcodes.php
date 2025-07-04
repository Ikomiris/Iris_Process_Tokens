<?php
if (!defined('ABSPATH')) {
    exit;
}

class Iris_Process_Shortcodes {
    
    public function __construct() {
        add_action('init', array($this, 'register_shortcodes'));
    }
    
    public function register_shortcodes() {
        add_shortcode('iris_upload_zone', array($this, 'upload_zone'));
        add_shortcode('user_token_balance', array($this, 'token_balance'));
        add_shortcode('token_history', array($this, 'token_history'));
        add_shortcode('iris_process_page', array($this, 'process_page'));
    }
    
    /**
     * Shortcode de la zone d'upload
     */
    public function upload_zone($atts) {
        if (!is_user_logged_in()) {
            return $this->render_login_required();
        }
        
        $user_id = get_current_user_id();
        $token_balance = Token_Manager::get_user_balance($user_id);
        
        ob_start();
        ?>
        <div id="iris-upload-container">
            <div class="iris-token-info">
                <h3>Vos jetons disponibles : <span id="token-balance"><?php echo $token_balance; ?></span></h3>
                <?php if ($token_balance < 1): ?>
                    <p class="iris-warning">Vous n'avez pas assez de jetons. <a href="/boutique">Achetez des jetons</a></p>
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
                            <h4>Glissez votre image ici ou cliquez pour sélectionner</h4>
                            <p>Formats supportés : CR3, CR2, NEF, ARW, RAW, DNG, ORF, RAF, RW2, JPG, TIF, PNG</p>
                            <p>Taille maximum : <?php echo size_format(wp_max_upload_size()); ?></p>
                            
                            <input type="file" id="iris-file-input" name="image_file" accept=".cr3,.cr2,.nef,.arw,.jpg,.jpeg,.tif,.tiff,.raw,.dng,.orf,.raf,.rw2,.png" class="iris-file-input-styled">
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
                            <span class="iris-btn-text">Traiter l'image (1 jeton)</span>
                            <span class="iris-btn-loading" style="display: none;">⏳ Traitement en cours...</span>
                        </button>
                    </div>
                </form>
            </div>
            
            <div id="iris-upload-result" style="display: none;"></div>
            
            <div id="iris-process-history">
                <h3>Historique des traitements</h3>
                <div id="iris-history-list">
                    <?php echo iris_get_user_process_history($user_id); ?>
                </div>
            </div>
        </div>
        
        <?php
        echo $this->get_upload_styles();
        echo $this->get_upload_scripts();
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher le solde de jetons
     */
    public function token_balance($atts) {
        if (!is_user_logged_in()) {
            return '<span class="iris-login-required">Connexion requise</span>';
        }
        
        $user_id = get_current_user_id();
        $balance = Token_Manager::get_user_balance($user_id);
        
        return '<span class="iris-token-balance">' . $balance . '</span>';
    }
    
    /**
     * Shortcode pour l'historique des jetons
     */
    public function token_history($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<p class="iris-login-required">Connexion requise pour voir l\'historique.</p>';
        }
        
        $user_id = get_current_user_id();
        $limit = intval($atts['limit']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'iris_token_transactions';
        
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id, $limit
        ));
        
        if (empty($transactions)) {
            return '<p>Aucune transaction trouvée.</p>';
        }
        
        $output = '<div class="iris-token-history">';
        foreach ($transactions as $transaction) {
            $type_class = $transaction->transaction_type === 'purchase' ? 'purchase' : 'usage';
            $sign = $transaction->tokens_amount > 0 ? '+' : '';
            
            $output .= '<div class="iris-transaction-item iris-' . $type_class . '">';
            $output .= '<span class="iris-transaction-amount">' . $sign . $transaction->tokens_amount . '</span>';
            $output .= '<span class="iris-transaction-desc">' . esc_html($transaction->description) . '</span>';
            $output .= '<span class="iris-transaction-date">' . date('d/m/Y', strtotime($transaction->created_at)) . '</span>';
            $output .= '</div>';
        }
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Shortcode pour la page de traitement complète
     */
    public function process_page($atts) {
        if (!is_user_logged_in()) {
            return $this->render_login_required();
        }
        
        $user_id = get_current_user_id();
        $balance = Token_Manager::get_user_balance($user_id);
        
        if ($balance < 1) {
            return '<div class="iris-no-tokens">
                <p>Vous n\'avez pas assez de jetons pour traiter une image.</p>
                <a href="/boutique" class="btn btn-primary">Acheter des jetons</a>
            </div>';
        }
        
        ob_start();
        ?>
        <div class="iris-process-page">
            <h2>Traitement d'image Iris Process</h2>
            <div class="token-info-small">
                Jetons disponibles : <strong><?php echo $balance; ?></strong>
            </div>
            
            <form id="iris-upload-form" enctype="multipart/form-data">
                <div class="upload-area">
                    <input type="file" id="iris-image-input" name="image" accept=".cr3,.cr2,.nef,.arw,.jpg,.jpeg,.tif,.tiff,.png" required>
                    <label for="iris-image-input">
                        <div class="upload-placeholder">
                            <p>Cliquez pour sélectionner une image</p>
                            <small>Formats acceptés : CR3, CR2, NEF, ARW, JPG, TIF, PNG</small>
                        </div>
                    </label>
                </div>
                
                <div class="process-actions">
                    <button type="submit" class="btn btn-primary">Traiter l'image (1 jeton)</button>
                </div>
            </form>
            
            <div id="iris-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <p class="progress-text">Traitement en cours...</p>
            </div>
            
            <div id="iris-result" style="display: none;">
                <!-- Résultat du traitement -->
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Affichage pour utilisateur non connecté
     */
    private function render_login_required() {
        return '<div class="iris-login-required">
                    <h3>Connexion requise</h3>
                    <p>Vous devez être connecté pour utiliser cette fonctionnalité.</p>
                    <a href="' . wp_login_url(get_permalink()) . '" class="iris-login-btn">Se connecter</a>
                </div>';
    }
    
    /**
     * Styles CSS pour la zone d'upload
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
        
        .iris-login-required h3 {
            color: #F4F4F2;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 16px;
            text-transform: uppercase;
        }
        
        .iris-login-btn {
            display: inline-block;
            background: #F05A28;
            color: #F4F4F2;
            padding: 12px 24px;
            border-radius: 24px;
            text-decoration: none;
            font-weight: 700;
            text-transform: uppercase;
            transition: all 0.3s ease;
            margin-top: 16px;
        }
        
        .iris-login-btn:hover {
            background: #3de9f4;
            color: #0C2D39;
            transform: translateY(-2px);
            text-decoration: none;
        }
        
        .iris-file-input-styled {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 10;
            font-size: 0;
        }
        
        .iris-drop-zone {
            position: relative;
            border: 3px dashed #3de9f4;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(60, 233, 244, 0.1);
            overflow: hidden;
        }
        
        .iris-drop-zone:hover {
            border-color: #F05A28;
            background: rgba(240, 90, 40, 0.1);
            transform: scale(1.02);
        }
        
        .iris-drop-content {
            position: relative;
            z-index: 1;
            pointer-events: none;
            color: #F4F4F2;
        }
        
        .iris-upload-icon {
            margin-bottom: 20px;
        }
        
        .iris-drop-content h4 {
            color: #3de9f4;
            font-size: 20px;
            margin: 10px 0;
        }
        
        .iris-drop-content p {
            color: #F4F4F2;
            margin: 5px 0;
            font-size: 14px;
        }
        
        #iris-file-preview {
            background: #0C2D39;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .iris-file-info {
            color: #F4F4F2;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        #iris-file-name {
            font-weight: bold;
            color: #3de9f4;
        }
        
        #iris-file-size {
            color: #ccc;
            font-size: 14px;
        }
        
        #iris-remove-file {
            background: #F05A28;
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        
        #iris-remove-file:hover {
            background: #e04a1a;
        }
        
        .iris-upload-actions {
            text-align: center;
            margin-top: 20px;
        }
        
        #iris-upload-btn {
            background: #F05A28;
            color: #F4F4F2;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
        }
        
        #iris-upload-btn:hover:not(:disabled) {
            background: #3de9f4;
            color: #0C2D39;
            transform: translateY(-2px);
        }
        
        #iris-upload-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        #iris-upload-result {
            margin-top: 20px;
        }
        
        .iris-success {
            background: #28a745;
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .iris-error {
            background: #dc3545;
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        #iris-process-history {
            background: #0C2D39;
            color: #F4F4F2;
            padding: 20px;
            border-radius: 12px;
            margin-top: 30px;
        }
        
        #iris-process-history h3 {
            color: #3de9f4;
            margin: 0 0 20px 0;
            font-size: 20px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            #iris-upload-container {
                padding: 10px;
            }
            
            .iris-drop-zone {
                padding: 20px 10px;
            }
        }
        </style>';
    }
    
    /**
     * JavaScript pour la zone d'upload
     */
    private function get_upload_scripts() {
        return '<script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log("🚀 Iris Upload - Version structurée");
            
            var dropZone = $("#iris-drop-zone");
            var fileInput = $("#iris-file-input");
            var filePreview = $("#iris-file-preview");
            var fileName = $("#iris-file-name");
            var fileSize = $("#iris-file-size");
            var removeBtn = $("#iris-remove-file");
            var uploadBtn = $("#iris-upload-btn");
            var uploadForm = $("#iris-upload-form");
            var result = $("#iris-upload-result");
            
            var selectedFile = null;
            
            // Empêcher défaut navigateur
            $(document).on("dragover drop", function(e) {
                e.preventDefault();
            });
            
            // INPUT CHANGE - Principal événement
            fileInput.on("change", function() {
                console.log("📂 Input change détecté !");
                if (this.files && this.files.length > 0) {
                    handleFile(this.files[0]);
                }
            });
            
            // Drag & Drop sur la zone
            dropZone.on("dragover dragenter", function(e) {
                e.preventDefault();
                $(this).css("background-color", "rgba(240, 90, 40, 0.2)");
            });
            
            dropZone.on("dragleave", function(e) {
                e.preventDefault();
                $(this).css("background-color", "rgba(60, 233, 244, 0.1)");
            });
            
            dropZone.on("drop", function(e) {
                e.preventDefault();
                $(this).css("background-color", "rgba(60, 233, 244, 0.1)");
                
                var files = e.originalEvent.dataTransfer.files;
                if (files && files.length > 0) {
                    handleFile(files[0]);
                }
            });
            
            // Traitement fichier
            function handleFile(file) {
                console.log("🔍 Fichier:", file.name);
                
                var ext = file.name.split(".").pop().toLowerCase();
                var allowed = ["jpg", "jpeg", "tif", "tiff", "cr3", "cr2", "nef", "arw", "raw", "dng", "orf", "raf", "rw2", "png"];
                
                if (allowed.indexOf(ext) === -1) {
                    alert("Format non supporté: " + ext.toUpperCase());
                    return;
                }
                
                selectedFile = file;
                fileName.text(file.name);
                fileSize.text(formatSize(file.size));
                filePreview.show();
                uploadBtn.prop("disabled", false);
                
                dropZone.css("background-color", "rgba(40, 167, 69, 0.2)");
                console.log("✅ Fichier accepté");
            }
            
            // Supprimer fichier
            removeBtn.on("click", function(e) {
                e.preventDefault();
                selectedFile = null;
                fileInput.val("");
                filePreview.hide();
                uploadBtn.prop("disabled", true);
                dropZone.css("background-color", "rgba(60, 233, 244, 0.1)");
            });
            
            // Submit formulaire
            uploadForm.on("submit", function(e) {
                e.preventDefault();
                
                if (!selectedFile) {
                    alert("Sélectionnez un fichier");
                    return;
                }
                
                console.log("🚀 Upload:", selectedFile.name);
                
                var originalText = uploadBtn.find(".iris-btn-text").text();
                uploadBtn.prop("disabled", true);
                uploadBtn.find(".iris-btn-text").hide();
                uploadBtn.find(".iris-btn-loading").show();
                
                var formData = new FormData();
                formData.append("action", "iris_upload_image");
                formData.append("nonce", iris_ajax.nonce);
                formData.append("image_file", selectedFile);
                
                $.ajax({
                    url: iris_ajax.ajax_url,
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    timeout: 120000,
                    success: function(resp) {
                        console.log("📨 Réponse:", resp);
                        
                        if (resp && resp.success) {
                            var successMsg = "<div style=\"background:#28a745;color:white;padding:15px;border-radius:8px;text-align:center;\">";
                            successMsg += "<h4>✅ " + resp.data.message + "</h4>";
                            successMsg += "<p>Jetons restants: " + resp.data.remaining_tokens + "</p>";
                            successMsg += "<p>Job ID: " + resp.data.job_id + "</p>";
                            if (resp.data.rawpy_applied) {
                                successMsg += "<p>🎨 Preset XMP appliqué</p>";
                            }
                            successMsg += "</div>";
                            
                            result.html(successMsg).show();
                            $("#token-balance").text(resp.data.remaining_tokens);
                            removeBtn.click();
                            
                            setTimeout(function() {
                                location.reload();
                            }, 3000);
                        } else {
                            var errorMsg = "<div style=\"background:#dc3545;color:white;padding:15px;border-radius:8px;text-align:center;\">";
                            errorMsg += "<h4>❌ Erreur</h4>";
                            errorMsg += "<p>" + (resp.data || "Erreur inconnue") + "</p>";
                            errorMsg += "</div>";
                            result.html(errorMsg).show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("💥 Erreur:", status, error);
                        var errorMsg = "<div style=\"background:#dc3545;color:white;padding:15px;border-radius:8px;text-align:center;\">";
                        errorMsg += "<h4>❌ Erreur de connexion</h4>";
                        errorMsg += "<p>" + status + ": " + error + "</p>";
                        errorMsg += "</div>";
                        result.html(errorMsg).show();
                    },
                    complete: function() {
                        uploadBtn.prop("disabled", false);
                        uploadBtn.find(".iris-btn-text").show().text(originalText);
                        uploadBtn.find(".iris-btn-loading").hide();
                    }
                });
            });
            
            function formatSize(bytes) {
                if (bytes > 1048576) {
                    return Math.round(bytes / 1048576) + " MB";
                }
                return Math.round(bytes / 1024) + " KB";
            }
            
            console.log("✅ Iris Upload initialisé !");
        });
        </script>';
    }
}