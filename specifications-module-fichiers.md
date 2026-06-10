Il faut, derreire l'accès utilisateur, une bibliothèque de gestion de fichiers , en php, qui permette 
 d'acceder à des fichiers, de le uploader, de les organiser par repertoires virtuels 
 (avec jusqu'a 5 niveaux de sous-repertoire) .
 La gestion des fichiers doit être organisée pour être appelé par API, avec un points d'entrée public_html/api/put_user_file.php` appelé par des partenaires pour déposer des fichiers


un fichier appartient à un utilisateur, dont l'uid est donné par la tables de utilisateurs du site, et 
à un groupe, listé dans une table files_bo_groups, par defaut le groupe est l'uid du propriétaire

stockage des fichiers : 
les fichier physiques sont nommés avec un nom composé de 10 caracteres random (par exemple les 10 premiers 
caracteres d'un MD5 de (  nom du fichier plus le timestamp plus uid user plus random sur 10000) 
suivi de l'uid du proprietaire suivi du nom du fichier "sanitized"
leur vrai nom et les informations de propriétaire de groupe , ainsi que le sous-repertoire du fichier , 
sont stockés dans une table files_bo_name dans la même base
les fichiers sont stockés dans un repertoire en dessous de la racine web, ../files_upload, 
où il y a des sous-repertoires 1,2, ..., a, b c , et un second niveau de sous-repertoire. 
Un fichier dont le nom commence par 3b sera ecrit dans le sous-repertoire 3/b/nom_complet_du_fichier

A partir de cette bibliotheque, il faut proposer dans l'interface web :
 accès, pour un utilisateur authentifié, dans la partie contenu de la page, à la liste de ses repertoires et fichiers,
  comme dans l'explorateur de fichier windows, qui par defaut montre le sous-repertoire virtuel "/" .


On aura deux modules d'entrées : un module "fichiers" , qui permet d'acceder aux fichiers personnels, et un module "Fichiers groupes" pour tous ce qui est destinés à un groupe.

Si l'utilisateur fait partie de groupes, dans le module "Fichiers groupes" , il pourra aussi choisir un groupe et ensuite naviguer de la meme manière dedans

Sur l' ecran de base d'un moduel , on peut : 
donwload un fichier dans la liste, 
upload un fichier dans le repertoire courant, 
supprimer un fichier (avec un warning), 
si le fichier est de type image ou video, une icone oeil en bout de ligne permet de monter/jouer le media directement dans le navigateur)
cliquer sur un nom de sous-repertoire virtuel pour le choisir et le visualiser

Détail appel API put_user_files 
Clé API — vérifiée contre une valeur stockée dans un ficheir conf ou la base

Fichier —  extensions interdites (php*, exe, js, html…), patch RFC 2047 pour les noms encodés =?UTF-8?...?=.

Destinataires — tableau d'emails $_POST['destinataires'][], chacun vérifié contre la lsite des user existants

Sous-dossier — normalisé en virtual_path (ex: "Documents" → "/documents") avec validation de profondeur 1–5

Log dans ../files_upload/api_logs_YYYY-MM-DD.txt.

Paramètres de la requête POST :


api_key        = "votre_clé_secrète"
destinataires[0] = "user1@example.com"
destinataires[1] = "user2@example.com"
sous_dossier   = "Documents"
files          = [fichier binaire]