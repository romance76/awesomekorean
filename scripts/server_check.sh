#!/usr/bin/env bash
# 서버 읽기 전용 점검 (SSH 가 허용될 때만). 쓰기 없음 · 비밀값 출력 없음.
#   scripts/server_check.sh
# 자동 모드 분류기가 서버 접속을 막으면 우회하지 말고 사용자에게 허용을 요청하세요.
SSH="ssh -o ConnectTimeout=10 -o BatchMode=yes root@68.183.60.70"
$SSH 'cd /var/www/awesomekorean && {
  echo "== 마지막 배포"; tail -3 storage/logs/deploy.log | cut -c1-140
  echo "== 대기 중 마이그레이션 수"; php8.2 artisan migrate:status 2>/dev/null | grep -c Pending
  echo "== supervisor"; supervisorctl status | cut -c1-90
  echo "== 메모리(MB)"; free -m | sed -n 1,2p
  echo "== 오늘 오류(최근 5줄)"; grep -hE "production.ERROR" storage/logs/laravel-$(date +%Y-%m-%d).log 2>/dev/null | cut -c1-200 | tail -5
  echo "== 결제·인보이스 요약"; php8.2 artisan tinker --execute="
    echo \"payments: \".App\\Models\\Payment::count().\" (captured/completed: \".App\\Models\\Payment::whereIn(\"status\",[\"captured\",\"completed\"])->count().\")\".PHP_EOL;
    echo \"invoices: \".App\\Models\\Invoice::count().PHP_EOL;
    echo \"card min cents: \".App\\Support\\CardLimits::minCents().PHP_EOL;
    echo \"point_sales_enabled: \".(DB::table(\"point_settings\")->where(\"key\",\"point_sales_enabled\")->value(\"value\") ?? \"-\").PHP_EOL;
  " 2>&1 | tail -5
}'
