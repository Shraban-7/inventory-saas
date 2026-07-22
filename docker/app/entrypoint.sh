#!/usr/bin/env sh
set -eu

role="${1:-web}"

mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ "${APP_ENV:-production}" != "local" ] && [ "${SKIP_CONFIG_CACHE:-0}" != "1" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache || true
    php artisan view:cache || true
fi

case "${role}" in
    web)
        php-fpm -D
        exec nginx -g 'daemon off;'
        ;;
    horizon)
        exec php artisan horizon
        ;;
    scheduler)
        exec php artisan schedule:work --verbose --no-interaction
        ;;
    migrate)
        # One-shot release migrations only — never from the web role.
        exec php artisan migrate --force
        ;;
    *)
        exec "$@"
        ;;
esac
