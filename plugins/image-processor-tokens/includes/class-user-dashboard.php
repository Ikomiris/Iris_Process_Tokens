<?php
if (!defined('ABSPATH')) {
    exit;
}

class User_Dashboard {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        add_shortcode('user_token_balance', array($this, 'display_token_balance'));
        add_shortcode('token_history', array($this, 'display_token_history'));
        add_shortcode('iris_process_page', array($this, 'display_process_page'));
    }
    
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
        
        ob_start();
        ?>
        <div class="iris-process-page">
            <h2>Traitement d'image Iris Process</h2>
            <div class="token-info-small">
                Jetons disponibles : <strong><?php echo $balance; ?></strong>
            </div>
            
            <form id="iris-upload-form" enctype="multipart/form-data">
                <div class="upload-area">
                    <input type="file" id="iris-image-input" name="image" accept=".cr3,.nef,.arw,.jpg,.jpeg,.tif,.tiff" required>
                    <label for="iris-image-input">
                        <div class="upload-placeholder">
                            <p>Cliquez pour sélectionner une image</p>
                            <small>Formats acceptés : CR3, NEF, ARW, JPG, TIF</small>
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
        }
        
        .token-info-small {
            background: #f0f9ff;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
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
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Veuillez sélectionner une image');
                return;
            }
            
            // Afficher la barre de progression
            document.getElementById('iris-progress').style.display = 'block';
            document.getElementById('iris-result').style.display = 'none';
            
            // Simuler le traitement (à remplacer par l'appel API réel)
            setTimeout(function() {
                document.getElementById('iris-progress').style.display = 'none';
                document.getElementById('iris-result').innerHTML = '<p>Image traitée avec succès ! <a href="#" download>Télécharger</a></p>';
                document.getElementById('iris-result').style.display = 'block';
            }, 3000);
        });
        </script>
        <?php
        return ob_get_clean();
    }
}