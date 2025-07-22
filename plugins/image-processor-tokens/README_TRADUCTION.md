# ✅ Système de traduction ajouté au plugin Iris Process

## 🎯 Ce qui a été fait

J'ai créé un système complet d'internationalisation pour votre plugin WordPress, permettant d'avoir des pages distinctes en français et en anglais.

### 📁 Fichiers créés/modifiés

1. **Fichiers de traduction** :
   - `languages/iris-process-tokens.pot` - Template de traduction
   - `languages/iris-process-tokens-en_US.po` - Traductions anglaises
   - `languages/iris-process-tokens-en_US.mo` - Fichier binaire anglais

2. **Classes de gestion** :
   - `includes/class-language-manager.php` - Détection automatique de langue
   - `includes/functions-i18n.php` - Fonctions utilitaires de traduction

3. **Guides et exemples** :
   - `GUIDE_TRADUCTION.md` - Guide complet d'utilisation
   - `shortcodes/class-shortcodes-i18n-example.php` - Exemple de shortcode traduit

## 🚀 Comment utiliser

### 1. Créez vos pages distinctes

Le plugin détecte automatiquement la langue par l'URL :

**Page française :**
- URL : `https://votresite.com/traitement-images/` (ou `/fr/traitement-images/`)
- Contenu : `[iris_upload_zone]`

**Page anglaise :**
- URL : `https://votresite.com/en/iris-processor/` (toute URL avec `/en/`)
- Contenu : `[iris_upload_zone]`

### 2. Test immédiat

Visitez vos pages avec ces paramètres :
- `?iris_lang=fr_FR` pour forcer le français
- `?iris_lang=en_US` pour forcer l'anglais

### 3. Personnalisation des slugs

Dans `includes/class-language-manager.php`, modifiez le tableau `$language_pages` avec vos propres slugs.

## 🔧 Fonctions disponibles

### Dans vos templates PHP :

```php
// Afficher du texte traduit
<?php iris_e('Vos jetons disponibles :'); ?>

// Récupérer du texte traduit
$text = iris__('Traiter l\'image (1 jeton)');

// Vérifier la langue
if (iris_is_english()) {
    echo "English version";
} else {
    echo "Version française";
}
```

### Liens de navigation :

```php
<nav class="language-nav">
    <a href="/traitement-images/">🇫🇷 Français</a>
    <a href="/process-images/">🇺🇸 English</a>
</nav>
```

## 🎛️ Shortcodes traduits

Tous vos shortcodes existants fonctionnent automatiquement :

- `[iris_upload_zone]` - Zone d'upload avec historique
- `[iris_process_page]` - Page de traitement complète  
- `[user_token_balance]` - Solde de jetons
- `[token_history]` - Historique des jetons
- `[iris_user_dashboard]` - Dashboard utilisateur

## 🔄 Workflow recommandé

1. **Créez vos 2 pages** avec les slugs appropriés
2. **Testez avec les paramètres URL** (`?iris_lang=en_US`)
3. **Personnalisez les slugs** si nécessaire
4. **Ajoutez la navigation bilingue** dans votre thème

## 🌐 Fonctionnalités

### ✅ Détection automatique
- Par slug de page
- Par paramètre URL
- Sauvegarde en session

### ✅ Traductions complètes
- Interface utilisateur
- Messages d'erreur  
- Textes JavaScript
- Messages de validation

### ✅ Performance optimisée
- Une seule détection par session
- Compatible avec le cache
- Pas d'impact sur les performances

## 🔧 Debug et maintenance

### Activer le mode debug
Ajoutez `?iris_debug=1` à vos URLs pour voir :
- Le sélecteur de langue
- La langue détectée
- Les informations de debug

### Logs
Consultez les logs WordPress pour les messages `IRIS LANG:` pour le debugging.

### Ajouter de nouveaux textes
1. Modifiez `includes/functions-i18n.php`
2. Ajoutez vos traductions dans `iris_get_common_translations()`
3. Utilisez `iris_e()` ou `iris__()` dans votre code

## 📊 Exemple concret

**URL française :** `https://votresite.com/traitement-images/`
**URL anglaise :** `https://votresite.com/process-images/`

Le même shortcode `[iris_upload_zone]` s'affichera automatiquement dans la bonne langue selon la page visitée.

---

**🎉 Votre plugin est maintenant bilingue !** 

Consultez `GUIDE_TRADUCTION.md` pour la documentation complète.

*Créé le 17 janvier 2025* 