jQuery(document).ready(function($) {
    const strings = iris_preset_ajax.strings;
    
    // Upload de preset
    $('#iris-preset-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        const fileInput = $('#preset_file')[0];
        
        if (!fileInput.files[0]) {
            alert('Veuillez sélectionner un fichier');
            return;
        }
        
        // Validation du type de fichier
        const allowedTypes = ['.xmp', '.json'];
        const fileName = fileInput.files[0].name.toLowerCase();
        const isValidType = allowedTypes.some(type => fileName.endsWith(type));
        
        if (!isValidType) {
            alert('Type de fichier non supporté. Utilisez .xmp ou .json');
            return;
        }
        
        // Préparation des données
        formData.append('action', 'iris_upload_preset');
        formData.append('nonce', iris_preset_ajax.nonce);
        formData.append('preset_file', fileInput.files[0]);
        formData.append('preset_name', $('#preset_name').val());
        formData.append('camera_models', $('#camera_models').val());
        formData.append('description', $('#description').val());
        
        // Affichage du loader
        const $submitBtn = $(this).find('button[type="submit"]');
        const $progress = $('#iris-upload-progress');
        
        $submitBtn.prop('disabled', true);
        $progress.show();
        
        // Upload AJAX
        $.ajax({
            url: iris_preset_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(strings.upload_success + '\n\nPreset: ' + response.data.preset_name);
                    
                    // Reset du formulaire
                    $('#iris-preset-upload-form')[0].reset();
                    
                    // Redirection vers la liste
                    window.location.href = '?page=iris-presets&tab=list';
                } else {
                    alert(strings.upload_error + '\n\n' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert(strings.upload_error + '\n\n' + error);
            },
            complete: function() {
                $submitBtn.prop('disabled', false);
                $progress.hide();
            }
        });
    });
    
    // Test d'un preset
    $('.iris-test-preset').on('click', function(e) {
        e.preventDefault();
        
        const presetId = $(this).data('preset-id');
        const $row = $(this).closest('tr');
        
        // Animation de chargement
        $row.addClass('testing');
        
        $.ajax({
            url: iris_preset_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iris_test_preset',
                nonce: iris_preset_ajax.nonce,
                preset_id: presetId
            },
            success: function(response) {
                if (response.success) {
                    showTestResult(presetId, true, response.data.message);
                } else {
                    showTestResult(presetId, false, response.data);
                }
            },
            error: function() {
                showTestResult(presetId, false, 'Erreur de connexion');
            },
            complete: function() {
                $row.removeClass('testing');
            }
        });
    });
    
    // Suppression d'un preset
    $('.iris-delete-preset').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm(strings.delete_confirm)) {
            return;
        }
        
        const presetId = $(this).data('preset-id');
        const $row = $(this).closest('tr');
        
        $.ajax({
            url: iris_preset_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iris_delete_preset',
                nonce: iris_preset_ajax.nonce,
                preset_id: presetId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert('Erreur lors de la suppression: ' + response.data);
                }
            },
            error: function() {
                alert('Erreur de connexion lors de la suppression');
            }
        });
    });
    
    // Test de tous les presets
    $('#iris-test-all-presets').on('click', function() {
        const $btn = $(this);
        const presetIds = [];
        
        $('.iris-test-preset').each(function() {
            presetIds.push($(this).data('preset-id'));
        });
        
        if (presetIds.length === 0) {
            alert('Aucun preset à tester');
            return;
        }
        
        $btn.prop('disabled', true).text('Test en cours...');
        
        let completedTests = 0;
        let results = [];
        
        presetIds.forEach(function(presetId) {
            $.ajax({
                url: iris_preset_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'iris_test_preset',
                    nonce: iris_preset_ajax.nonce,
                    preset_id: presetId
                },
                success: function(response) {
                    results.push({
                        preset: presetId,
                        success: response.success,
                        message: response.success ? response.data.message : response.data
                    });
                },
                error: function() {
                    results.push({
                        preset: presetId,
                        success: false,
                        message: 'Erreur de connexion'
                    });
                },
                complete: function() {
                    completedTests++;
                    
                    if (completedTests === presetIds.length) {
                        displayAllTestResults(results);
                        $btn.prop('disabled', false).text('Tester tous les presets');
                    }
                }
            });
        });
    });
    
    function showTestResult(presetId, success, message) {
        const $results = $('#iris-test-results');
        const $content = $results.find('.iris-test-content');
        
        const resultHtml = `
            <div class="iris-test-result ${success ? 'success' : 'error'}">
                <strong>${presetId}:</strong> ${message}
            </div>
        `;
        
        $content.html(resultHtml);
        $results.show();
        
        // Auto-hide après 5 secondes
        setTimeout(() => {
            $results.fadeOut();
        }, 5000);
    }
    
    function displayAllTestResults(results) {
        const $results = $('#iris-test-results');
        const $content = $results.find('.iris-test-content');
        
        let html = '<h4>Résultats des tests :</h4>';
        
        results.forEach(function(result) {
            html += `
                <div class="iris-test-result ${result.success ? 'success' : 'error'}">
                    <strong>${result.preset}:</strong> ${result.message}
                </div>
            `;
        });
        
        const successCount = results.filter(r => r.success).length;
        html += `<p><strong>Résumé:</strong> ${successCount}/${results.length} presets valides</p>`;
        
        $content.html(html);
        $results.show();
    }
    
    // Actions des paramètres
    $('#iris-clear-preset-cache').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).text('Nettoyage...');
        
        $.ajax({
            url: iris_preset_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iris_clear_preset_cache',
                nonce: iris_preset_ajax.nonce
            },
            success: function(response) {
                alert(response.success ? 'Cache vidé avec succès' : 'Erreur: ' + response.data);
            },
            complete: function() {
                $btn.prop('disabled', false).text('Vider le cache');
            }
        });
    });
});