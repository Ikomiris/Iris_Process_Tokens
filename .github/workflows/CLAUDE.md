name: Claude Code Assistant

on:
  issues:
    types: [opened, edited]
  issue_comment:
    types: [created, edited]
  pull_request:
    types: [opened, edited, synchronize]
  pull_request_review_comment:
    types: [created, edited]

jobs:
  claude-assistant:
    runs-on: ubuntu-latest
    if: |
      contains(github.event.comment.body, '@claude') || 
      contains(github.event.issue.body, '@claude') || 
      contains(github.event.pull_request.body, '@claude')
    
    steps:
      - name: Checkout repository
        uses: actions/checkout@v4
        with:
          fetch-depth: 0
          token: ${{ secrets.GITHUB_TOKEN }}

      - name: Run Claude Code Action
        uses: anthropics/claude-code-action@beta
        with:
          anthropic_api_key: ${{ secrets.ANTHROPIC_API_KEY }}
          model: claude-sonnet-4-20250514
          github_token: ${{ secrets.GITHUB_TOKEN }}

  # Révision automatique des Pull Requests
  auto-review:
    runs-on: ubuntu-latest
    if: github.event_name == 'pull_request'
    
    steps:
      - name: Checkout repository
        uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Claude Auto Review
        uses: anthropics/claude-code-action@beta
        with:
          anthropic_api_key: ${{ secrets.ANTHROPIC_API_KEY }}
          model: claude-sonnet-4-20250514
          github_token: ${{ secrets.GITHUB_TOKEN }}
          direct_prompt: |
            🔍 **Révision automatique du code WordPress**
            
            Analyse ce plugin WordPress pour :
            
            **Sécurité :**
            - Vérification des nonces
            - Sanitisation des entrées utilisateur
            - Échappement des sorties
            - Requêtes SQL préparées
            
            **Standards WordPress :**
            - Respect des conventions de nommage
            - Utilisation correcte des hooks
            - Gestion des capabilities
            - Structure des fichiers
            
            **Performance :**
            - Optimisation des requêtes DB
            - Chargement conditionnel des scripts
            - Gestion de la mémoire
            
            **Spécifique au plugin Iris Process :**
            - Gestion des tokens utilisateur
            - Sécurité des uploads d'images
            - Intégration API Python
            - Callbacks et AJAX
            
            Fournis des recommandations concrètes et prioritaires.