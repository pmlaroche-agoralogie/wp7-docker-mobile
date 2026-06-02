Ce repository doit contenir les fichiers de création d'une image docker, dans laquelle on fait tourner un projet de site web, style appli mobile, en php/sqllite
L'image doit embarquer apache et php.

Voila les specifications : 
pour le visiteur : voir quelques pages de contenu, textes plus images, une partie news, avec un menu simple pour naviger entre les pages

L'UX doit etre celle d'une appli mobile, avec des menus repliés par defaut (quand on est sur mobile), et bouton  "COnenctez vous". NB : il n'y a pas d'informations tres personnelles, meme dans l'espace privé, on mettra par defaut une option où on reste connecté 3 ans quand on s'est connecté avec succes une fois


uniquement pour l'utilisateur qui s'est loggué avec login password : 
une menu visuel, avec quelques grands blocs 

+ module activite : presentation des activités recentes dans l'intranet 
+ module annonces :  les titres des trois dernieres petites annonces
+ module fichiers: les noms des trois derniers fichiers déposés dans files_bo consultables par l'utilisateur
+ module messagerie : les derniers messages reçus
+ module meteo : acces à un widget personalisé
+ module commandes : permet de voir des produits et de créer des paniers de commandes de ceux ci


pour un utilisateur admin, on aura en plus 

+module gestion des fichiers : ajout de fichiers pour un ou plusieurs utilisateurs ou groupes
+module gestion des utilisateurs :  liste, recherche, tri, ajout/disable de membres, creation de groupe
+module gestion des pages de contenus, en petit nombre, avec un menu simple
+module moderation petites annonces
+module suivi de l'activité 
+module suivi des commandes 



Il faut que je puisse faire tourner facilement l'image docker en local pour travailler sur le code, avec VS code branché. 
Les medias doivent être dans un dossier bien séparé.
