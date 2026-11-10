#!/bin/sh
set -e
cd /var/www/html
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache || true
if [ ! -d vendor ]; then
  composer install --no-interaction --prefer-dist --ignore-platform-reqs
fi
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
  php artisan key:generate --force || true
fi
i=0
until php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0);} catch (Exception \$e) { exit(1);}" 2>/dev/null; do
  i=$((i+1)); [ "$i" -gt 60 ] && break; sleep 2
done
TABLES=$(php -r "try { \$p=new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'),getenv('DB_USERNAME'),getenv('DB_PASSWORD')); echo (int)\$p->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn(); } catch(Exception \$e){ echo 0; }")
if [ "$TABLES" = "0" ] || [ -z "$TABLES" ]; then
  php artisan migrate --force
  php artisan db:seed --force
fi
exec "$@"