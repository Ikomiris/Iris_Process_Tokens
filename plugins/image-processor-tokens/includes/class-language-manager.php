<?php
/**
 * Gestionnaire de langue pour Iris Process
 * 
 * @package IrisProcessTokens
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe pour gérer les langues du plugin
 * 
 * @since 1.2.0
 */
class Iris_Language_Manager {
    
    /**
     * Instance unique de la classe (Singleton)
     * 
     * @since 1.2.0
     * @var Iris_Language_Manager|null
     */
    private static $instance = null;
    
    /**
     * Langue actuelle détectée
     * 
     * @since 1.2.0
     * @var string
     */
    private $current_language = 'fr_FR';
    
    /**
     * Langues supportées par le plugin
     * 
     * @since 1.2.0
     * @var array
     */
    private $supported_languages = array(
        'fr_FR' => 'Français',
        'en_US' => 'English'
    );
    
    /**
     * Constructeur
     * 
     * @since 1.2.0
     */
    private function __construct() {
        add_action('init', array($this, 'init_language_detection'), 5);
        add_action('wp_loaded', array($this, 'setup_language_overrides'), 5);
    }
    
    /**
     * Récupère l'instance unique de la classe
     * 
     * @since 1.2.0
     * @return Iris_Language_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialise la détection de langue
     * 
     * @since 1.2.0
     * @return void
     */
    public function init_language_detection() {
        // Méthode 1: Détection par URL/slug de page
        $detected_lang = $this->detect_language_by_page();
        
        // Méthode 2: Paramètre GET de force (pour les tests)
        if (isset($_GET['iris_lang']) && in_array($_GET['iris_lang'], array_keys($this->supported_languages))) {
            $detected_lang = sanitize_text_field($_GET['iris_lang']);
        }
        
        // Méthode 3: Session utilisateur (persistance)
        if (!$detected_lang && isset($_SESSION['iris_lang'])) {
            $detected_lang = $_SESSION['iris_lang'];
        }
        
        // Méthode 4: Langue WordPress par défaut
        if (!$detected_lang) {
            $wp_locale = get_locale();
            if (array_key_exists($wp_locale, $this->supported_languages)) {
                $detected_lang = $wp_locale;
            }
        }
        
        // Fallback vers français
        if (!$detected_lang) {
            $detected_lang = 'fr_FR';
        }
        
        $this->current_language = $detected_lang;
        
        // Sauvegarder en session
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['iris_lang'] = $this->current_language;
        
        iris_log_error('IRIS LANG: Langue détectée - ' . $this->current_language);
    }
    
    /**
     * Détecte la langue selon la page actuelle
     * 
     * @since 1.2.0
     * @return string|null Code de langue ou null si non détecté
     */
    private function detect_language_by_page() {
        global $post;
        
        // Obtenir l'URL actuelle
        $current_path = $_SERVER['REQUEST_URI'];
        
        // Méthode 1 : Détection par préfixe d'URL (priorité)
        if (strpos($current_path, '/en/') !== false) {
            return 'en_US';
        }
        
        if (strpos($current_path, '/fr/') !== false) {
            return 'fr_FR';
        }
        
        // Méthode 2 : Détection par slug de page (fallback)
        if (!$post) {
            return null;
        }
        
        // Configuration des pages par langue (pour les cas spécifiques)
        $language_pages = array(
            'en_US' => array(
                'process-images',      // slug anglais
                'image-processing',    
                'tokens-dashboard-en',
                'iris-processor',      // pages spécifiques anglaises
                'processing-english'
            ),
            'fr_FR' => array(
                'traitement-images',   // slug français
                'traitement-iris',
                'dashboard-jetons',
                'iris-processor',      // si pas de préfixe, considérer comme français par défaut
                'traitement-francais'
            )
        );
        
        // Obtenir le slug de la page actuelle
        $current_slug = $post->post_name;
        
        // Vérifier les correspondances par slug (seulement si pas de préfixe détecté)
        foreach ($language_pages as $lang => $pages) {
            foreach ($pages as $page_slug) {
                if ($current_slug === $page_slug) {
                    return $lang;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Configure les surcharges de langue pour forcer une locale
     * 
     * @since 1.2.0
     * @return void
     */
    public function setup_language_overrides() {
        if ($this->current_language === 'en_US' && get_locale() !== 'en_US') {
            // Forcer la langue anglaise pour ce plugin uniquement
            add_filter('plugin_locale', array($this, 'override_plugin_locale'), 10, 2);
        }
    }
    
    /**
     * Surcharge la locale pour ce plugin spécifiquement
     * 
     * @since 1.2.0
     * @param string $locale Locale actuelle
     * @param string $domain Domaine de texte
     * @return string Locale modifiée
     */
    public function override_plugin_locale($locale, $domain) {
        if ($domain === 'iris-process-tokens') {
            return $this->current_language;
        }
        return $locale;
    }
    
    /**
     * Récupère la langue actuelle
     * 
     * @since 1.2.0
     * @return string Code de langue
     */
    public function get_current_language() {
        return $this->current_language;
    }
    
    /**
     * Vérifie si on est en anglais
     * 
     * @since 1.2.0
     * @return bool
     */
    public function is_english() {
        return $this->current_language === 'en_US';
    }
    
    /**
     * Vérifie si on est en français
     * 
     * @since 1.2.0
     * @return bool
     */
    public function is_french() {
        return $this->current_language === 'fr_FR';
    }
    
    /**
     * Récupère les langues supportées
     * 
     * @since 1.2.0
     * @return array
     */
    public function get_supported_languages() {
        return $this->supported_languages;
    }
    
    /**
     * Génère un sélecteur de langue (pour debug/admin)
     * 
     * @since 1.2.0
     * @return string HTML du sélecteur
     */
    public function get_language_selector() {
        $current_url = $_SERVER['REQUEST_URI'];
        $html = '<div class="iris-language-selector">';
        $html .= '<label>Langue / Language:</label>';
        
        foreach ($this->supported_languages as $code => $name) {
            $url = add_query_arg('iris_lang', $code, $current_url);
            $selected = ($code === $this->current_language) ? 'selected' : '';
            
            $html .= '<a href="' . esc_url($url) . '" class="iris-lang-link ' . $selected . '">';
            $html .= esc_html($name) . '</a> ';
        }
        
        $html .= '</div>';
        return $html;
    }
}

/**
 * Fonction utilitaire globale pour récupérer l'instance
 * 
 * @since 1.2.0
 * @return Iris_Language_Manager
 */
function iris_get_language_manager() {
    return Iris_Language_Manager::get_instance();
}

/**
 * Fonction utilitaire pour vérifier la langue
 * 
 * @since 1.2.0
 * @return bool
 */
function iris_is_english() {
    return iris_get_language_manager()->is_english();
}

/**
 * Fonction utilitaire pour vérifier la langue
 * 
 * @since 1.2.0
 * @return bool
 */
function iris_is_french() {
    return iris_get_language_manager()->is_french();
} 