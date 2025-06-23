<div class="iris-preset-upload">
    <div class="card">
        <h2>Uploader un nouveau preset</h2>
        <p>Uploadez un fichier preset Lightroom (.xmp) ou un preset JSON personnalisé.</p>
        
        <form id="iris-preset-upload-form" enctype="multipart/form-data">
            <?php wp_nonce_field('iris_preset_nonce', 'nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="preset_file">Fichier preset</label>
                    </th>
                    <td>
                        <input type="file" id="preset_file" name="preset_file" 
                               accept=".xmp,.json" required />
                        <p class="description">
                            Formats supportés : .xmp (Lightroom/Camera Raw), .json (preset personnalisé)
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="preset_name">Nom du preset</label>
                    </th>
                    <td>
                        <input type="text" id="preset_name" name="preset_name" 
                               class="regular-text" placeholder="Nom automatique si vide" />
                        <p class="description">
                            Laissez vide pour utiliser le nom du fichier
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="camera_models">Modèles de caméra</label>
                    </th>
                    <td>
                        <input type="text" id="camera_models" name="camera_models" 
                               class="regular-text" placeholder="Canon EOS R, Canon EOS R5, ..." />
                        <p class="description">
                            Modèles compatibles, séparés par des virgules. Laisser vide pour tous les modèles.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="description">Description</label>
                    </th>
                    <td>
                        <textarea id="description" name="description" 
                                  class="large-text" rows="3"
                                  placeholder="Description optionnelle du preset"></textarea>
                    </td>
                </tr>
            </table>
            
            <div class="iris-upload-actions">
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-upload"></span>
                    Uploader le preset
                </button>
                <div id="iris-upload-progress" style="display: none;">
                    <div class="iris-progress-bar">
                        <div class="iris-progress-fill"></div>
                    </div>
                    <p>Upload en cours...</p>
                </div>
            </div>
        </form>
    </div>
    
    <div class="card iris-help-card">
        <h3>Comment exporter un preset depuis Lightroom ?</h3>
        <ol>
            <li>Dans Lightroom, sélectionnez une image avec les réglages souhaités</li>
            <li>Allez dans <strong>Développer > Nouveau paramètre prédéfini...</strong></li>
            <li>Donnez un nom à votre preset et cliquez sur <strong>Créer</strong></li>
            <li>Clic droit sur votre preset > <strong>Exporter...</strong></li>
            <li>Sauvegardez le fichier .xmp et uploadez-le ici</li>
        </ol>
        
        <h3>Format JSON personnalisé</h3>
        <p>Vous pouvez aussi créer des presets
            <details>
           <summary>Voir exemple de preset JSON</summary>
           <pre><code>{
 "name": "Mon Preset Personnalisé",
 "description": "Preset optimisé pour les photos d'iris",
 "version": "1.0",
 "camera_models": ["Canon EOS R", "Canon EOS R5"],
 "raw_params": {
   "white_balance": "custom",
   "temperature": 4726,
   "tint": -2,
   "output_color": "adobe",
   "output_bps": 16
 },
 "tone_adjustments": {
   "exposure": 0.10,
   "contrast": 0.13,
   "highlights": -0.93,
   "shadows": 1.0,
   "texture": 1.0,
   "clarity": 0.15
 },
 "color_adjustments": {
   "saturation_adjustments": {
     "blue": 26,
     "aqua": 8
   }
 }
}</code></pre>
       </details>
   </div>
</div>