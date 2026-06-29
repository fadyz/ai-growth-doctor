#!/bin/sh
set -eu

cd /var/www/html

if [ "${APP_KEY:-}" = "" ]; then
    unset APP_KEY
fi

if [ ! -f .env ]; then
    cp .env.docker.example .env
fi

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

mkdir -p storage/app/ai-growth-doctor/pending \
    storage/app/ai-growth-doctor/processing \
    storage/app/ai-growth-doctor/processed \
    storage/app/ai-growth-doctor/failed \
    storage/app/ai-growth-doctor/runs \
    storage/app/ai-growth-doctor/forecasts \
    storage/app/ai-growth-doctor/evaluations \
    storage/app/ai-growth-doctor/calibrations \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --ansi
fi

php artisan config:clear --ansi || true
php artisan view:clear --ansi || true

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --ansi
fi

exec "$@"
