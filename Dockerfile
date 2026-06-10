FROM php:8.2-apache

RUN apt-get update && apt-get install -y libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/www/data /var/www/files_upload

COPY apache.conf /etc/apache2/sites-available/000-default.conf
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html

# Source baked into the image for production.
# In dev the docker-compose.yml volume mount overrides this.
COPY src/ /var/www/html/

# Initial data bundled as defaults — seeded on first run only (see entrypoint.sh).
COPY data/   /var/www/data-init/
COPY media/  /var/www/html/media-init/

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
