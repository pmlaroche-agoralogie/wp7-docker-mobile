#!/bin/sh

# --- Seed data on first run (volume empty) ---
if [ ! -f /var/www/data/app.sqlite ] && [ -d /var/www/data-init ]; then
    cp -r /var/www/data-init/. /var/www/data/
fi

if [ -z "$(ls -A /var/www/html/media 2>/dev/null)" ] && [ -d /var/www/html/media-init ]; then
    cp -r /var/www/html/media-init/. /var/www/html/media/
fi
# ---------------------------------------------

chown -R www-data:www-data /var/www/data 2>/dev/null || true
chown -R www-data:www-data /var/www/html/media 2>/dev/null || true
mkdir -p /var/www/html/uploads/produits
chown -R www-data:www-data /var/www/html/uploads 2>/dev/null || true
exec "$@"
