les utilisateurs conenctés peuvent consulter des petites annonces. 
Chaque annonce a un ou plusieurs photos ou video pour illustrer l'objet vendu. Un titre, un texte, un tag parmi plusieurs (matériels, aliments, animaux,..)  et un prix completent l'annnonce. 
Un utilisateur peut trier ou filtrer les annonces, et peut repondre à une annonce, le message est alors envoyé à l'administrateur.

L'adminitrateur peut voir la liste des annonces, en rendre certaines visibles ou invisible , saisir ou modifier une annonce.

---

## Implémentation

### Base de données (SQLite)

Trois tables ajoutées dans `src/includes/db.php` :

- **annonces** : `id`, `titre`, `texte`, `tag`, `prix` (nullable), `visible`, `created_at`, `updated_at`
- **annonce_medias** : `id`, `annonce_id` (FK), `fichier`, `type` (`photo` ou `video`), `position`
- **annonce_messages** : `id`, `annonce_id` (FK), `nom`, `email`, `message`, `lu`, `created_at`

Cinq annonces de démonstration sont insérées au premier démarrage (matériels, aliments, animaux).

Les tags disponibles sont définis comme constante PHP dans `src/config.php` :  
`ANNONCE_TAGS = ['matériels', 'aliments', 'animaux', 'services', 'divers']`

### Pages publiques (accès réservé aux utilisateurs connectés)

**`/annonces`** — liste de toutes les annonces visibles  
- Filtre par catégorie (tag) via un `<select>` soumis automatiquement  
- Tri par date décroissante/croissante ou prix croissant/décroissant  
- Grille de cartes (1 colonne mobile → 3 colonnes desktop) avec vignette, tag coloré, extrait du texte, prix, date

**`/annonce?id=X`** — détail d'une annonce  
- Galerie photos avec lightbox, vidéos en lecture directe  
- Texte complet, tag, prix, date de publication  
- Formulaire de réponse : un seul champ message — le nom et l'email sont automatiquement récupérés depuis le compte de l'utilisateur connecté

### Widget dashboard

Dans `src/pages/dashboard.php`, la carte "Annonces" affiche les 3 annonces les plus récentes (tag coloré, titre, prix) avec un lien direct vers chacune et un lien "Toutes →" vers la liste complète.

### Pages d'administration (`/admin/annonces`, `/admin/annonce-edit`)

**Liste admin** :  
- Toutes les annonces (visibles et masquées)  
- Bascule de visibilité en un clic  
- Compteur de messages non lus par annonce (badge rouge si non lus)  
- Section "Messages non lus" en bas de page, avec bouton "Marquer comme lu"  
- Suppression d'une annonce (supprime aussi les fichiers médias associés)

**Formulaire création/édition** :  
- Champs : titre, catégorie (select), prix (optionnel), description (textarea), visibilité  
- Upload de plusieurs fichiers en une seule sélection (photos : jpg/png/webp/gif, vidéos : mp4/webm/ogg/mov)  
- Affichage des médias existants avec suppression individuelle possible  
- Le répertoire `src/uploads/annonces/` est créé automatiquement au premier upload

### Navigation

Le lien "Annonces" dans le menu principal est visible uniquement pour les utilisateurs connectés.
