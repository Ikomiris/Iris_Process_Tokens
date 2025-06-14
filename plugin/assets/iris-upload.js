/**
 * Fichier : wp-content/plugins/image-processor-tokens/assets/iris-upload.js
 * JavaScript pour la zone d'upload Iris Process
 */

jQuery(document).ready(function($) {
    let selectedFile = null;
    
    // Création de l'icône de téléchargement personnalisée
    $dropZone.find('.iris-upload-icon').html('⬇️');
    const $dropZone = $('#iris-drop-zone');
    const $fileInput = $('#iris-file-input');
    const $uploadForm = $('#iris-upload-form');
    const $filePreview = $('#iris-file-preview');
    const $uploadBtn = $('#iris-upload-btn');
    const $uploadResult = $('#iris-upload-result');
    const $tokenBalance = $('#token-balance');
    
    // Gestion du drag & drop
    $dropZone.on('dragover dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('iris-drag-over');
    });
    
    $dropZone.on('dragleave dragend', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('iris-drag-over');
    });
    
    $dropZone.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('iris-drag-over');
        
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            handleFileSelection(files[0]);
        }
    });
    
    // Clic sur la zone de drop
    $dropZone.on('click', function() {
        $fileInput.click();
    });
    
    // Sélection de fichier via input
    $fileInput.on('change', function() {
        if (this.files.length > 0) {
            handleFileSelection(this.files[0]);
        }
    });
    
    // Suppression du fichier sélectionné
    $('#iris-remove-file').on('click', function() {
        resetFileSelection();
    });
    
    // Soumission du formulaire
    $uploadForm.on('submit', function(e) {
        e.preventDefault();
        
        if (!selectedFile) {
            showMessage('Veuillez sélectionner un fichier', 'error');
            return;
        }
        
        uploadFile();
    });
    
    /**
     * Gestion de la sélection de fichier
     */
    function handleFileSelection(file) {
        // Vérification de la taille
        if (file.size > iris_ajax.max_file_size) {
            showMessage('Le fichier est trop volumineux. Taille maximum : ' + formatFileSize(iris_ajax.max_file_size), 'error');
            return;
        }
        
        // Vérification du type
        const allowedExtensions = ['cr3', 'nef', 'arw', 'jpg', 'jpeg', 'tif', 'tiff'];
        const extension = file.name.split('.').pop().toLowerCase();
        
        if (!allowedExtensions.includes(extension)) {
            showMessage('Format de fichier non supporté. Formats acceptés : CR3, NEF, ARW, JPG, TIF', 'error');
            return;
        }
        
        selectedFile = file;
        
        // Affichage des informations du fichier
        $('#iris-file-name').text(file.name);
        $('#iris-file-size').text(formatFileSize(file.size));
        
        $dropZone.hide();
        $filePreview.show();
        $uploadBtn.prop('disabled', false);
        
        // Prévisualisation de l'image (si JPG/TIF)
        if (['jpg', 'jpeg', 'tif', 'tiff'].includes(extension)) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = '<img src="' + e.target.result + '" style="max-width: 200px; max-height: 200px; object-fit: cover; border-radius: 4px; margin-top: 10px;">';
                $filePreview.append(preview);
            };
            reader.readAsDataURL(file);
        }
    }
    
    /**
     * Réinitialisation de la sélection
     */
    function resetFileSelection() {
        selectedFile = null;
        $fileInput.val('');
        $filePreview.hide();
        $dropZone.show();
        $uploadBtn.prop('disabled', true);
        hideMessage();
    }
    
    /**
     * Upload du fichier
     */
    function uploadFile() {
        const formData = new FormData();
        formData.append('action', 'iris_upload_image');
        formData.append('nonce', iris_ajax.nonce);
        formData.append('image_file', selectedFile);
        
        // État de chargement
        $uploadBtn.prop('disabled', true);
        $('.iris-btn-text').hide();
        $('.iris-btn-loading').show();
        
        $.ajax({
            url: iris_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showMessage('Fichier uploadé avec succès ! Traitement en cours...', 'success');
                    
                    // Mise à jour du solde de jetons
                    $tokenBalance.text(response.data.remaining_tokens);
                    
                    // Réinitialisation du formulaire
                    resetFileSelection();
                    
                    // Rechargement de l'historique
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                    
                } else {
                    showMessage(response.data || 'Erreur lors de l\'upload', 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Erreur de connexion : ' + error, 'error');
            },
            complete: function() {
                $uploadBtn.prop('disabled', false);
                $('.iris-btn-text').show();
                $('.iris-btn-loading').hide();
            }
        });
    }
    
    /**
     * Affichage des messages
     */
    function showMessage(message, type) {
        const alertClass = type === 'success' ? 'iris-alert-success' : 'iris-alert-error';
        const html = '<div class="iris-alert ' + alertClass + '">' + message + '</div>';
        
        $uploadResult.html(html).show();
        
        // Masquage automatique après 5 secondes pour les messages de succès
        if (type === 'success') {
            setTimeout(hideMessage, 5000);
        }
    }
    
    /**
     * Masquage des messages
     */
    function hideMessage() {
        $uploadResult.hide();
    }
    
    /**
     * Formatage de la taille de fichier
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    /**
     * Polling pour vérifier le statut du traitement
     */
    function checkProcessStatus(processId) {
        $.ajax({
            url: iris_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iris_check_process_status',
                nonce: iris_ajax.nonce,
                process_id: processId
            },
            success: function(response) {
                if (response.success) {
                    const status = response.data.status;
                    
                    if (status === 'completed') {
                        showMessage('Traitement terminé ! Vous pouvez télécharger votre image.', 'success');
                        location.reload();
                    } else if (status === 'error') {
                        showMessage('Erreur lors du traitement de l\'image.', 'error');
                    } else if (status === 'processing') {
                        // Continuer le polling
                        setTimeout(function() {
                            checkProcessStatus(processId);
                        }, 3000);
                    }
                }
            }
        });
    }
    
    // Fonction pour vérifier le statut (si nécessaire)
    window.irisCheckStatus = checkProcessStatus;
});