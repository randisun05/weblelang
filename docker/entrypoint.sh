#!/bin/sh
# Peran container ditentukan argumen pertama: web | queue | scheduler | reverb | artisan <cmd>
set -e
cd /app

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY belum diisi. Buat dengan: docker compose run --rm web artisan key:generate --show" >&2
    exit 1
fi

role="${1:-web}"

# Tunggu database siap (maks. ±60 detik).
if [ "$role" != "artisan" ]; then
    i=0
    until php artisan db:show >/dev/null 2>&1 || [ $i -ge 30 ]; do i=$((i+1)); sleep 2; done
fi

php artisan storage:link >/dev/null 2>&1 || true
php artisan optimize >/dev/null

case "$role" in
    web)
        # Migrasi hanya dari container web, dan hanya bila diizinkan.
        if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then php artisan migrate --force; fi
        exec frankenphp run --config /etc/caddy/Caddyfile
        ;;
    queue)
        exec php artisan queue:work --tries=3 --backoff=10 --max-time=3600 --sleep=2
        ;;
    scheduler)
        exec php artisan schedule:work
        ;;
    reverb)
        exec php artisan reverb:start --host=0.0.0.0 --port="${REVERB_SERVER_PORT:-8080}"
        ;;
    artisan)
        shift
        exec php artisan "$@"
        ;;
    *)
        exec "$@"
        ;;
esac
