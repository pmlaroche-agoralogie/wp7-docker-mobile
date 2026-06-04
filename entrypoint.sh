#!/bin/sh
chown -R www-data:www-data /var/www/data 2>/dev/null || true
mkdir -p /var/www/html/uploads/produits
chown -R www-data:www-data /var/www/html/uploads 2>/dev/null || true
exec "$@"
