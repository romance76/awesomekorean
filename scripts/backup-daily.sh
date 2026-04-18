#!/bin/bash
# Phase 2-C 자동 DB 백업 (Kay 결정: 리텐션 30일)
# Cron: 0 3 * * *  /var/www/somekorean/scripts/backup-daily.sh
set -euo pipefail

APP_DIR="/var/www/somekorean"
BACKUP_DIR="/var/backups/somekorean"
RETENTION_DAYS=30
LOG="/var/log/sk_backup.log"

cd "$APP_DIR"
DB_USER=$(grep ^DB_USERNAME .env | cut -d= -f2)
DB_PASS=$(grep ^DB_PASSWORD .env | cut -d= -f2)
DB_NAME=$(grep ^DB_DATABASE .env | cut -d= -f2)

TS=$(date +%Y%m%d_%H%M)
OUT="$BACKUP_DIR/db_${TS}.sql.gz"

mkdir -p "$BACKUP_DIR"

echo "[$(date -Iseconds)] === backup start ===" >> "$LOG"

# server_backups 테이블에 running 레코드 insert (PHP 경유)
BACKUP_ID=$(php -r "
require '$APP_DIR/vendor/autoload.php';
\$app = require_once '$APP_DIR/bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
try {
  \$id = \\DB::table('server_backups')->insertGetId([
    'type' => 'db',
    'status' => 'running',
    'triggered_type' => 'cron',
    'started_at' => now(),
    'created_at' => now(),
  ]);
  echo \$id;
} catch (\\Throwable \$e) { echo 0; }
" 2>/dev/null || echo 0)

echo "[$(date -Iseconds)] backup id=$BACKUP_ID target=$OUT" >> "$LOG"

# 덤프 실행
if mysqldump --single-transaction --routines --triggers --events \
    -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" 2>>"$LOG" | gzip > "$OUT"; then

  SIZE=$(stat -c%s "$OUT")
  MD5=$(md5sum "$OUT" | awk '{print $1}')
  echo "[$(date -Iseconds)] dump ok size=$SIZE md5=$MD5" >> "$LOG"

  if [ "$BACKUP_ID" != "0" ]; then
    php -r "
require '$APP_DIR/vendor/autoload.php';
\$app = require_once '$APP_DIR/bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\\DB::table('server_backups')->where('id', $BACKUP_ID)->update([
  'status' => 'completed',
  'file_path' => '$OUT',
  'file_size_bytes' => $SIZE,
  'md5_hash' => '$MD5',
  'completed_at' => now(),
]);
" 2>/dev/null
  fi
else
  echo "[$(date -Iseconds)] dump FAILED" >> "$LOG"
  rm -f "$OUT"
  if [ "$BACKUP_ID" != "0" ]; then
    php -r "
require '$APP_DIR/vendor/autoload.php';
\$app = require_once '$APP_DIR/bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\\DB::table('server_backups')->where('id', $BACKUP_ID)->update([
  'status' => 'failed',
  'error_message' => 'mysqldump returned non-zero',
  'completed_at' => now(),
]);
" 2>/dev/null
  fi
  exit 1
fi

# 리텐션: 30일 이상 오래된 백업 삭제
DELETED=$(find "$BACKUP_DIR" -name "db_*.sql.gz" -mtime +$RETENTION_DAYS -delete -print | wc -l)
echo "[$(date -Iseconds)] retention: deleted $DELETED old backups (>$RETENTION_DAYS days)" >> "$LOG"

# DB 테이블 정리 (running 상태로 30일 이상 남은 것)
php -r "
require '$APP_DIR/vendor/autoload.php';
\$app = require_once '$APP_DIR/bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\\DB::table('server_backups')
  ->where('status', 'running')
  ->where('started_at', '<', now()->subDays($RETENTION_DAYS))
  ->update(['status' => 'failed', 'error_message' => 'abandoned (>retention)']);
" 2>/dev/null

echo "[$(date -Iseconds)] === backup done ===" >> "$LOG"
exit 0
