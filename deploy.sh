#!/bin/bash
# P2B-22: 단계별 로그 + 실패 시 명확한 에러 메시지
set -u
APP_DIR="/var/www/awesomekorean"
LOG="$APP_DIR/storage/logs/deploy.log"

log() { echo "[Deploy $(date +%Y-%m-%d_%H:%M:%S)] $*" | tee -a "$LOG"; }
fail() { log "❌ FAIL at step: $1 — $2"; exit 1; }

log "───────────── Deploy Starting ─────────────"
cd "$APP_DIR" || fail "cd" "APP_DIR not accessible"

# 두 배포가 동시에 git reset/npm build/php-fpm restart를 겹쳐 실행하면
# 서버가 일관되지 않은 상태로 남을 수 있음 — GitHub Actions concurrency
# 그룹이 1차 방어선이고, 이건 workflow_dispatch 수동 실행이 겹치는 등의
# 경우를 막는 2차 방어선.
exec 9>"$APP_DIR/storage/deploy.lock"
if ! flock -n 9; then
    log "⏳ 다른 배포가 이미 진행 중 — 끝날 때까지 대기"
    flock 9
fi

COMMIT_BEFORE=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
log "Previous commit: $COMMIT_BEFORE"

# Save build before git reset
cp -r public/build /tmp/awesomekorean_build_backup 2>/dev/null || true

git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true

log "▶ Step 1/7: git fetch + reset"
git fetch origin main 2>&1 | tail -5 >> "$LOG" || fail "git-fetch" "unable to fetch"
git reset --hard origin/main 2>&1 | tail -3 >> "$LOG" || fail "git-reset" "reset failed"
COMMIT_AFTER=$(git rev-parse --short HEAD)
log "New commit: $COMMIT_AFTER"

if [ ! -f "public/build/manifest.json" ]; then
    cp -r /tmp/awesomekorean_build_backup public/build 2>/dev/null || true
fi

log "▶ Step 2/7: composer install"
composer install --no-dev --optimize-autoloader --no-interaction -q 2>&1 | tail -10 >> "$LOG" || fail "composer" "check composer.lock drift"

log "▶ Step 3/7: npm install"
(npm ci --silent 2>/dev/null || npm install --silent) 2>&1 | tail -5 >> "$LOG" || fail "npm-install" "dependency resolution"

log "▶ Step 4/7: vite build (NODE_OPTIONS=--max-old-space-size=1024)"
export NODE_OPTIONS='--max-old-space-size=1024'
rm -rf public/build
npm run build 2>&1 | tail -10 >> "$LOG" || fail "vite-build" "빌드 실패 — OOM 여부 확인 (free -m)"

log "▶ Step 5/7: manifest copy"
mkdir -p public/build/.vite
cp public/build/manifest.json public/build/.vite/manifest.json || fail "manifest-copy" "manifest.json 부재"

log "▶ Step 6/7: migrate + optimize:clear"
php8.2 artisan migrate --force 2>&1 | tail -5 >> "$LOG" || log "⚠️ migrate returned non-zero"
php8.2 artisan optimize:clear 2>&1 | tail -3 >> "$LOG"

chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chown -R www-data:www-data "$APP_DIR/public/build" 2>/dev/null || true
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

log "▶ Step 7/7: php-fpm restart"
systemctl restart php8.2-fpm

rm -rf /tmp/awesomekorean_build_backup

log "✅ DEPLOY SUCCESS: $COMMIT_BEFORE → $COMMIT_AFTER"
log "─────────────────────────────────────────"
