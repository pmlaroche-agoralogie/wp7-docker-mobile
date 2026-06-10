# ELVEA Béarn-Pays Basque-Landes — Site web

Structure d'un site PHP/SQLite conteneurisé, style application mobile, avec gestion d'utilisateurs, espace privé et pages de contenu éditables par blocs.

---

## Architecture des fichiers

```
wp7-docker-mobile/
├── Dockerfile              # PHP 8.2 + Apache + PDO SQLite
├── docker-compose.yml      # Volumes live src/, media/, data/
├── apache.conf             # VirtualHost avec AllowOverride All
├── entrypoint.sh           # Fix permissions /var/www/data au démarrage
├── Makefile                # Raccourcis : make up/down/build/logs/shell
├── src/
│   ├── index.php           # Front-controller / routeur
│   ├── config.php          # Constantes (DB_PATH, SITE_NAME, COOKIE_LIFETIME)
│   ├── .htaccess           # Tout → index.php sauf fichiers statiques
│   ├── assets/
│   │   ├── css/style.css   # CSS mobile-first, menu, galerie, lightbox, vidéo
│   │   └── js/app.js       # Menu hamburger, lightbox galerie (clavier + souris)
│   ├── includes/
│   │   ├── db.php          # PDO SQLite + init/migration automatique des tables
│   │   ├── auth.php        # login(), logout(), getCurrentUser(), guards
│   │   ├── header.php      # Logo + nav responsive ($extraHead pour CSS injectés)
│   │   └── footer.php      # Pied de page + overlay lightbox partagé
│   └── pages/
│       ├── home.php        # Accueil public
│       ├── news.php        # Actualités publiques
│       ├── content.php     # Rendu générique des pages par blocs
│       ├── login.php       # Formulaire de connexion
│       ├── logout.php      # Déconnexion + suppression cookie
│       ├── dashboard.php   # Espace privé — grille des modules
│       ├── 404.php         # Page d'erreur
│       └── admin/
│           ├── users.php   # Gestion utilisateurs (liste, ajout, activation)
│           ├── pages.php   # Gestion des blocs d'une page (ordre, suppression)
│           └── block.php   # Éditeur d'un bloc (HTML / galerie / vidéo)
├── media/                  # Fichiers média (volume monté, hors Git)
│   ├── logo-elvea64.jpg    # Logo affiché dans le header
│   ├── gallery/            # Images uploadées via les blocs galerie
│   └── videos/             # Vidéos uploadées via les blocs vidéo
└── data/                   # Base SQLite persistée (volume monté, hors Git)
```

---

## Base de données

Quatre tables créées automatiquement au premier démarrage :

| Table | Rôle |
|-------|------|
| `users` | Membres : username, email, hash, role, active |
| `remember_tokens` | Tokens de reconnexion automatique (3 ans) |
| `pages` | Métadonnées des pages (slug, titre) |
| `page_blocks` | Blocs de contenu ordonnés par page (type + contenu JSON) |

**Migrations automatiques** : si une page possède un contenu legacy dans `pages.content`, il est migré en premier bloc HTML au démarrage.

---

## Authentification

- Session PHP + cookie `remember_token` valide 3 ans
- Reconnexion automatique sans ressaisir le mot de passe
- Fonctions `requireLogin()` / `requireAdmin()` pour les pages protégées

**Compte admin par défaut** (créé si aucun admin n'existe) :
- Identifiant : `admin` — Mot de passe : `admin123`

---

## Pages et accès

| URL | Accès | Description |
|-----|-------|-------------|
| `/` | Public | Accueil avec hero et aperçu actualités |
| `/eleveurs` | Public | Page de contenu par blocs |
| `/filieres` | Public | Page de contenu par blocs |
| `/oribase` | Public | Page de contenu par blocs |
| `/adhesion` | Public | Page de contenu par blocs |
| `/news` | Public | Actualités |
| `/login` `/logout` | Public | Connexion / déconnexion |
| `/dashboard` | Connecté | Grille des modules (placeholders) |
| `/admin/users` | Admin | Liste, ajout, activation/désactivation |
| `/admin/pages` | Admin | Gestion des blocs de chaque page |
| `/admin/block` | Admin | Éditeur d'un bloc individuel |

---

## Système de blocs

Chaque page de contenu est composée de N blocs ordonnés, de trois types :

### Bloc HTML
- Éditeur WYSIWYG **Quill.js** (titres, gras/italique, listes, liens, images, blockquote)
- Contenu stocké en HTML dans `page_blocks.content`

### Bloc Galerie
- Upload multi-fichiers (JPG, PNG, WebP…) → `media/gallery/`
- Légende optionnelle par image, suppression individuelle
- Rendu : grille CSS responsive avec **lightbox** plein écran
- Lightbox : navigation ←/→ souris et clavier, fermeture Échap

### Bloc Vidéo
- URL **YouTube** ou **Vimeo** → iframe embed responsive 16/9
- URL directe `.mp4`/`.webm` ou upload → balise `<video controls>`
- Titre optionnel, aperçu live dans le formulaire admin
- Contenu stocké en JSON `{"src": "...", "title": "..."}`

### Gestion admin (`/admin/pages`)
- Vue liste : toutes les pages avec date de dernière modification
- Vue blocs : liste ordonnée avec badge de type, aperçu du contenu
- Actions : ↑ ↓ réordonner — ✎ éditer — ✕ supprimer
- Boutons d'ajout par type de bloc

---

## UX mobile

- Logo ELVEA + sous-titre dans le header
- Menu hamburger replié par défaut, déroulé au tap, auto-fermé à la navigation
- CSS mobile-first, breakpoints 540 px et 768 px
- Grille de modules : 1 colonne → 2 → 3 selon la largeur d'écran

---

## Lancer en local

```bash
make build   # Construire l'image (une fois, ou après modif du Dockerfile)
make up      # Démarrer le conteneur en arrière-plan
make logs    # Suivre les logs Apache/PHP
make shell   # Ouvrir un shell dans le conteneur
make down    # Arrêter et supprimer le conteneur
```

Commande shell directe (équivalent à `make shell`) :

```bash
docker compose exec web bash
```

Site disponible sur **http://localhost:8095**.

`src/` est monté en volume live : toute modification PHP est prise en compte immédiatement, sans rebuild.

---

## Modules à implémenter

- **Activités** — journal des actions dans l'intranet
- **Annonces** — petites annonces avec modération admin
- **Fichiers** — dépôt et consultation par utilisateur/groupe
- **Messagerie** — messages internes entre membres
- **Météo** — intégration d'un widget météo personnalisé
- **Commandes** — catalogue produits et gestion de paniers
