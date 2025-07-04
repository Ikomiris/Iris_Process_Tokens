# Notes de développement - Image Processor Tokens

## 🔧 Configuration technique actuelle

### Environnement de développement
- **OS :** Windows
- **IDE Principal :** Visual Studio Code
- **IDE Secondaire :** Cursor (assistance IA Claude)
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

## 📁 Architecture détaillée

### Structure modulaire (Version 1.0.6)

image-processor-tokens/
├── image-processor-tokens.php              # Point d'entrée principal
│   ├── Constantes globales (API_URL, VERSION, PATHS)
│   ├── Autoloading des classes
│   ├── Hooks d'activation/désactivation
│   └── Initialisation du plugin
│
├── includes/                               # Logique métier
│   ├── class-iris-process-main.php            # Singleton principal
│   │   ├── Chargement des dépendances
│   │   ├── Initialisation des hooks
│   │   ├── Enqueue scripts/styles
│   │   └── Gestion i18n
│   │
│   ├── class-database.php                     # Schéma BDD
│   │   ├── Création des tables
│   │   ├── Migrations automatiques
│   │   └── Structure : tokens, transactions, jobs, processes
│   │
│   ├── class-token-manager.php                # Gestion jetons
│   │   ├── Soldes utilisateur
│   │   ├── Ajout/déduction de jetons
│   │   ├── Historique transactions
│   │   └── Statistiques globales
│   │
│   ├── class-xmp-manager.php                  # Gestion XMP simplifiée
│   │   ├── Upload presets par format
│   │   ├── Stockage standardisé (/xmp-presets/)
│   │   ├── Preset par défaut automatique
│   │   └── Interface admin épurée
│   │
│   ├── class-image-processor.php              # Traitement images
│   │   ├── Validation fichiers
│   │   ├── Application presets XMP
│   │   ├── Appels API RawPy + ExtractIris
│   │   └── Gestion callbacks
│   │
│   ├── class-ajax-handlers.php                # Endpoints AJAX
│   │   ├── Upload images
│   │   ├── Vérification statuts
│   │   ├── Tests API
│   │   └── Téléchargements
│   │
│   ├── class-rest-api.php                     # API REST
│   │   ├── /iris/v1/callback (callback Python)
│   │   ├── /iris/v1/status/{job_id}
│   │   └── /iris/v1/stats (admin)
│   │
│   ├── class-user-dashboard.php               # Interface utilisateur
│   ├── class-surecart-integration.php         # Intégration e-commerce
│   └── functions-helpers.php                  # Utilitaires globaux
│
├── admin/                                  # Interface administration
│   ├── class-admin-menu.php                   # Menus WP Admin
│   │   ├── Tableau de bord principal
│   │   ├── Configuration API
│   │   ├── Gestion jobs
│   │   └── Intégration presets XMP
│   │
│   └── class-admin-pages.php                  # Templates admin
│
├── shortcodes/                            # Shortcodes frontend
│   └── class-shortcodes.php                   # Interface utilisateur
│       ├── [iris_upload_zone] - Zone complète
│       ├── [user_token_balance] - Solde
│       ├── [token_history] - Historique
│       └── [iris_process_page] - Page dédiée
│
└── assets/                                # Ressources statiques
├── css/
│   ├── iris-upload.css                    # Styles frontend
│   └── iris-admin.css                     # Styles admin
└── js/
├── iris-upload.js                     # JavaScript frontend
└── iris-admin.js                      # JavaScript admin

## 🔄 Workflow de développement

### 1. Développement local (optionnel)
```bash
# Démarrer XAMPP si tests locaux nécessaires
# Modification des fichiers dans VS Code
# Sauvegarde automatique → Upload SFTP vers Hostinger