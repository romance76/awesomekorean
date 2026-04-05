#!/bin/bash
set -e
APP_DIR="/var/www/somekorean"
LOG="$APP_DIR/storage/logs/deploy.log"
echo "[Deploy] Starting... $(date)" >> "$LOG"
cd "$APP_DIR"

# Save build before git reset
cp -r public/build /tmp/somekorean_build_backup 2>/dev/null || true

git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true
git fetch origin main
git reset --hard origin/main

# Restore build if git nuked it
if [ ! -f "public/build/manifest.json" ]; then
    cp -r /tmp/somekorean_build_backup public/build 2>/dev/null || true
fi

composer install --no-dev --optimize-autoloader --no-interaction -q
npm ci --silent 2>/dev/null || npm install --silent
rm -rf public/build && npm run build

# Ensure .vite manifest exists for Laravel
mkdir -p public/build/.vite
cp public/build/manifest.json public/build/.vite/manifest.json

php8.2 artisan migrate --force 2>/dev/null || true
php8.2 artisan optimize:clear
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
systemctl restart php8.2-fpm

# Cleanup
rm -rf /tmp/somekorean_build_backup

echo "[Deploy] Done! $(date)" >> "$LOG"
