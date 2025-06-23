<div class="iris-preset-settings">
    <form method="post" action="options.php">
        <?php settings_fields('iris_preset_settings'); ?>
        
        <div class="card">
            <h2>Paramètres des presets</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Preset par défaut</th>
                    <td>
                        <select name="iris_default_preset" id="iris_default_preset">
                            <option value="">Auto-détection</option>
                            <?php foreach ($presets as $preset): ?>
                                <option value="<?php echo esc_attr($preset['id']); ?>" 
                                        <?php selected(get_option('iris_default_preset'), $preset['id']); ?>>
                                    <?php echo esc_html($preset['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            Preset utilisé par défaut si aucun modèle de caméra spécifique n'est trouvé.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Prétraitement automatique</th>
                    <td>
                        <label>
                            <input type="checkbox" name="iris_auto_preprocessing" value="1" 
                                   <?php checked(get_option('iris_auto_preprocessing', 1)); ?> />
                            Appliquer automatiquement le prétraitement Lightroom sur les fichiers RAW
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Sauvegarde des images intermédiaires</th>
                    <td>
                        <label>
                            <input type="checkbox" name="iris_save_intermediate" value="1" 
                                   <?php checked(get_option('iris_save_intermediate', 0)); ?> />
                            Sauvegarder les images après prétraitement (pour debug)
                        </label>
                        <p class="description">
                            ⚠️ Utilise plus d'espace disque mais utile pour le dépannage.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Nettoyage automatique</th>
                    <td>
                        <select name="iris_cleanup_interval">
                            <option value="never" <?php selected(get_option('iris_cleanup_interval', 'weekly'), 'never'); ?>>
                                Jamais
                            </option>
                            <option value="daily" <?php selected(get_option('iris_cleanup_interval'), 'daily'); ?>>
                                Quotidien
                            </option>
                            <option value="weekly" <?php selected(get_option('iris_cleanup_interval'), 'weekly'); ?>>
                                Hebdomadaire
                            </option>
                            <option value="monthly" <?php selected(get_option('iris_cleanup_interval'), 'monthly'); ?>>
                                Mensuel
                            </option>
                        </select>
                        <p class="description">
                            Fréquence de suppression des fichiers temporaires et images intermédiaires.
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="card">
            <h2>Performances</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Cache des presets</th>
                    <td>
                        <button type="button" id="iris-clear-preset-cache" class="button">
                            Vider le cache
                        </button>
                        <p class="description">
                            Vide le cache des presets compilés pour forcer leur rechargement.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Logs de debug</th>
                    <td>
                        <label>
                            <input type="checkbox" name="iris_debug_preprocessing" value="1" 
                                   <?php checked(get_option('iris_debug_preprocessing', 0)); ?> />
                            Activer les logs détaillés du prétraitement
                        </label>
                        
                        <br><br>
                        
                        <button type="button" id="iris-view-logs" class="button">
                            Voir les logs
                        </button>
                        <button type="button" id="iris-clear-logs" class="button">
                            Vider les logs
                        </button>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="card">
            <h2>Statistiques</h2>
            
            <?php
            // Calcul des statistiques
            $stats = array(
                'total_presets' => count($presets),
                'default_presets' => count(array_filter($presets, function($p) { return $p['type'] === 'default'; })),
                'uploaded_presets' => count(array_filter($presets, function($p) { return $p['type'] === 'uploaded'; })),
                'disk_usage' => $this->calculate_presets_disk_usage()
            );
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Presets installés</th>
                    <td>
                        <strong><?php echo $stats['total_presets']; ?></strong> presets
                        (<?php echo $stats['default_presets']; ?> par défaut, 
                         <?php echo $stats['uploaded_presets']; ?> uploadés)
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Espace disque utilisé</th>
                    <td>
                        <strong><?php echo size_format($stats['disk_usage']); ?></strong>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button('Sauvegarder les paramètres'); ?>
    </form>
</div>