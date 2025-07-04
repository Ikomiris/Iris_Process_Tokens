<?php
/**
 * Fonctions de gestion de l'upload et du traitement
 * 
 * @package IrisProcessTokens
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestionnaire d'upload d'images (MODIFIÉ v1.1.0 pour presets)
 * 
 * @since 1.0.0
 * @since 1.1.0 Ajout support presets JSON
 * @return void
 */
function iris_handle_image_upload() {
    // Vérification du nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iris_upload_nonce')) {
        wp_die('Erreur de sécurité');
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('Utilisateur non connecté');
    }
    
    // Vérification du solde de jetons
    if (Token_Manager::get_user_balance($user_id) < 1) {
        wp_send_json_error('Solde de jetons insuffisant');
    }
    
    // Récupération du preset sélectionné (NOUVEAU v1.1.0)
    $preset_id = isset($_POST['preset_id']) ? intval($_POST['preset_id']) : null;
    
    // Vérification du fichier uploadé
    if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('Erreur lors de l\'upload du fichier');
    }
    
    $file = $_FILES['image_file'];
    $allowed_extensions = array('jpg', 'jpeg', 'tif', 'tiff', 'cr3', 'nef', 'arw', 'raw', 'dng', 'orf', 'raf', 'rw2');
    
    // Vérification de l'extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
   
   if (!in_array($extension, $allowed_extensions)) {
       wp_send_json_error('Format de fichier non supporté. Formats acceptés : ' . implode(', ', $allowed_extensions));
   }
   
   // Création du répertoire d'upload spécifique
   $upload_dir = wp_upload_dir();
   $iris_dir = $upload_dir['basedir'] . '/iris-process';
   
   if (!file_exists($iris_dir)) {
       wp_mkdir_p($iris_dir);
   }
   
   // Génération d'un nom de fichier unique
   $file_name = uniqid('iris_' . $user_id . '_') . '.' . $extension;
   $file_path = $iris_dir . '/' . $file_name;
   
   // Déplacement du fichier
   if (move_uploaded_file($file['tmp_name'], $file_path)) {
       // Création de l'enregistrement de traitement
       $process_id = iris_create_process_record($user_id, $file_name, $file_path);
       
       // Envoi vers l'API Python avec preset (MODIFIÉ v1.1.0)
       $api_result = iris_send_to_python_api($file_path, $user_id, $process_id, $preset_id);
       
       if (is_wp_error($api_result)) {
           wp_send_json_error($api_result->get_error_message());
       } else {
           wp_send_json_success(array(
               'message' => 'Fichier uploadé avec succès ! Traitement en cours...',
               'process_id' => $process_id,
               'job_id' => $api_result['job_id'],
               'file_name' => $file_name,
               'preset_applied' => $api_result['preset_applied'],
               'remaining_tokens' => Token_Manager::get_user_balance($user_id)
           ));
       }
   } else {
       wp_send_json_error('Erreur lors de la sauvegarde du fichier');
   }
}

/**
* Vérification du statut d'un traitement
* 
* @since 1.0.0
* @return void
*/
function iris_check_process_status() {
   if (!wp_verify_nonce($_POST['nonce'], 'iris_upload_nonce')) {
       wp_die('Erreur de sécurité');
   }
   
   $user_id = get_current_user_id();
   if (!$user_id) {
       wp_send_json_error('Utilisateur non connecté');
   }
   
   $process_id = intval($_POST['process_id']);
   
   global $wpdb;
   $table_name = $wpdb->prefix . 'iris_image_processes';
   
   $process = $wpdb->get_row($wpdb->prepare(
       "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
       $process_id, $user_id
   ));
   
   if (!$process) {
       wp_send_json_error('Traitement non trouvé');
   }
   
   wp_send_json_success(array(
       'status' => $process->status,
       'process_id' => $process->id,
       'created_at' => $process->created_at,
       'updated_at' => $process->updated_at
   ));
}

/**
* Gestionnaire de téléchargement sécurisé
* 
* @since 1.0.0
* @return void
*/
function iris_handle_download() {
   $process_id = intval($_GET['process_id']);
   $nonce = $_GET['nonce'];
   
   if (!wp_verify_nonce($nonce, 'iris_download_' . $process_id)) {
       wp_die('Erreur de sécurité');
   }
   
   $user_id = get_current_user_id();
   if (!$user_id) {
       wp_die('Utilisateur non connecté');
   }
   
   global $wpdb;
   $table_name = $wpdb->prefix . 'iris_image_processes';
   
   $process = $wpdb->get_row($wpdb->prepare(
       "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
       $process_id, $user_id
   ));
   
   if (!$process || !file_exists($process->processed_file_path)) {
       wp_die('Fichier non trouvé');
   }
   
   // Téléchargement du fichier
   header('Content-Type: application/octet-stream');
   header('Content-Disposition: attachment; filename="processed_' . basename($process->original_filename) . '"');
   header('Content-Length: ' . filesize($process->processed_file_path));
   
   readfile($process->processed_file_path);
   exit;
}

/**
* Styles CSS pour la zone d'upload (MODIFIÉ v1.1.0)
* 
* @since 1.0.0
* @since 1.1.0 Ajout styles pour sélection preset
* @return string CSS complet
*/
function iris_get_upload_styles() {
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
   
   /* NOUVEAU v1.1.0 - Styles pour sélection preset */
   .iris-preset-selection {
       background: #0C2D39;
       color: #F4F4F2;
       padding: 20px;
       border-radius: 12px;
       margin-bottom: 20px;
       font-family: "Lato", sans-serif;
   }
   
   .iris-preset-selection h4 {
       color: #3de9f4;
       margin: 0 0 15px 0;
       font-size: 18px;
       font-weight: 600;
   }
   
   .iris-preset-selection select {
       width: 100%;
       padding: 12px 16px;
       border: 2px solid #124C58;
       border-radius: 8px;
       background: #15697B;
       color: #F4F4F2;
       font-size: 14px;
       font-family: "Lato", sans-serif;
       margin-bottom: 10px;
   }
   
   .iris-preset-selection select:focus {
       outline: none;
       border-color: #3de9f4;
   }
   
   .iris-preset-selection .description {
       color: #ccc;
       font-size: 13px;
       margin: 0;
       font-style: italic;
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
   
   .iris-success h4,
   .iris-error h4 {
       margin: 0 0 10px 0;
       font-size: 18px;
   }
   
   .iris-success p,
   .iris-error p {
       margin: 5px 0;
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
   
   .iris-history-items {
       display: flex;
       flex-direction: column;
       gap: 15px;
   }
   
   .iris-history-item {
       background: #15697B;
       padding: 15px;
       border-radius: 8px;
       display: flex;
       justify-content: space-between;
       align-items: center;
   }
   
   .iris-history-info {
       flex: 1;
   }
   
   .iris-history-info strong {
       color: #3de9f4;
       display: block;
       margin-bottom: 5px;
   }
   
   .iris-status {
       background: #F05A28;
       color: white;
       padding: 3px 8px;
       border-radius: 12px;
       font-size: 12px;
       margin-right: 10px;
   }
   
   .iris-date {
       color: #ccc;
       font-size: 14px;
   }
   
   .iris-download-btn {
       background: #3de9f4;
       color: #0C2D39;
       padding: 8px 15px;
       border-radius: 5px;
       text-decoration: none;
       font-weight: bold;
       transition: all 0.3s ease;
   }
   
   .iris-download-btn:hover {
       background: #2bc9d4;
       text-decoration: none;
       color: #0C2D39;
   }
   
   @media (max-width: 768px) {
       #iris-upload-container {
           padding: 10px;
       }
       
       .iris-drop-zone {
           padding: 20px 10px;
       }
       
       .iris-history-item {
           flex-direction: column;
           align-items: flex-start;
           gap: 10px;
       }
       
       .iris-preset-selection select {
           font-size: 16px; /* Éviter le zoom sur mobile */
       }
   }
   </style>';
}

/**
* JavaScript pour la zone d'upload (MODIFIÉ v1.1.0)
* 
* @since 1.0.0
* @since 1.1.0 Ajout gestion preset dans formulaire
* @return string JavaScript complet
*/
function iris_get_upload_scripts() {
   return '<script type="text/javascript">
   jQuery(document).ready(function($) {
       console.log("🚀 Iris Upload v1.1.0 - Avec support presets JSON");
       
       var dropZone = $("#iris-drop-zone");
       var fileInput = $("#iris-file-input");
       var filePreview = $("#iris-file-preview");
       var fileName = $("#iris-file-name");
       var fileSize = $("#iris-file-size");
       var removeBtn = $("#iris-remove-file");
       var uploadBtn = $("#iris-upload-btn");
       var uploadForm = $("#iris-upload-form");
       var result = $("#iris-upload-result");
       var presetSelect = $("#iris-preset-select"); // NOUVEAU v1.1.0
       
       var selectedFile = null;
       
       console.log("Éléments:", {
           dropZone: dropZone.length,
           fileInput: fileInput.length,
           presetSelect: presetSelect.length
       });
       
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
           console.log("📁 Drag over");
       });
       
       dropZone.on("dragleave", function(e) {
           e.preventDefault();
           $(this).css("background-color", "rgba(60, 233, 244, 0.1)");
       });
       
       dropZone.on("drop", function(e) {
           e.preventDefault();
           $(this).css("background-color", "rgba(60, 233, 244, 0.1)");
           console.log("📥 Drop détecté");
           
           var files = e.originalEvent.dataTransfer.files;
           if (files && files.length > 0) {
               handleFile(files[0]);
           }
       });
       
       // Gestion changement de preset (NOUVEAU v1.1.0)
       presetSelect.on("change", function() {
           var selectedPreset = $(this).find("option:selected").text();
           console.log("🎨 Preset sélectionné:", selectedPreset);
           
           // Mettre à jour le texte du bouton si un preset spécifique est choisi
           if ($(this).val()) {
               uploadBtn.find(".iris-btn-text").text("Traiter avec preset (1 jeton)");
           } else {
               uploadBtn.find(".iris-btn-text").text("Traiter l\'image (1 jeton)");
           }
       });
       
       // Traitement fichier
       function handleFile(file) {
           console.log("🔍 Fichier:", file.name);
           
           var ext = file.name.split(".").pop().toLowerCase();
           var allowed = ["jpg", "jpeg", "tif", "tiff", "cr3", "nef", "arw", "raw", "dng", "orf", "raf", "rw2"];
           
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
           
           // Réinitialiser le texte du bouton
           uploadBtn.find(".iris-btn-text").text("Traiter l\'image (1 jeton)");
           console.log("🗑️ Fichier supprimé");
       });
       
       // Submit formulaire (MODIFIÉ v1.1.0)
       uploadForm.on("submit", function(e) {
           e.preventDefault();
           
           if (!selectedFile) {
               alert("Sélectionnez un fichier");
               return;
           }
           
           var selectedPresetId = presetSelect.val();
           console.log("🚀 Upload:", selectedFile.name, "avec preset ID:", selectedPresetId);
           
           var originalText = uploadBtn.find(".iris-btn-text").text();
           uploadBtn.prop("disabled", true);
           uploadBtn.find(".iris-btn-text").hide();
           uploadBtn.find(".iris-btn-loading").show();
           
           var formData = new FormData();
           formData.append("action", "iris_upload_image");
           formData.append("nonce", iris_ajax.nonce);
           formData.append("image_file", selectedFile);
           
           // Ajouter le preset sélectionné (NOUVEAU v1.1.0)
           if (selectedPresetId) {
               formData.append("preset_id", selectedPresetId);
           }
           
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
                       
                       // Afficher info preset si appliqué (NOUVEAU v1.1.0)
                       if (resp.data.preset_applied) {
                           successMsg += "<p>🎨 Preset appliqué avec succès</p>";
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
       
       console.log("✅ Iris Upload v1.1.0 initialisé avec support presets !");
   });
   </script>';
}