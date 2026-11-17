#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ -z "${APP_KEY:-}" ] && ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/framework/testing storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
    php artisan migrate --force

    if [ "${RUN_SEEDER:-1}" = "1" ] && [ "${APP_ENV:-local}" != "production" ]; then
        php artisan db:seed --force
    fi
fi

php artisan storage:link 2>/dev/null || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

exec "$@"
