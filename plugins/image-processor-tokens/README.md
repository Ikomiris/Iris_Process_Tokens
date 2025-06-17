# Image Processor Tokens - Plugin WordPress

Plugin WordPress pour le traitement d'images avec système de tokens développé pour le site iris4pro.com.

## 📋 Description du projet

Ce plugin WordPress permet de traiter et manipuler des images en utilisant un système de tokens sécurisé. Il s'intègre parfaitement dans l'écosystème WordPress et offre une interface d'administration intuitive.

## 🛠 Configuration de développement

### Environnement local
- **IDE Principal :** Visual Studio Code
- **IDE Secondaire :** Cursor (pour l'assistance IA)
- **Serveur local :** XAMPP (Apache + PHP + MySQL)
- **Versioning :** Git + GitHub

### Environnement de production
- **Hébergeur :** Hostinger.com
- **Site :** iris4pro.com
- **Synchronisation :** SFTP automatique via VS Code

### Extensions VS Code utilisées
- PHP Extension Pack
- Python (extension officielle Microsoft)
- Live Server
- GitLens
- SFTP (Natizyskunk)

## 📁 Structure du projet

```
Iris_Process_Tokens/
├── .vscode/
│   └── sftp.json              # Configuration SFTP (exclu de Git)
├── plugins/
│   └── image-processor-tokens/
│       ├── image-processor-tokens.php  # Fichier principal du plugin
│       ├── includes/          # Fonctions et classes
│       ├── admin/            # Interface d'administration
│       ├── assets/           # CSS, JS, images
│       └── languages/        # Fichiers de traduction
├── .gitignore               # Fichiers exclus de Git
└── README.md               # Ce fichier
```

## 🚀 Installation et configuration

### Prérequis
- WordPress 5.0+
- PHP 7.4+
- Environnement de développement configuré

### Installation
1. Cloner le repository
2. Configurer les identifiants SFTP dans `.vscode/sftp.json`
3. Synchroniser avec le serveur de production
4. Activer le plugin dans WordPress Admin

## 🔄 Workflow de développement

1. **Développement local :** Modification des fichiers dans VS Code
2. **Synchronisation automatique :** Upload SFTP vers Hostinger
3. **Test en production :** Test direct sur iris4pro.com
4. **Versioning :** Commit et push vers GitHub

## 🔐 Sécurité

- Fichier `.vscode/sftp.json` exclu de Git (contient les identifiants)
- Validation et échappement de toutes les entrées utilisateur
- Utilisation des API WordPress sécurisées

## 🤖 Assistance IA

Ce projet est développé avec l'assistance de Claude.ai pour :
- Génération de code PHP WordPress
- Résolution de problèmes techniques
- Optimisation des performances
- Respect des bonnes pratiques WordPress

## 📝 Notes de développement

- Synchronisation SFTP configurée vers `/wp-content/plugins/image-processor-tokens/`
- Tests effectués directement sur l'environnement de production
- Sauvegarde automatique sur GitHub après chaque session de développement

## 👥 Contributeurs

- **Développeur principal :** Emmanuel
- **Assistant IA :** Claude.ai (Anthropic)

## 📞 Support

Pour toute question ou problème, consulter la documentation WordPress ou utiliser Claude.ai pour l'assistance technique.

---

*Dernière mise à jour : 17 juin 2025*