#!/bin/bash
set -e
APP_DIR="/var/www/somekorean"
echo "[Deploy] Starting..."
cd "$APP_DIR"
git config --global --add safe.directory "$APP_DIR"
git fetch origin main
git reset --hard origin/main
composer install --no-dev --optimize-autoloader --no-interaction -q
npm ci --silent
npm run build
php8.2 artisan migrate --force
php8.2 artisan config:cache
php8.2 artisan route:cache
php8.2 artisan view:cache
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
systemctl restart php8.2-fpm
echo "[Deploy] Done!"
