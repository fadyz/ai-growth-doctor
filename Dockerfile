FROM php:7.4-apache

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        default-mysql-client \
        libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/99-ai-growth-doctor-memory.ini

COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/ai-growth-doctor-entrypoint
RUN chmod +x /usr/local/bin/ai-growth-doctor-entrypoint

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .

RUN composer dump-autoload --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["ai-growth-doctor-entrypoint"]
CMD ["apache2-foreground"]
