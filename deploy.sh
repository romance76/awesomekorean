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

# git reset 으로 새 PHP 코드는 이미 올라간 상태 — 빌드(수 분)가 끝난 뒤에 마이그레이션하면 그동안 새 코드가 옛 표 구조로 돌아
# 오류가 날 수 있으므로, 새 칸/표가 필요한 마이그레이션은 빌드 전에 바로 실행한다 (칸·표 추가만 하는 마이그레이션이라 옛 화면에도 안전)
log "▶ Step 2b/7: migrate (빌드 전)"
php8.2 artisan migrate --force 2>&1 | tail -5 >> "$LOG" || log "⚠️ migrate returned non-zero"

log "▶ Step 3/7: npm install"
(npm ci --silent 2>/dev/null || npm install --silent) 2>&1 | tail -5 >> "$LOG" || fail "npm-install" "dependency resolution"

log "▶ Step 4/7: vite build (NODE_OPTIONS=--max-old-space-size=1024)"
export NODE_OPTIONS='--max-old-space-size=1024'
# 새 빌드는 임시 폴더(public/build_next)에 만들고 끝나면 한 번에 교체한다.
# (예전엔 public/build 를 먼저 지우고 1~3분간 빌드해서, 그동안 Vite manifest 가 없어 사이트 전체가 500 이었음)
rm -rf public/build_next
VITE_OUT_DIR=public/build_next npm run build 2>&1 | tail -10 >> "$LOG"
# 빌드가 실패하면 기존 public/build 는 그대로 두고 중단 — 사이트는 이전 버전으로 계속 동작
[ -f public/build_next/manifest.json ] || fail "vite-build" "빌드 실패(기존 빌드 유지) — OOM 여부 확인 (free -m)"

log "▶ Step 5/7: manifest copy + 빌드 교체"
# 직전 빌드의 해시 파일을 14일간 보존: 오래 열어 둔 탭/설치형 앱이 옛 chunk 를 요청해도 404 가 나지 않게 함
# (배포 직후 옛 버전 화면에서 버튼이 안 눌리던 문제 방지). 새 빌드에 이미 있는 파일은 덮어쓰지 않음(cp -n).
if [ -d public/build/assets ]; then
    mkdir -p public/build_next/assets
    cp -np public/build/assets/* public/build_next/assets/ 2>/dev/null || true
    find public/build_next/assets -type f -mtime +14 -delete 2>/dev/null || true
fi
mkdir -p public/build_next/.vite
cp public/build_next/manifest.json public/build_next/.vite/manifest.json || fail "manifest-copy" "manifest.json 부재"
rm -rf public/build_prev
[ -d public/build ] && mv public/build public/build_prev
mv public/build_next public/build || fail "build-swap" "새 빌드 교체 실패"
rm -rf public/build_prev

log "▶ Step 6/7: migrate + storage:link + optimize:clear"
php8.2 artisan migrate --force 2>&1 | tail -5 >> "$LOG" || log "⚠️ migrate returned non-zero"
php8.2 artisan storage:link 2>&1 | tail -3 >> "$LOG" || true
php8.2 artisan optimize:clear 2>&1 | tail -3 >> "$LOG"
# 큐 워커는 켜진 채로 옛 코드를 기억하므로, 배포 후 안전하게 재시작시킨다(진행 중인 작업이 끝난 뒤 supervisor 가 다시 띄움)
php8.2 artisan queue:restart 2>&1 | tail -2 >> "$LOG" || log "⚠️ queue:restart 실패"

# 로고/앱 아이콘 업로드(AdminSettingsController::uploadLogo/uploadAppIcon)가
# storage/app/public/branding/에 저장함 — 최초 배포 때 기본값이 비어있으면
# manifest.json/이메일 템플릿이 깨진 이미지를 가리키므로, 저장소에 커밋된
# 디폴트 로고/아이콘을 한 번만 복사해둠(관리자가 업로드하면 이후엔 덮어써짐).
if [ ! -f "storage/app/public/branding/logo.png" ]; then
    mkdir -p storage/app/public/branding
    cp public/images/logo.png storage/app/public/branding/logo.png 2>/dev/null || true
    for size in 72 96 128 192 512; do
        cp "public/icons/icon-${size}x${size}.png" "storage/app/public/branding/icon-${size}x${size}.png" 2>/dev/null || true
    done
    cp public/icons/icon-192x192.png storage/app/public/branding/apple-touch-icon.png 2>/dev/null || true
    log "ℹ️ branding 디폴트 파일 시드 완료"
fi

chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chown -R www-data:www-data "$APP_DIR/public/build" 2>/dev/null || true
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

log "▶ Step 7/7: php-fpm restart"
systemctl restart php8.2-fpm

rm -rf /tmp/awesomekorean_build_backup

log "✅ DEPLOY SUCCESS: $COMMIT_BEFORE → $COMMIT_AFTER"
log "─────────────────────────────────────────"
