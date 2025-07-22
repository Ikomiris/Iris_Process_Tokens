# 🌍 Guide d'internationalisation - Plugin Iris Process

Ce guide vous explique comment utiliser les fonctionnalités de traduction du plugin Iris Process pour créer un site bilingue français/anglais.

## 📁 Structure des fichiers de traduction

```
plugins/image-processor-tokens/
├── languages/
│   ├── iris-process-tokens.pot      # Template de traduction
│   ├── iris-process-tokens-en_US.po # Traductions anglaises
│   └── iris-process-tokens-en_US.mo # Fichier binaire anglais
├── includes/
│   ├── class-language-manager.php   # Gestionnaire de langues
│   └── functions-i18n.php          # Fonctions d'internationalisation
```

## 🎯 Méthodes de détection de langue

Le plugin détecte automatiquement la langue selon plusieurs méthodes :

### 1. Détection par préfixe d'URL (Automatique - Recommandé)

Le plugin détecte automatiquement la langue selon le préfixe dans l'URL :

**Pages anglaises :** Toute URL contenant `/en/`
- `https://votresite.com/en/iris-processor/`
- `https://votresite.com/en/process-images/`  
- `https://votresite.com/en/dashboard/`

**Pages françaises :** Toute URL contenant `/fr/` ou sans préfixe
- `https://votresite.com/fr/traitement-images/`
- `https://votresite.com/traitement-images/` (français par défaut)

### 2. Détection par slug de page (Fallback)

Pour des cas spécifiques sans préfixe d'URL :

**Pages françaises :**
- `traitement-images`
- `traitement-iris` 
- `dashboard-jetons`

**Pages anglaises :**
- `process-images`
- `image-processing`
- `tokens-dashboard-en`

### 2. Paramètre URL (Pour tests)

Ajoutez `?iris_lang=en_US` ou `?iris_lang=fr_FR` à n'importe quelle URL.

### 3. Session utilisateur

Une fois détectée, la langue est sauvegardée en session.

## 🔧 Configuration des pages

### Étape 1 : Créer les pages distinctes

Dans WordPress Admin, créez deux pages :

**Page française :**
- Titre : "Traitement d'images"
- Slug : `traitement-images`
- Contenu : `[iris_upload_zone]` ou `[iris_process_page]`

**Page anglaise :**
- Titre : "Image Processing"
- Slug : `process-images`
- Contenu : `[iris_upload_zone]` ou `[iris_process_page]`

### Étape 2 : Personnaliser la détection (optionnel)

Dans `includes/class-language-manager.php`, modifiez le tableau `$language_pages` :

```php
$language_pages = array(
    'en_US' => array(
        'process-images',      // Vos slugs anglais
        'image-processing',    
        'tokens-dashboard-en',
        'your-custom-english-slug'
    ),
    'fr_FR' => array(
        'traitement-images',   // Vos slugs français
        'traitement-iris',
        'dashboard-jetons',
        'votre-slug-francais-personnalise'
    )
);
```

## 🎨 Utilisation dans les shortcodes

### Méthode simple avec les fonctions utilitaires

```php
// Au lieu de :
echo 'Vos jetons disponibles : ' . $balance;

// Utilisez :
iris_e('Vos jetons disponibles :');
echo ' ' . $balance;

// Ou :
echo iris__('Vos jetons disponibles :') . ' ' . $balance;
```

### Méthode WordPress standard

```php
// Au lieu de :
echo 'Traitement d\'image Iris Process';

// Utilisez :
_e('Traitement d\'image Iris Process', 'iris-process-tokens');

// Ou :
echo __('Traitement d\'image Iris Process', 'iris-process-tokens');
```

## 🔄 Workflow de développement

### 1. Développement

Utilisez les fonctions `iris_e()` et `iris__()` dans votre code PHP :

```php
<h3><?php iris_e('Vos jetons disponibles :'); ?> <span><?php echo $balance; ?></span></h3>
```

### 2. Test de langue

Visitez vos pages avec `?iris_lang=en_US` pour tester la traduction anglaise.

### 3. Mise à jour des traductions

Si vous ajoutez de nouveaux textes :

1. Ajoutez-les dans `includes/functions-i18n.php` dans `iris_get_common_translations()`
2. Ou utilisez le système WordPress standard et mettez à jour les fichiers .po/.mo

## 🎛️ Shortcodes disponibles

Tous les shortcodes du plugin sont maintenant traduits :

- `[iris_upload_zone]` - Zone d'upload avec historique
- `[iris_process_page]` - Page de traitement complète  
- `[user_token_balance]` - Solde de jetons
- `[token_history]` - Historique des jetons
- `[iris_user_dashboard]` - Dashboard utilisateur

## 🔧 Outils de debug

### Sélecteur de langue

Ajoutez temporairement ceci dans vos templates :

```php
<?php
if (function_exists('iris_get_language_manager')) {
    echo iris_get_language_manager()->get_language_selector();
}
?>
```

### Vérification de langue

```php
<?php
if (iris_is_english()) {
    echo "English version";
} else {
    echo "Version française";
}
?>
```

## 🌐 Exemple d'implémentation complète

### Template de page française (traitement-images.php)

```php
<?php
/*
Template Name: Traitement Images FR
*/

get_header(); ?>

<div class="container">
    <h1><?php iris_e('Traitement d\'image Iris Process'); ?></h1>
    
    <?php if (!is_user_logged_in()): ?>
        <div class="login-notice">
            <p><?php iris_e('Vous devez être connecté pour utiliser cette fonctionnalité.'); ?></p>
            <a href="<?php echo wp_login_url(); ?>" class="btn">
                <?php iris_e('Se connecter'); ?>
            </a>
        </div>
    <?php else: ?>
        <?php echo do_shortcode('[iris_process_page]'); ?>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
```

### Template de page anglaise (process-images.php)

```php
<?php
/*
Template Name: Image Processing EN
*/

get_header(); ?>

<div class="container">
    <h1><?php iris_e('Traitement d\'image Iris Process'); ?></h1>
    
    <?php if (!is_user_logged_in()): ?>
        <div class="login-notice">
            <p><?php iris_e('Vous devez être connecté pour utiliser cette fonctionnalité.'); ?></p>
            <a href="<?php echo wp_login_url(); ?>" class="btn">
                <?php iris_e('Se connecter'); ?>
            </a>
        </div>
    <?php else: ?>
        <?php echo do_shortcode('[iris_process_page]'); ?>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
```

## 🚀 Navigation bilingue

Ajoutez des liens de navigation dans votre thème :

```php
<nav class="language-nav">
    <a href="/traitement-images/" <?php echo iris_is_french() ? 'class="active"' : ''; ?>>
        🇫🇷 Français
    </a>
    <a href="/process-images/" <?php echo iris_is_english() ? 'class="active"' : ''; ?>>
        🇺🇸 English
    </a>
</nav>
```

## 📝 Notes importantes

1. **Performance** : La détection de langue se fait une seule fois par session
2. **Cache** : Videz le cache après modification des traductions
3. **Compatibilité** : Fonctionne avec tous les plugins de cache WordPress
4. **SEO** : Créez des URLs distinctes pour un meilleur référencement

## 🔍 Dépannage

### La traduction ne s'affiche pas

1. Vérifiez que le slug de page est correct
2. Testez avec `?iris_lang=en_US`
3. Vérifiez les logs WordPress pour les messages `IRIS LANG:`

### Textes partiellement traduits

1. Vérifiez que tous les textes utilisent `iris_e()` ou `_e()`
2. Mettez à jour `functions-i18n.php` si nécessaire

### Cache de traductions

Videz le cache et rechargez les pages si les traductions ne se mettent pas à jour.

---

*Dernière mise à jour : 17 janvier 2025* 