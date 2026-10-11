#!/usr/bin/env bash
# 수집 전용 큐 워커 설치 (할 일: 수집 버튼 큐화 후속) — 서버에서 root 로 "한 번만" 실행:
#   bash /var/www/awesomekorean/deploy/setup_collect_worker.sh
#
# 왜 필요한가: 관리자 "지금 수집"(음악·뉴스·숏츠·레시피)은 최대 20분 걸린다. 기존 워커 1개가 그동안
#   묶여 있으면 채팅·알림 실시간 전송이 밀린다. 수집만 따로 처리하는 두 번째 워커(--queue=collect)를 띄운다.
# 안전하게 만든 점: 여러 번 실행해도 같은 결과(멱등), 기존 워커 설정은 읽기만 하고 건드리지 않으며,
#   .env 는 바꾸기 전에 백업(.env.bak_collect_YYYYmmdd_HHMMSS)을 만든다. 값(비밀번호·키)은 출력하지 않는다.
set -euo pipefail

APP=/var/www/awesomekorean
PROG=awesomekorean-collect-worker
CONF=/etc/supervisor/conf.d/${PROG}.conf

[ "$(id -u)" -eq 0 ] || { echo "root 로 실행해 주세요 (sudo bash $0)"; exit 1; }
[ -f "$APP/artisan" ] || { echo "$APP/artisan 이 없어요. 경로를 확인해 주세요."; exit 1; }
command -v supervisorctl >/dev/null || { echo "supervisor 가 설치돼 있지 않아요 (apt install supervisor)"; exit 1; }

# 기존 큐 워커 설정에서 실행 사용자를 그대로 따라간다 (없으면 www-data)
RUN_USER=$(grep -rhE '^\s*user\s*=' $(grep -rlE 'queue:work' /etc/supervisor/conf.d/ 2>/dev/null | grep -v "$PROG" || true) 2>/dev/null | head -1 | sed -E 's/^\s*user\s*=\s*//' || true)
RUN_USER=${RUN_USER:-www-data}
echo "실행 사용자: $RUN_USER"

cat > "$CONF" <<EOF
[program:${PROG}]
process_name=%(program_name)s_%(process_num)02d
command=php8.2 ${APP}/artisan queue:work --queue=collect --sleep=3 --tries=1 --timeout=1500 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=${RUN_USER}
numprocs=1
redirect_stderr=true
stdout_logfile=${APP}/storage/logs/collect-worker.log
stopwaitsecs=1600
EOF
echo "supervisor 설정 저장: $CONF"

# .env 에 ADMIN_COLLECT_QUEUE=collect (이미 있으면 그 줄만 바꾸고, 없으면 추가). 먼저 백업.
ENVF="$APP/.env"
if grep -qE '^ADMIN_COLLECT_QUEUE=collect\s*$' "$ENVF"; then
  echo ".env: 이미 ADMIN_COLLECT_QUEUE=collect"
else
  cp -p "$ENVF" "$ENVF.bak_collect_$(date +%Y%m%d_%H%M%S)"
  if grep -qE '^ADMIN_COLLECT_QUEUE=' "$ENVF"; then
    sed -i -E 's/^ADMIN_COLLECT_QUEUE=.*/ADMIN_COLLECT_QUEUE=collect/' "$ENVF"
  else
    printf '\nADMIN_COLLECT_QUEUE=collect\n' >> "$ENVF"
  fi
  echo ".env: ADMIN_COLLECT_QUEUE=collect 로 설정 (백업 만듦)"
fi

# 워커를 먼저 띄운 "다음에" 설정 캐시를 갱신 → 수집 버튼이 새 큐로 보내도 받아 줄 워커가 이미 있다
supervisorctl reread
supervisorctl update
supervisorctl status | grep -E "${PROG}" || true
cd "$APP"
php8.2 artisan config:clear >/dev/null
php8.2 artisan queue:restart >/dev/null
echo
echo "완료. 확인: supervisorctl status  /  관리자 화면에서 '지금 수집'을 눌러 진행 상태가 올라가는지 봐 주세요."
echo "되돌리기: supervisorctl stop ${PROG}; rm $CONF; supervisorctl update; .env 의 ADMIN_COLLECT_QUEUE 줄을 지우고 php8.2 artisan config:clear"
