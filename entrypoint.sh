#!/bin/sh
chown -R www-data:www-data /var/www/data 2>/dev/null || true
exec "$@"
