jQuery(document).ready(function($) {
    console.log('Iris Upload JS chargé - version corrigée');
    
    // Déclarations avec var au lieu de const pour éviter les erreurs de hoisting
    var $dropZone = $('#iris-drop-zone');
    var $fileInput = $('#iris-file-input');
    var $filePreview = $('#iris-file-preview');
    var $fileName = $('#iris-file-name');
    var $fileSize = $('#iris-file-size');
    var $removeBtn = $('#iris-remove-file');
    var $uploadBtn = $('#iris-upload-btn');
    var $uploadForm = $('#iris-upload-form');
    var $result = $('#iris-upload-result');
    var $tokenBalance = $('#token-balance');
    
    var selectedFile = null;
    
    console.log('Éléments trouvés:', {
        dropZone: $dropZone.length,
        fileInput: $fileInput.length,
        uploadBtn: $uploadBtn.length,
        uploadForm: $uploadForm.length
    });
    
    // Empêcher le comportement par défaut du navigateur
    $(document).on('dragover drop', function(e) {
        e.preventDefault();
        return false;
    });
    
    // Gestion du clic sur la zone de drop
    if ($dropZone.length > 0) {
        $dropZone.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Zone cliquée - ouverture du sélecteur');
            $fileInput.trigger('click');
        });
        
        // Gestion du drag & drop
        $dropZone.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('iris-drag-over');
            console.log('Drag over détecté');
        });
        
        $dropZone.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('iris-drag-over');
        });
        
        $dropZone.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('iris-drag-over');
            
            console.log('Drop détecté !');
            var files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelection(files[0]);
            }
        });
    } else {
        console.error('Zone de drop non trouvée !');
    }
    
    // Gestion de la sélection de fichier
    if ($fileInput.length > 0) {
        $fileInput.on('change', function() {
            console.log('Fichier sélectionné via input');
            if (this.files && this.files.length > 0) {
                handleFileSelection(this.files[0]);
            }
        });
    } else {
        console.error('Input file non trouvé !');
    }
    
    // Fonction de gestion de fichier
    function handleFileSelection(file) {
        console.log('Traitement du fichier:', file.name, 'Taille:', formatFileSize(file.size));
        
        // Vérifier l'extension
        var allowedExtensions = ['jpg', 'jpeg', 'tif', 'tiff', 'cr3', 'nef', 'arw', 'raw', 'dng', 'orf', 'raf', 'rw2'];
        var fileExtension = file.name.split('.').pop().toLowerCase();
        
        if (allowedExtensions.indexOf(fileExtension) === -1) {
            alert('Format de fichier non supporté.\nFormats acceptés : ' + allowedExtensions.join(', ').toUpperCase());
            return false;
        }
        
        // Vérifier la taille si définie
        if (typeof iris_ajax !== 'undefined' && iris_ajax.max_file_size && file.size > iris_ajax.max_file_size) {
            alert('Fichier trop volumineux. Taille maximum : ' + formatFileSize(iris_ajax.max_file_size));
            return false;
        }
        
        selectedFile = file;
        
        // Afficher la prévisualisation
        if ($fileName.length > 0) $fileName.text(file.name);
        if ($fileSize.length > 0) $fileSize.text(formatFileSize(file.size));
        if ($filePreview.length > 0) $filePreview.show();
        if ($dropZone.length > 0) $dropZone.addClass('iris-file-selected');
        if ($uploadBtn.length > 0) $uploadBtn.prop('disabled', false);
        
        console.log('Fichier accepté et prêt:', file.name);
        return true;
    }
    
    // Supprimer le fichier
    if ($removeBtn.length > 0) {
        $removeBtn.on('click', function(e) {
            e.preventDefault();
            selectedFile = null;
            if ($fileInput.length > 0) $fileInput.val('');
            if ($filePreview.length > 0) $filePreview.hide();
            if ($dropZone.length > 0) $dropZone.removeClass('iris-file-selected');
            if ($uploadBtn.length > 0) $uploadBtn.prop('disabled', true);
            console.log('Fichier supprimé');
        });
    }
    
    // Soumission du formulaire
    if ($uploadForm.length > 0) {
        $uploadForm.on('submit', function(e) {
            e.preventDefault();
            
            if (!selectedFile) {
                alert('Veuillez sélectionner un fichier');
                return false;
            }
            
            console.log('Début de l\'upload pour:', selectedFile.name);
            
            // Vérifier que iris_ajax est défini
            if (typeof iris_ajax === 'undefined') {
                console.error('iris_ajax non défini !');
                alert('Erreur de configuration. Veuillez recharger la page.');
                return false;
            }
            
            // Affichage du loading
            if ($uploadBtn.length > 0) {
                $uploadBtn.prop('disabled', true);
                $uploadBtn.find('.iris-btn-text').hide();
                $uploadBtn.find('.iris-btn-loading').show();
            }
            
            // Préparer FormData
            var formData = new FormData();
            formData.append('action', 'iris_upload_image');
            formData.append('nonce', iris_ajax.nonce);
            formData.append('image_file', selectedFile);
            
            console.log('Envoi vers:', iris_ajax.ajax_url);
            
            // Envoyer via AJAX
            $.ajax({
                url: iris_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 60000, // 60 secondes
                success: function(response) {
                    console.log('Réponse serveur:', response);
                    
                    if (response && response.success) {
                        var successHtml = '<div class="iris-success">' +
                            '<h4>✅ ' + (response.data.message || 'Upload réussi') + '</h4>' +
                            '<p>Jetons restants : ' + (response.data.remaining_tokens || '?') + '</p>' +
                            '<p>ID de traitement : ' + (response.data.process_id || '?') + '</p>' +
                            '</div>';
                        
                        if ($result.length > 0) $result.html(successHtml).show();
                        if ($tokenBalance.length > 0) $tokenBalance.text(response.data.remaining_tokens || 0);
                        
                        // Réinitialiser le formulaire
                        if ($removeBtn.length > 0) $removeBtn.trigger('click');
                        
                        // Recharger après 3 secondes pour voir l'historique
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                        
                    } else {
                        var errorMsg = response && response.data ? response.data : 'Erreur inconnue';
                        var errorHtml = '<div class="iris-error">' +
                            '<h4>❌ Erreur</h4>' +
                            '<p>' + errorMsg + '</p>' +
                            '</div>';
                        
                        if ($result.length > 0) $result.html(errorHtml).show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erreur AJAX:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    
                    var errorHtml = '<div class="iris-error">' +
                        '<h4>❌ Erreur de connexion</h4>' +
                        '<p>Status: ' + status + '</p>' +
                        '<p>Erreur: ' + error + '</p>' +
                        '</div>';
                    
                    if ($result.length > 0) $result.html(errorHtml).show();
                },
                complete: function() {
                    // Réactiver le bouton
                    if ($uploadBtn.length > 0) {
                        $uploadBtn.prop('disabled', false);
                        $uploadBtn.find('.iris-btn-text').show();
                        $uploadBtn.find('.iris-btn-loading').hide();
                    }
                }
            });
            
            return false;
        });
    } else {
        console.error('Formulaire d\'upload non trouvé !');
    }
    
    // Fonction utilitaire pour formater la taille
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    console.log('JavaScript Iris initialisé avec succès !');
});