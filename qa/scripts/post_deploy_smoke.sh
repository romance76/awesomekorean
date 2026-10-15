#!/usr/bin/env bash
# 배포 직후 "사이트가 살아 있는지" 읽기 전용으로 확인한다. 로그인·결제·데이터 변경은 하지 않는다
# (로그인은 일부러 틀린 비밀번호로만 시험해서 401/422 가 나오는지만 본다).
#
# 사용: bash qa/scripts/post_deploy_smoke.sh [사이트주소]      예) bash qa/scripts/post_deploy_smoke.sh https://awesomekorean.com
# 모두 통과하면 마지막 줄이 "ALL OK" 이고 종료 코드 0, 하나라도 실패하면 "FAILED n" 과 종료 코드 1.
# 사람이 눈으로 확인할 부분(관리자 화면 왕복 등)은 qa/POST_DEPLOY_CHECKLIST.md 를 본다.
BASE="${1:-https://awesomekorean.com}"
fail=0

code() { curl -s -o /dev/null -m 20 -w "%{http_code}" "$@"; }
ok() { printf '  ok    %s\n' "$1"; }
bad() { printf '  FAIL  %s\n' "$1"; fail=$((fail + 1)); }
expect() { # 설명 기대코드 curl인자...
  local name="$1" want="$2"; shift 2
  local got; got=$(code "$@")
  if [ "$got" = "$want" ]; then ok "$name ($got)"; else bad "$name — 기대 $want, 실제 $got"; fi
}

echo "== 화면과 정적 파일 ($BASE)"
expect "홈" 200 "$BASE/"
expect "커뮤니티" 200 "$BASE/community"
expect "중고장터" 200 "$BASE/market"
expect "구인구직" 200 "$BASE/jobs"
expect "정보 글 목록" 200 "$BASE/info"
expect "빌드 파일 목록(manifest)" 200 "$BASE/build/manifest.json"
expect "사이트맵" 200 "$BASE/sitemap-main.xml"
expect "robots.txt" 200 "$BASE/robots.txt"

echo "== API"
expect "공개 설정" 200 "$BASE/api/settings/public"
expect "로그인(틀린 비밀번호는 401)" 401 -X POST "$BASE/api/login" -H 'Content-Type: application/json' -d '{"email":"smoke-test@example.invalid","password":"not-the-password"}'
expect "로그인(빈 요청은 422)" 422 -X POST "$BASE/api/login" -H 'Content-Type: application/json' -d '{}'
expect "2단계 인증 주소(로그인 안 하면 401)" 401 "$BASE/api/me/2fa" -H 'Accept: application/json'
expect "CSP 보고 수신(204)" 204 -X POST "$BASE/api/csp-report" -H 'Content-Type: application/csp-report' -d '{"csp-report":{"document-uri":"'"$BASE"'/","effective-directive":"img-src","blocked-uri":"https://smoke-test.invalid/x.png"}}'

echo "== 보안 헤더와 속도"
hdr=$(curl -sI -m 20 "$BASE/")
for h in "x-content-type-options" "x-frame-options" "strict-transport-security" "content-security-policy"; do
  if printf '%s' "$hdr" | grep -qi "^$h:"; then ok "헤더 $h"; else bad "헤더 $h 없음"; fi
done
t=$(curl -s -o /dev/null -m 20 -w "%{time_total}" "$BASE/")
if awk "BEGIN{exit !($t < 3.0)}"; then ok "홈 응답 ${t}초 (3초 미만)"; else bad "홈 응답이 느려요: ${t}초"; fi

echo
if [ "$fail" -eq 0 ]; then echo "ALL OK"; else echo "FAILED $fail"; fi
exit $((fail > 0 ? 1 : 0))
