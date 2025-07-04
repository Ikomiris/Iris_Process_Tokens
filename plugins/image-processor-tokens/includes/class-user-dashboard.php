<?php
/**
 * Dashboard utilisateur
 * 
 * @package IrisProcessTokens
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour le dashboard utilisateur
 * 
 * @since 1.0.0
 */
class User_Dashboard {
    
    /**
     * Constructeur
     * 
     * @since 1.0.0
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialisation
     * 
     * @since 1.0.0
     * @return void
     */
    public function init() {
        add_shortcode('user_token_balance', array($this, 'display_token_balance'));
        add_shortcode('token_history', array($this, 'display_token_history'));
        add_shortcode('iris_process_page', array($this, 'display_process_page'));
    }
    
    /**
     * Afficher le solde de jetons
     * 
     * @since 1.0.0
     * @param array $atts Attributs du shortcode
     * @return string HTML du solde
     */
    public function display_token_balance($atts) {
        if (!is_user_logged_in()) {
            return '<p>Vous devez être connecté pour voir votre solde.</p>';
        }
        
        $user_id = get_current_user_id();
        $balance = Token_Manager::get_user_balance($user_id);
        
        ob_start();
        ?>
        <div class="iris-process-token-balance">
            <div class="token-info">
                <h3>Vos jetons Iris Process</h3>
                <div class="balance-display">
                    <span class="token-count"><?php echo $balance; ?></span>
                    <span class="token-label">jetons disponibles</span>
                </div>
            </div>
            <div class="token-actions">
                <a href="/boutique" class="btn btn-primary">Acheter des jetons</a>
                <?php if ($balance > 0) : ?>
                    <a href="/iris-process" class="btn btn-secondary">Traiter une image</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Afficher l'historique des jetons
     * 
     * @since 1.0.0
     * @param array $atts Attributs du shortcode
     * @return string HTML de l'historique
     */
    public function display_token_history($atts) {
        if (!is_user_logged_in()) {
            return '<p>Vous devez être connecté pour voir votre historique.</p>';
        }
        
        $atts = shortcode_atts(array(
            'limit' => 10
        ), $atts);
        
        $user_id = get_current_user_id();
        $transactions = Token_Manager::get_user_transactions($user_id, $atts['limit']);
        
        if (empty($transactions)) {
            return '<p>Aucune transaction trouvée.</p>';
        }
        
        ob_start();
        ?>
        <div class="token-history">
            <h4>Historique des jetons</h4>
            <table class="token-transactions">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Jetons</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction) : ?>
                    <tr class="transaction-<?php echo $transaction->transaction_type; ?>">
                        <td><?php echo date('d/m/Y H:i', strtotime($transaction->created_at)); ?></td>
                        <td><?php echo ucfirst($transaction->transaction_type); ?></td>
                        <td>
                            <?php if ($transaction->tokens_amount > 0) : ?>
                                <span class="positive">+<?php echo $transaction->tokens_amount; ?></span>
                            <?php else : ?>
                                <span class="negative"><?php echo $transaction->tokens_amount; ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($transaction->description); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Afficher la page de traitement d'images
     * 
     * @since 1.0.0
     * @param array $atts Attributs du shortcode
     * @return string HTML de la page
     */
    public function display_process_page($atts) {
        if (!is_user_logged_in()) {
            return '<p>Vous devez être connecté pour utiliser Iris Process.</p>';
        }
        
        $user_id = get_current_user_id();
        $balance = Token_Manager::get_user_balance($user_id);
        
        if ($balance < 1) {
            return '<div class="iris-no-tokens">
                <p>Vous n\'avez pas assez de jetons pour traiter une image.</p>
                <a href="/boutique" class="btn btn-primary">Acheter des jetons</a>
            </div>';
        }
        
        // Récupérer les presets disponibles
        $available_presets = iris_list_available_presets();
        
        ob_start();
        ?>
        <div class="iris-process-page">
            <h2>Traitement d'image Iris Process</h2>
            <div class="token-info-small">
                Jetons disponibles : <strong><?php echo $balance; ?></strong>
            </div>
            
            <!-- Sélection du preset (NOUVEAU v1.1.0) -->
            <?php if (!empty($available_presets)): ?>
            <div class="preset-selection">
                <label for="iris-preset-select">🎨 Choisir un preset de traitement :</label>
                <select id="iris-preset-select" name="preset_id">
                    <option value="">Traitement par défaut</option>
                    <?php foreach ($available_presets as $preset): ?>
                        <option value="<?php echo esc_attr($preset->id); ?>" <?php echo $preset->is_default ? 'selected' : ''; ?>>
                            <?php echo esc_html($preset->preset_name); ?>
                            <?php if ($preset->is_default): ?>(Par défaut)<?php endif; ?>
                            (<?php echo $preset->usage_count; ?> utilisations)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Choisissez un preset pour appliquer des réglages spécifiques</p>
            </div>
            <?php endif; ?>
            
            <form id="iris-upload-form" enctype="multipart/form-data">
                <div class="upload-area">
                    <input type="file" id="iris-image-input" name="image" accept=".cr3,.nef,.arw,.jpg,.jpeg,.tif,.tiff,.raw,.dng,.orf,.raf,.rw2" required>
                    <label for="iris-image-input">
                        <div class="upload-placeholder">
                            <p>Cliquez pour sélectionner une image</p>
                            <small>Formats acceptés : CR3, NEF, ARW, JPG, TIF, RAW, DNG, ORF, RAF, RW2</small>
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
        
        <style>
        .iris-process-page {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            font-family: 'Lato', sans-serif;
        }
        
        .token-info-small {
            background: #f0f9ff;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .preset-selection {
            background: #0C2D39;
            color: #F4F4F2;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .preset-selection label {
            display: block;
            margin-bottom: 10px;
            color: #3de9f4;
            font-weight: bold;
        }
        
        .preset-selection select {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #124C58;
            background: #15697B;
            color: #F4F4F2;
            margin-bottom: 5px;
        }
        
        .preset-selection .description {
            color: #ccc;
            font-size: 13px;
            margin: 0;
            font-style: italic;
        }
        
        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            margin-bottom: 20px;
            transition: border-color 0.3s;
        }
        
        .upload-area:hover {
            border-color: #007cba;
        }
        
        .upload-area input[type="file"] {
            display: none;
        }
        
        .upload-placeholder {
            cursor: pointer;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn:hover {
            background: #005a87;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: #007cba;
            width: 0%;
            transition: width 0.3s;
        }
        </style>
        
        <script>
        document.getElementById('iris-upload-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('iris-image-input');
            const presetSelect = document.getElementById('iris-preset-select');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Veuillez sélectionner une image');
                return;
            }
            
            // Afficher la barre de progression
            document.getElementById('iris-progress').style.display = 'block';
            document.getElementById('iris-result').style.display = 'none';
            
            // Préparer les données du formulaire
            const formData = new FormData();
            formData.append('action', 'iris_upload_image');
            formData.append('nonce', '<?php echo wp_create_nonce('iris_upload_nonce'); ?>');
            formData.append('image_file', file);
            
            // Ajouter le preset sélectionné si disponible
            if (presetSelect && presetSelect.value) {
                formData.append('preset_id', presetSelect.value);
            }
            
            // Envoyer via AJAX
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('iris-progress').style.display = 'none';
                
                if (data.success) {
                    let resultHtml = '<div style="background:#28a745;color:white;padding:15px;border-radius:8px;">';
                    resultHtml += '<h4>✅ ' + data.data.message + '</h4>';
                    resultHtml += '<p>Jetons restants: ' + data.data.remaining_tokens + '</p>';
                    if (data.data.preset_applied) {
                        resultHtml += '<p>🎨 Preset appliqué avec succès</p>';
                    }
                    resultHtml += '</div>';
                    
                    document.getElementById('iris-result').innerHTML = resultHtml;
                } else {
                    document.getElementById('iris-result').innerHTML = 
                        '<div style="background:#dc3545;color:white;padding:15px;border-radius:8px;">' +
                        '<h4>❌ Erreur</h4><p>' + (data.data || 'Erreur inconnue') + '</p></div>';
                }
                
                document.getElementById('iris-result').style.display = 'block';
                
                // Recharger après 3 secondes
                setTimeout(() => location.reload(), 3000);
            })
            .catch(error => {
                document.getElementById('iris-progress').style.display = 'none';
                document.getElementById('iris-result').innerHTML = 
                    '<div style="background:#dc3545;color:white;padding:15px;border-radius:8px;">' +
                    '<h4>❌ Erreur de connexion</h4><p>' + error.message + '</p></div>';
                document.getElementById('iris-result').style.display = 'block';
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
}

// Initialiser la classe
new User_Dashboard();