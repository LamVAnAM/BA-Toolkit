FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN if [ -f composer.json ]; then composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader; fi

FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libfreetype6-dev \
    libonig-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_sqlite sqlite3 gd curl zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN mkdir -p database private_uploads storage/logs storage/tmp \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 database private_uploads storage

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/ba-entrypoint.sh

RUN chmod +x /usr/local/bin/ba-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/ba-entrypoint.sh"]
CMD ["apache2-foreground"]
