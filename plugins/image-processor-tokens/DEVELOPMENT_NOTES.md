# Notes de développement - Image Processor Tokens

## 🔧 Configuration technique actuelle

### Environnement de développement
- **OS :** Windows
- **IDE Principal :** Visual Studio Code
- **IDE Secondaire :** Cursor (assistance IA)
- **Serveur local :** XAMPP (recommandé pour tests locaux)

### Synchronisation SFTP
- **Extension :** SFTP (Natizyskunk) pour VS Code
- **Serveur :** Hostinger (178.16.128.218)
- **Compte FTP :** u319690172.eleconte
- **Chemin distant :** `/wp-content/plugins/image-processor-tokens/`
- **Synchronisation :** Automatique à la sauvegarde

### Repository Git
- **Platform :** GitHub
- **Repository :** Iris_Process_Tokens
- **Branche principale :** main
- **Workflow :** Local → SFTP → GitHub

## 📁 Structure de fichiers détaillée

```
Iris_Process_Tokens/
├── .vscode/
│   └── sftp.json                    # Config SFTP (privé, exclu de Git)
├── plugins/
│   └── image-processor-tokens/
│       ├── image-processor-tokens.php  # Fichier principal (structure Singleton)
│       ├── includes/                # Classes et fonctions
│       │   ├── class-image-processor.php
│       │   ├── class-token-manager.php
│       │   └── class-api-handler.php
│       ├── admin/                   # Interface d'administration
│       │   ├── class-admin.php
│       │   └── views/
│       ├── assets/                  # Ressources statiques
│       │   ├── css/
│       │   ├── js/
│       │   └── images/
│       ├── languages/               # Fichiers de traduction
│       └── readme.txt              # Documentation WordPress
├── .gitignore                      # Fichiers exclus de Git
├── README.md                       # Documentation principale
└── DEVELOPMENT_NOTES.md           # Ce fichier
```

## 🔐 Sécurité et bonnes pratiques

### Fichiers sensibles exclus de Git
- `.vscode/sftp.json` (contient les identifiants FTP)
- Fichiers de logs (`*.log`)
- Fichiers temporaires et cache
- Configuration spécifique à l'environnement

### Standards WordPress respectés
- Prefix unique pour toutes les fonctions : `image_processor_tokens_`
- Échappement des sorties : `esc_html()`, `esc_attr()`, etc.
- Validation des entrées : `sanitize_text_field()`, etc.
- Nonces pour les formulaires
- Permissions utilisateur vérifiées

## 🚀 Workflow de développement

### 1. Développement local
```bash
# Démarrer XAMPP si tests locaux nécessaires
# Modification des fichiers dans VS Code
# Sauvegarde automatique → Upload SFTP vers Hostinger
```

### 2. Test en production
- Test direct sur iris4pro.com/wp-admin
- Vérification des logs d'erreur WordPress
- Test des fonctionnalités en temps réel

### 3. Versioning
```bash
git add .
git commit -m "Description des modifications"
git push origin main
```

## 🐛 Debug et logs

### Activation du debug WordPress
```php
// Dans wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Logs du plugin
- Logs automatiques lors de l'activation/désactivation
- Logs de debug conditionnels (seulement si WP_DEBUG actif)
- Fichier de log : `/wp-content/debug.log`

### Monitoring des erreurs
- Console SFTP de VS Code pour erreurs de sync
- Logs d'erreur Hostinger via hPanel
- Console développeur du navigateur pour erreurs JS

## 📊 Performance et optimisation

### Bonnes pratiques implémentées
- Chargement conditionnel des scripts (seulement pages admin du plugin)
- Singleton pattern pour la classe principale
- Hooks WordPress optimisés
- Nettoyage lors de la désactivation

### À surveiller
- Taille des images traitées
- Temps de traitement des tokens
- Usage mémoire PHP
- Requêtes de base de données

## 🔄 Intégration avec Claude.ai

### Utilisation recommandée
- Génération de code PHP WordPress conforme aux standards
- Résolution de problèmes techniques spécifiques
- Optimisation des performances
- Révision de code et suggestions d'amélioration

### Prompts utiles pour Claude
- "Génère du code WordPress pour [fonctionnalité]"
- "Optimise cette fonction PHP pour WordPress"
- "Ajoute la gestion d'erreurs à ce code"
- "Crée une interface admin WordPress pour [fonction]"

## 📝 TODO et améliorations futures

### Fonctionnalités prévues
- [ ] Système de tokens JWT
- [ ] API REST pour traitement d'images
- [ ] Interface d'administration complète
- [ ] Gestion des permissions utilisateur
- [ ] Cache des images traitées
- [ ] Support multi-format d'images

### Optimisations techniques
- [ ] Tests unitaires PHPUnit
- [ ] CI/CD GitHub Actions
- [ ] Documentation PHPDoc complète
- [ ] Internationalisation complète
- [ ] Tests de performance

## 🆘 Résolution de problèmes courants

### SFTP ne synchronise pas
1. Vérifier les identifiants dans `.vscode/sftp.json`
2. Recharger la config : `Ctrl+Shift+P` → `SFTP: Reload Config`
3. Test manuel : `SFTP: Upload File`

### Erreur critique WordPress
1. Désactiver le plugin via FTP (renommer le dossier)
2. Vérifier les logs : `/wp-content/debug.log`
3. Corriger les erreurs de syntaxe PHP

### Git ne pousse pas vers GitHub
1. Vérifier l'authentification GitHub
2. Utiliser un token personnel si nécessaire
3. Vérifier la connexion : `git remote -v`

## 📞 Contacts et ressources

### Documentation WordPress
- [Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Coding Standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/)
- [Security](https://developer.wordpress.org/plugins/security/)

### Outils de développement
- [WordPress CLI](https://wp-cli.org/)
- [Query Monitor](https://wordpress.org/plugins/query-monitor/) (debug)
- [PHPStan](https://phpstan.org/) (analyse statique)

---

*Dernière mise à jour : 17 juin 2025*
*Développeur : Emmanuel*
*Assistant : Claude.ai*