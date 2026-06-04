# Déploiement — app.elvea64-40.fr

## Prérequis

- Un compte DockerHub (ex : `toncompte`)
- Docker installé sur la machine de développement
- Serveur à l'IP `178.170.25.113`, Apache installé, DNS `app.elvea64-40.fr` pointant sur cette IP

---

## 1. Build et push de l'image (machine de développement)

Remplacer `toncompte` par l'identifiant DockerHub réel.

```bash
docker build -t toncompte/elvea64-app:latest .
docker login
docker push toncompte/elvea64-app:latest
```

Mettre à jour `docker-compose.prod.yml` : remplacer `TON_USER_DOCKERHUB` par `toncompte`.

---

## 2. Préparation du serveur (une seule fois)

### Installer Docker

```bash
curl -fsSL https://get.docker.com | sh
apt install docker-compose-plugin
```

### Créer les dossiers persistants

```bash
mkdir -p /srv/elvea64/{data,media}
```

### Copier le fichier Compose sur le serveur

```bash
scp docker-compose.prod.yml root@178.170.25.113:/srv/elvea64/
```

---

## 3. Démarrer le conteneur

```bash
docker compose -f /srv/elvea64/docker-compose.prod.yml pull
docker compose -f /srv/elvea64/docker-compose.prod.yml up -d
```

Le conteneur écoute sur `127.0.0.1:8095` (non exposé publiquement).

---

## 4. Configuration Apache (reverse proxy)

### Activer les modules

```bash
a2enmod proxy proxy_http
```

### Créer le VirtualHost

Fichier : `/etc/apache2/sites-available/elvea64.conf`

```apache
<VirtualHost *:80>
    ServerName app.elvea64-40.fr

    ProxyPreserveHost On
    ProxyPass        / http://127.0.0.1:8095/
    ProxyPassReverse / http://127.0.0.1:8095/

    ErrorLog  ${APACHE_LOG_DIR}/elvea64-error.log
    CustomLog ${APACHE_LOG_DIR}/elvea64-access.log combined
</VirtualHost>
```

### Activer le site et recharger Apache

```bash
a2ensite elvea64.conf
systemctl reload apache2
```

---

## 5. HTTPS avec Let's Encrypt (recommandé)

```bash
apt install certbot python3-certbot-apache
certbot --apache -d app.elvea64-40.fr
```

Certbot modifie automatiquement la config Apache et met en place le renouvellement automatique.

---

## 6. Mise à jour de l'application

Sur la machine de développement, après chaque modification :

```bash
docker build -t toncompte/elvea64-app:latest .
docker push toncompte/elvea64-app:latest
```

Sur le serveur :

```bash
docker compose -f /srv/elvea64/docker-compose.prod.yml pull
docker compose -f /srv/elvea64/docker-compose.prod.yml up -d
```

---

## Schéma du flux

```
Internet → Apache :80/:443 (app.elvea64-40.fr)
               ↓  ProxyPass
         Docker :8095 (conteneur php:8.2-apache)
               ↓  volumes
         /srv/elvea64/data/app.sqlite    base de données SQLite
         /srv/elvea64/media/             fichiers uploadés
```
