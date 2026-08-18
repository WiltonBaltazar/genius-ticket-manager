#!/bin/sh
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "FATAL: APP_KEY is not set." >&2
    echo "Generate one with 'php artisan key:generate --show' and set it as the APP_KEY environment variable in Coolify — do not let this container generate one itself, since that would silently break decryption of existing sessions/data on every restart." >&2
    exit 1
fi

php artisan package:discover --ansi
php artisan filament:upgrade

php artisan storage:link || true

php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
