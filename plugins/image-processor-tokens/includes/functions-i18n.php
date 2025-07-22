<?php
/**
 * Fonctions d'internationalisation pour Iris Process
 * 
 * @package IrisProcessTokens
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fonction utilitaire pour les traductions avec détection de langue dynamique
 * 
 * @since 1.2.0
 * @param string $text Texte français par défaut
 * @param array $translations Traductions dans d'autres langues
 * @return string Texte traduit
 */
function iris_translate($text, $translations = array()) {
    // Utiliser le système WordPress par défaut si disponible
    $translated = __($text, 'iris-process-tokens');
    
    // Si la traduction n'a pas changé et qu'on a des traductions custom
    if ($translated === $text && !empty($translations)) {
        $lang_manager = iris_get_language_manager();
        $current_lang = $lang_manager->get_current_language();
        
        if (isset($translations[$current_lang])) {
            return $translations[$current_lang];
        }
    }
    
    return $translated;
}

/**
 * Fonction utilitaire pour les traductions avec écho
 * 
 * @since 1.2.0
 * @param string $text Texte français par défaut
 * @param array $translations Traductions dans d'autres langues
 * @return void
 */
function iris_translate_e($text, $translations = array()) {
    echo iris_translate($text, $translations);
}

/**
 * Traductions communes du plugin
 * 
 * @since 1.2.0
 * @return array
 */
function iris_get_common_translations() {
    return array(
        // Messages de base
        'Vos jetons disponibles :' => array(
            'en_US' => 'Available tokens:'
        ),
        'Vous n\'avez pas assez de jetons.' => array(
            'en_US' => 'You don\'t have enough tokens.'
        ),
        'Achetez des jetons' => array(
            'en_US' => 'Buy tokens'
        ),
        'Glissez votre image ici ou cliquez pour sélectionner' => array(
            'en_US' => 'Drag your image here or click to select'
        ),
        'Formats supportés : CR3, CR2, NEF, ARW, RAW, DNG, ORF, RAF, RW2, JPG, TIF, PNG' => array(
            'en_US' => 'Supported formats: CR3, CR2, NEF, ARW, RAW, DNG, ORF, RAF, RW2, JPG, TIF, PNG'
        ),
        'Taille maximum :' => array(
            'en_US' => 'Maximum size:'
        ),
        'Traiter l\'image (1 jeton)' => array(
            'en_US' => 'Process image (1 token)'
        ),
        '⏳ Traitement en cours...' => array(
            'en_US' => '⏳ Processing...'
        ),
        'Historique des traitements' => array(
            'en_US' => 'Processing history'
        ),
        'Aucun traitement effectué pour le moment.' => array(
            'en_US' => 'No processing performed yet.'
        ),
        'Télécharger' => array(
            'en_US' => 'Download'
        ),
        'Préc.' => array(
            'en_US' => 'Prev'
        ),
        'Suiv.' => array(
            'en_US' => 'Next'
        ),
        'Connexion requise' => array(
            'en_US' => 'Login required'
        ),
        'Vous devez être connecté pour utiliser cette fonctionnalité.' => array(
            'en_US' => 'You must be logged in to use this feature.'
        ),
        'Se connecter' => array(
            'en_US' => 'Log in'
        ),
        
        // Messages d'erreur
        'Erreur de sécurité - Nonce invalide' => array(
            'en_US' => 'Security error - Invalid nonce'
        ),
        'Utilisateur non connecté' => array(
            'en_US' => 'User not logged in'
        ),
        'Système de jetons non disponible' => array(
            'en_US' => 'Token system unavailable'
        ),
        'Solde de jetons insuffisant (%d disponible)' => array(
            'en_US' => 'Insufficient token balance (%d available)'
        ),
        'Fichier trop volumineux (limite serveur)' => array(
            'en_US' => 'File too large (server limit)'
        ),
        'Fichier trop volumineux (limite formulaire)' => array(
            'en_US' => 'File too large (form limit)'
        ),
        'Upload partiel' => array(
            'en_US' => 'Partial upload'
        ),
        'Aucun fichier sélectionné' => array(
            'en_US' => 'No file selected'
        ),
        'Dossier temporaire manquant' => array(
            'en_US' => 'Temporary folder missing'
        ),
        'Erreur d\'écriture disque' => array(
            'en_US' => 'Disk write error'
        ),
        'Extension PHP bloquante' => array(
            'en_US' => 'Blocking PHP extension'
        ),
        'Erreur upload inconnue' => array(
            'en_US' => 'Unknown upload error'
        ),
        'Erreur lors de l\'upload:' => array(
            'en_US' => 'Upload error:'
        ),
        'Votre fichier a été envoyé avec succès, il est en cours de traitement...' => array(
            'en_US' => 'Your file has been uploaded successfully, it is being processed...'
        ),
        'Format de fichier non supporté:' => array(
            'en_US' => 'Unsupported file format:'
        ),
        'Formats acceptés:' => array(
            'en_US' => 'Accepted formats:'
        ),
        'Fichier trop volumineux. Taille maximum:' => array(
            'en_US' => 'File too large. Maximum size:'
        ),
        'En attente' => array(
            'en_US' => 'Pending'
        ),
        'En cours de traitement' => array(
            'en_US' => 'Processing'
        ),
        'Terminé' => array(
            'en_US' => 'Completed'
        ),
        'Erreur' => array(
            'en_US' => 'Error'
        ),
        'Uploadé' => array(
            'en_US' => 'Uploaded'
        )
    );
}

/**
 * Fonction raccourcie pour les traductions communes
 * 
 * @since 1.2.0
 * @param string $text Texte français
 * @return string Texte traduit
 */
function iris__($text) {
    $translations = iris_get_common_translations();
    
    if (isset($translations[$text])) {
        return iris_translate($text, $translations[$text]);
    }
    
    // Fallback vers le système WordPress standard
    return __($text, 'iris-process-tokens');
}

/**
 * Fonction raccourcie pour les traductions communes avec écho
 * 
 * @since 1.2.0
 * @param string $text Texte français
 * @return void
 */
function iris_e($text) {
    echo iris__($text);
}

/**
 * Fonction pour localiser les scripts avec les bonnes traductions
 * 
 * @since 1.2.0
 * @param string $handle Handle du script
 * @return void
 */
function iris_localize_script($handle) {
    $translations = array(
        'loading' => iris__('Chargement...'),
        'error' => iris__('Erreur lors du chargement'),
        'refresh' => iris__('Actualiser'),
        'no_jobs' => iris__('Aucun traitement en cours'),
        'download' => iris__('Télécharger'),
        'prev' => iris__('Préc.'),
        'next' => iris__('Suiv.'),
        'no_processing' => iris__('Aucun traitement effectué pour le moment.'),
        'processing' => iris__('⏳ Traitement en cours...'),
        'process_image' => iris__('Traiter l\'image (1 jeton)'),
        'upload_success' => iris__('Votre fichier a été envoyé avec succès, il est en cours de traitement...'),
        'error_connection' => iris__('❌ Erreur de connexion'),
        'error_generic' => iris__('❌ Erreur'),
        'unknown_error' => iris__('Erreur inconnue')
    );
    
    wp_localize_script($handle, 'iris_translations', $translations);
} 