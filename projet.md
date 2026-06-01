Ce repository doit contenir les fichiers de création d'une image docker, dans laquelle on fait tourner un projet de site web style appli mobile, en php/sqllite
L'image doit embarquer mariadb, apache et php.

Voila les specifications : 
pour le visiteur : voir quelques pages de contenu, textes plus images, une partie news, avec un menu simple
L'UX doit etre celle d'une appli mobile, avec des menus repliés par defaut (quand on est sur mobile), et une option login. NB : il n'y a pas d'informations tres personnelle, meme dans l'espace privé, on mettra pa r defaut une option où on reste connecté 3 ans quand on s'est connecté avec succes une fois

pour l'utilisateur qui s'est loggué avec login password : 
une menu assez graphique qui donne acces  à des informations professionelles , organisées en quelques modules :
 fichiers personnels, commande produits (pas de comemrce, jsute des commandes) , la meteo personalsiée

pour l'adminisrateur 
-gerer les utilisateurs :  liste, recherche, tri, ajout/disable de membres

-permettre de modifier des pages de contenus, en petit nombre, avec un menu simple

-vue de l'activité

Il faut que je puisse faire tourner facilement l'image docker en local pour travailler sur le code, avec VS code branché. 
Les medias doivent être dans un dossier bien séparé.
