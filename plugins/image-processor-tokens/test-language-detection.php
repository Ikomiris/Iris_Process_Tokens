<?php
/**
 * Script de test pour la détection de langue
 * 
 * Ajoutez ce code temporairement dans votre template pour tester
 * 
 * @package IrisProcessTokens
 * @since 1.2.0
 */

// À ajouter temporairement dans votre template WordPress pour tester

if (function_exists('iris_get_language_manager')) {
    $lang_manager = iris_get_language_manager();
    $current_url = $_SERVER['REQUEST_URI'];
    
    echo '<div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 5px; font-family: monospace;">';
    echo '<h4>🔧 Test de détection de langue Iris Process</h4>';
    echo '<strong>URL actuelle :</strong> ' . esc_html($current_url) . '<br>';
    echo '<strong>Langue détectée :</strong> <span style="color: green;">' . $lang_manager->get_current_language() . '</span><br>';
    echo '<strong>Est en anglais :</strong> ' . (iris_is_english() ? '✅ OUI' : '❌ NON') . '<br>';
    echo '<strong>Est en français :</strong> ' . (iris_is_french() ? '✅ OUI' : '❌ NON') . '<br>';
    echo '<br>';
    
    // Test des traductions
    echo '<strong>Test de traduction :</strong><br>';
    echo '- "Vos jetons disponibles :" → "' . iris__('Vos jetons disponibles :') . '"<br>';
    echo '- "Traiter l\'image (1 jeton)" → "' . iris__('Traiter l\'image (1 jeton)') . '"<br>';
    echo '- "Se connecter" → "' . iris__('Se connecter') . '"<br>';
    
    echo '<br>' . $lang_manager->get_language_selector();
    
    echo '<br><small style="color: #666;">Supprimez ce code une fois le test terminé.</small>';
    echo '</div>';
}
?> 