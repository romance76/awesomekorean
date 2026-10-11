#!/usr/bin/env bash
# 배포 뒤 빠른 점검 (로그인 없이 확인 가능한 것만). 사용법: scripts/smoke.sh [주소]   (기본 https://awesomekorean.com)
BASE="${1:-https://awesomekorean.com}"
FAIL=0
chk() { # 설명 기대코드 메서드 경로
  local code; code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 -X "$3" -H 'Accept: application/json' "$BASE$4")"
  if [ "$code" = "$2" ]; then printf '  ✓ %-44s %s\n' "$1" "$code"; else printf '  ✗ %-44s %s (기대 %s)\n' "$1" "$code" "$2"; FAIL=1; fi
}
echo "스모크 테스트: $BASE"
chk "홈 화면"                          200 GET  /
chk "장터 화면"                        200 GET  /market
chk "광고 신청 화면"                   200 GET  /ads/apply
chk "상위노출 가격 설정(공개)"         200 GET  "/api/promotion/settings?resource=market"
chk "카드 최소 결제 금액(공개)"        200 GET  /api/payments/card-limits
chk "결제 내역은 로그인 필요"          401 GET  /api/payments/history
chk "광고 카드 확인은 로그인 필요"     401 POST /api/banners/1/confirm-payment
chk "상위노출 카드 확인은 로그인 필요" 401 POST /api/market/1/promote/confirm
chk "관리자 지급 기록은 로그인 필요"   401 GET  /api/admin/grants/recent
chk "관리자 회원 목록은 로그인 필요"   401 GET  /api/admin/users
# 카드 최소 금액이 숫자로 오는지
MIN="$(curl -s --max-time 20 "$BASE/api/payments/card-limits" | grep -o '"min_cents":[0-9]*' | head -1)"
if [ -n "$MIN" ]; then printf '  ✓ %-44s %s\n' "카드 최소 금액 값" "$MIN"; else printf '  ✗ 카드 최소 금액 값이 비어 있어요\n'; FAIL=1; fi
[ $FAIL -eq 0 ] && echo "스모크 테스트 통과" || echo "스모크 테스트 실패"
exit $FAIL
