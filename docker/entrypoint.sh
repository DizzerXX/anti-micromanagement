#!/bin/sh
# Prepares the data volume, migrates, and starts nginx + php-fpm.
set -e

DATA=/data
mkdir -p "$DATA/reports" "$DATA/fonts"
touch "$DATA/database.sqlite"

# Generate an app key once and keep it on the volume.
if [ -z "$APP_KEY" ]; then
    if [ ! -s "$DATA/app_key" ]; then
        php artisan key:generate --show > "$DATA/app_key"
    fi
    APP_KEY="$(cat "$DATA/app_key")"
    export APP_KEY
fi

# dompdf caches font metrics here.
rm -rf /var/www/html/storage/fonts
ln -s "$DATA/fonts" /var/www/html/storage/fonts

chown -R www-data:www-data "$DATA" /var/www/html/storage /var/www/html/bootstrap/cache

if [ "$LEDGER_SEED_SAMPLE_DATA" = "true" ]; then
    su-exec www-data php artisan ledger:install --sample
else
    su-exec www-data php artisan ledger:install
fi

su-exec www-data php artisan optimize

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
