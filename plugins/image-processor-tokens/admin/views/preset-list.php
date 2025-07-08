<div class="iris-preset-list">
    <div class="tablenav top">
        <div class="alignleft actions">
            <button id="iris-test-all-presets" class="button">Tester tous les presets</button>
        </div>
        <div class="alignright">
            <span class="displaying-num"><?php echo count($presets); ?> preset(s)</span>
        </div>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="column-name">Nom</th>
                <th scope="col" class="column-photo-type">Type de photo</th>
                <th scope="col" class="column-default">Par défaut</th>
                <th scope="col" class="column-type">Type</th>
                <th scope="col" class="column-camera">Modèles compatibles</th>
                <th scope="col" class="column-date">Date</th>
                <th scope="col" class="column-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($presets)): ?>
                <tr>
                    <td colspan="5" class="no-presets">
                        <p>Aucun preset trouvé. <a href="?page=iris-presets&tab=upload">Uploader votre premier preset</a></p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($presets as $preset): ?>
                <tr data-preset-id="<?php echo esc_attr($preset['id']); ?>">
                    <td class="column-name">
                        <strong><?php echo esc_html($preset['name']); ?></strong>
                        <?php if (!empty($preset['description'])): ?>
                            <br><span class="description"><?php echo esc_html($preset['description']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="column-photo-type">
                        <?php echo isset($preset['photo_type']) ? esc_html($preset['photo_type']) : '<em>Non défini</em>'; ?>
                    </td>
                    <td class="column-default">
                        <?php echo !empty($preset['is_default']) ? '<span style="color:green;font-weight:bold;">Oui</span>' : 'Non'; ?>
                    </td>
                    <td class="column-type">
                        <span class="preset-type preset-type-<?php echo $preset['type']; ?>">
                            <?php echo $preset['type'] === 'default' ? 'Par défaut' : 'Uploadé'; ?>
                        </span>
                    </td>
                    <td class="column-camera">
                        <?php if (!empty($preset['camera_models'])): ?>
                            <?php echo esc_html(implode(', ', $preset['camera_models'])); ?>
                        <?php else: ?>
                            <em>Tous les modèles</em>
                        <?php endif; ?>
                    </td>
                    <td class="column-date">
                        <?php echo esc_html(date('d/m/Y H:i', strtotime($preset['created_date']))); ?>
                    </td>
                    <td class="column-actions">
                        <div class="row-actions">
                            <span class="test">
                                <a href="#" class="iris-test-preset" data-preset-id="<?php echo esc_attr($preset['id']); ?>">
                                    Tester
                                </a>
                            </span>
                            
                            <?php if ($preset['type'] === 'uploaded'): ?>
                                | <span class="edit">
                                    <a href="?page=iris-presets&tab=edit&preset=<?php echo esc_attr($preset['id']); ?>">
                                        Éditer
                                    </a>
                                </span>
                                | <span class="delete">
                                    <a href="#" class="iris-delete-preset" data-preset-id="<?php echo esc_attr($preset['id']); ?>">
                                        Supprimer
                                    </a>
                                </span>
                            <?php endif; ?>
                            
                            | <span class="download">
                                <a href="<?php echo wp_upload_dir()['baseurl'] . '/iris-presets/' . ($preset['type'] === 'uploaded' ? 'uploads/' : '') . $preset['id'] . '.json'; ?>" 
                                   download="<?php echo esc_attr($preset['id']); ?>.json">
                                    Télécharger JSON
                                </a>
                            </span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div id="iris-test-results" class="iris-test-results" style="display: none;">
        <h3>Résultats des tests</h3>
        <div class="iris-test-content"></div>
    </div>
</div>