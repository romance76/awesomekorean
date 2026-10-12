# 할 일 대기열 (다른 Claude 계정이 위에서부터 그대로 실행)

`/continue` (또는 `qa/CONTINUE_PROMPT.md` 붙여넣기) → 이 파일에서 **status 가 `ready` 인 맨 위 작업**을 골라 끝까지 처리 → `scripts/ship.sh` 로 배포·검증 → 아래 상태 갱신 → Kay 에게 보고.
status 값: `ready`(바로 시작) · `needs_kay`(결정/확인 받기 전엔 시작 금지 — 질문만 모아서 하고 멈춤) · `blocked`(서버 권한 등) · `done`

공통 규칙은 `qa/HANDOFF_LATEST.md` 1번(일하는 방식)을 따른다: 존댓말, 파일 하나씩 커밋, 배포는 `scripts/ship.sh`, 서버 쓰기·삭제는 허용 받기, 더미 계정 삭제 금지, 게임·안심 보류.
작업을 끝내면 이 파일의 해당 작업 status 를 `done` 으로 바꾸고 결과·확인할 것을 한 줄 적은 뒤, **코드 배포가 success 가 된 다음** 문서를 별도 커밋으로 올린다(`SKIP_BUILD=1 scripts/ship.sh "인수인계 갱신" qa/TASKS_QUEUE.md qa/HANDOFF_LATEST.md`).

---

## T0. 먼저 확인 (매번 시작할 때, 5분)  — status: ready (항상)
1. `git status -s | grep -v '^??'` 로 미커밋 코드가 있는지(있으면 누가 하던 작업인지 `git diff` 로 확인), `git log --oneline -5`.
2. `gh run list --limit 3 --workflow "Deploy to Production"` 가 success 인지(실패면 `gh run view <id> --log-failed` 로 원인부터).
3. `bash scripts/smoke.sh` (통과해야 함) · 가능하면 `bash scripts/server_check.sh` (서버 읽기 전용, 막히면 건너뛰고 Kay 에게 허용 요청).
4. 이 파일과 `qa/HANDOFF_LATEST.md` 의 "Kay 확인 필요" 항목에 새 답이 있는지 확인.

---

## T1. 공동구매 구조 변경 (3단계) — status: needs_kay  (계획서 `qa/groupbuy_plan.md` 작성 완료 — Kay 가 7가지 결정에 답하면 ready 로 바꾸고 구현 시작)
**Kay 결정**: 이벤트(공동구매)를 **요청하는 사람(주최자)** 에게만 어코가 **오픈 수수료**를 받는다. **참가자는 결제 없음**. 이벤트가 끝나면 **참가자 명단과 정보를 주최자에게 제공**한다.
**현재 코드**: `app/Http/Controllers/API/GroupBuyController.php` — 참가자가 `join()`(285줄~)에서 **포인트로 참여금 결제**(332·349줄 `addPoints(-$amount…,'groupbuy_join')`), 취소·반려 시 `groupbuy_refund` 로 포인트 환불(245·399·475줄), `adminComplete()`(491줄)에서 주최자에게 마일스톤 Entry. 모델 `GroupBuy`, `GroupBuyParticipant`(paid_amount). 화면 `resources/js/pages/groupbuy/GroupBuyCreate.vue`·`GroupBuyDetail.vue`·`GroupBuyHome.vue`. 라우트 `routes/api.php` 182~185·380~384·936~938.
**할 일 (순서대로, 작게 나눠 각각 `ship.sh`)**
1. **조사 후 계획 한 장**을 `qa/groupbuy_plan.md` 에 쓰고 **Kay 에게 한 번 보여 주기**(아래 "결정 필요"). 계획 승인 전에는 결제·데이터 코드를 바꾸지 말 것. (계획만 쓰는 건 바로 해도 됨)
2. (승인 후) **참가 무료화**: `join()` 에서 포인트 차감 제거(기존 참가 기록·`paid_amount` 는 보존), 이미 포인트로 결제한 기존 참가자는 취소·반려 시 지금처럼 환불되게 유지(환불 경로는 남겨 둔다).
3. **주최자 오픈 수수료(카드)**: 공동구매 등록 → 카드 보류(`DirectPayments::begin`, 새 `Payment::KIND_GROUPBUY`, `DIRECT_KINDS` 에 추가, `AdPayments` 와 같은 흐름) → **관리자 승인 시 청구**, 반려·취소 시 보류 해제. 수수료는 `point_settings` 같은 설정값(예 `groupbuy_open_fee_cents`)으로 관리자 › 가격 센터에서 수정. 카드 최소 결제 금액(`CardLimits`)과 인보이스(`Invoicing::lineItemsFor`·`letter.blade.php` 종류 라벨) 반영, 매출 현황(`AdminRevenueController`) 포함.
4. **참가자 정보 제공 동의**: 참가 시 "종료 후 내 이름·연락처가 주최자에게 전달돼요" 체크 필수, 동의 시각 저장(`group_buy_participants` 새 칸).
5. **종료 후 명단 제공**: 주최자 전용 화면/내보내기(CSV) — 이름·이메일·전화·참가 일시·수량, **동의한 참가자만**, 이벤트 종료·관리자 완료 처리 이후에만 열림, 열람·내보내기 감사 로그.
6. 관리자 화면(`/admin/groupbuy` 계열)에서 수수료 상태(보류/청구/해제) 확인, 마이페이지 결제 내역·인보이스에 표시.
**결정 필요 (Kay)**: ① 오픈 수수료 금액(제안 $10, 설정으로 변경 가능) ② 수수료는 등록 시 보류→승인 때 청구(제안) vs 등록 즉시 청구 ③ 명단에 이메일·전화를 포함해도 되는지(동의 문구 확정).
**완료 확인**: 참가자가 결제 없이 참가 · 주최자 등록 시 카드 창 · 관리자 승인으로 청구(Stripe 테스트 카드 4242 4242 4242 4242) · 종료 후 주최자 명단 열람 · 인보이스에 "Group buy open fee" 표기 · `scripts/smoke.sh` 통과.

---

## T2. 월간 리더보드 + 차등 Entry (4단계) — status: needs_kay  (보상 개수·기준 확인 후 시작)
**Kay 결정**: 레시피 또는 이달의 **답글왕·앤서왕·글 많이 쓴 사람** 등의 리더보드를 만들고, **1~5등에게 차등 Entry** 를 지급할 수 있고, 보여 주는 곳(공개 화면)도 필요. 레시피 인기 보상은 **포인트 유지**(그대로 두기).
**제안 설계**: 월 1일 00:00(애틀랜타) 스케줄러가 지난달을 집계(`leaderboards` 표: month, category, rank, user_id, score) → 관리자 `/admin/grants`(지급·보상 센터)에 "리더보드" 탭에서 **확정 후 지급**(1위 5 · 2위 4 · 3위 3 · 4위 2 · 5위 1 Entry — 설정값) · 중복 지급 방지(idempotency `leaderboard:{month}:{category}:{rank}`) · 공개 화면 `/leaderboard`(이번 달 실시간 + 지난달 확정) · 홈/마이페이지에 내 순위. 부정 방지: 정지·더미·운영진 제외, 글자 수 최소 기준, 하루 한도 이미 있는 포인트 규칙과 별개로 "본문 N자 이상 글/댓글만" 집계.
**집계 기준 제안**: 글 많이 쓴 사람 = 공개 게시글 수 · 답글왕 = 댓글 수(본인 글 제외) · 앤서왕 = Q&A 채택된 답변 수 · 레시피 = 이달 레시피 찜(favorite_count) 증가량. 더미 계정(`@somekorean.local`, `@test.com`, `@example.com`)은 순위에서 제외.
**결정 필요 (Kay)**: ① 카테고리 목록 ② 1~5등 Entry 개수 ③ 최소 기준(글자 수) ④ 더미 제외 OK? ⑤ 지급은 관리자 확정 후(제안) vs 자동.

---

## T3. 포인트 → 기프트카드 교환 (5단계) — status: needs_kay  (T1·T2 뒤, 세무 확인 후)
**Kay 결정**: Amazon·Starbucks 두 종류만. **1,000P = $1, 수수료 10%(최소 $1), 최소 $10 카드, 월 $20 · 연 $500**(Kay 가 5번 자리에 쓴 값을 한도로 해석 — 확인 필요), 관리자 승인제.
**설계**: `point_redemptions` 표(user_id, brand, face_cents, points_spent, fee_points, status requested/approved/sent/rejected/cancelled, admin_id, code(암호화), note, timestamps) · 신청 시 포인트 즉시 보류(차감 기록 type `redeem_hold`), 거절·취소는 전액 환불 · 자격: 가입 30일, 이메일 인증 + 전화, 최근 30일 제재 없음, 월·연 한도 · 회원 화면(마이페이지 › 포인트 › 기프트카드 교환) · 관리자 화면(지급·보상 센터 탭 "교환 신청": 승인→코드 입력→발송 메일) · 비상 스위치 "교환 멈추기"(`EmergencySwitches`) · 약관·포인트 정책 개정 문구 · 감사 로그.
**주의**: 연 $600 이상 지급 시 미국 세금 서류(1099) 문제 → 연 한도를 $500 으로 둔 것으로 이해, 세무사 확인 권고. 이 작업은 돈과 직결 → **계획서를 먼저 Kay 에게 보여 주고 승인 후** 시작.

---

## T4. 카드 결제 시험 (Kay 가 직접 — 운영 사이트에는 카드 번호를 대신 입력하지 않음) — status: needs_kay
**Kay 확인(2026-10-11): Stripe 는 현재 테스트 모드** → 실제 카드로 시험하지 않는다(실제 카드는 거절됨). 테스트 카드 `4242 4242 4242 4242`, 아무 미래 날짜, 아무 CVC·우편번호. 결과는 Stripe 대시보드 "테스트 모드"에서 보임. 실결제 전환은 오픈 직전 Kay 가 키를 바꾼 뒤 $5 정도 1회만 하고 대시보드에서 직접 환불(카드 결제 환불 불가 정책).
카드 최소 금액 미만일 때 막다른 길이 없는지도 함께 본다: 상위노출 7일 $3.50 선택 → "10일로 늘리기 · $5.00" / "포인트로 결제하기" 버튼, 광고 신청 → "$5.00 으로 올리기" 버튼. 시나리오: ① 광고(텍스트) 신청 → 카드 확인(보류) → 관리자 승인(청구) → 인보이스에 카드 종류·끝 4자리 ② 광고 거절(보류 해제) ③ 상위노출 카드 결제(최소 $5 이상이 되는 기간) → 즉시 적용 ④ 포인트 부족/카드 최소 금액 미만 안내 문구 ⑤ 인보이스 영어/한국어 전환(우측 상단 EN 토글) ⑥ 지급·보상 센터에서 Zelle 직접 기록 → 인보이스. 문제가 나오면 `storage/logs/laravel-*.log`(서버 읽기)로 원인 확인.

---

## T5. 남은 작은 정리 — status: done  (2026-10-11 UTC 15:55 / 애틀랜타 오전 11:55, 4건 모두 배포·검증)
- ✅ 일괄 등급 변경 — 커밋 `0059cd46`. PC 회원 목록에서 여러 명 선택 → "일반회원으로 / 기업회원으로"(최고관리자에게만 보임). `AdminController::bulkUsers` 에 `action=role` 추가: 최고관리자 전용(아니면 403), 일반·기업으로만(운영진 승급은 회원 상세에서 한 명씩), 본인·운영진·이미 같은 등급은 건너뜀, 바뀐 회원마다 `admin_audit_log` 한 줄(사유 "일반회원 → 기업회원 (일괄 등급 변경)"). 서버에서 롤백되는 트랜잭션으로 6가지 시나리오 확인(롤백 후 역할·감사 로그 원상태).
- ✅ 경품 매분 작업 3개 합치기 — 커밋 `5612c1a9`. 새 명령 `sweepstakes:tick`(`app/Console/Commands/SweepstakesTick.php`)이 `send-reminders` → `notify-winners` → `run-schedules` 를 차례로 실행(한 단계가 실패해도 다음 단계 계속), 스케줄은 `everyMinute()->withoutOverlapping(10)`. 옛 3개 명령은 그대로 남아 따로 실행 가능. 서버 `schedule:list` 에 tick 하나만 보이고 배포 후 오류 없음.
- ✅ 관리자 광고 목록 페이지 넘기기 — 커밋 `23b3a88e`. 휴대폰·PC 목록이 50건씩 "더 보기", 90일 이전에 끝난 광고는 "90일 이전 지난 광고 보기" 로 50건씩 추가 로드(`GET /api/admin/banners?older=1&page=N`, 기본 응답은 그대로). 지금 운영 서버는 광고가 0건이라 빈 화면은 "광고 신청 없음".
- ✅ 휴대폰 회원 상세 "지급·보상 센터로 이동 ›" — 커밋 `57ccd182`.
- 환불 정책 2단계는 **Kay 보류** — 건드리지 말 것.
- (미루어 둔 작은 메모) 지난 광고 페이지를 넘기는 중에 광고를 삭제하면 다음 페이지에서 한 건이 건너뛰어질 수 있음(관리자 전용·드문 경우).

---

## T6. 메뉴 개편 (묶음 메뉴 + 관리자 메뉴 트리 + MONEY 허브) — status: **done** (1단계·2단계 모두 배포, Kay 가 스위치 켜고 MONEY 공개 — 2026-10-12)  (기록 `qa/HANDOFF_LATEST.md` 11·12번, 계획 `qa/menu_restructure_plan.md`·`qa/money_hub_plan.md`)
**(완료) 2단계 할 일**: `qa/money_hub_plan.md` 를 쓴다 — `/money` 허브(샘플 구조), 정보 공유 카페 12개(가입 없이 보기·답글, 글쓰기 로그인, 카페 운영자 나중), 카페 전체 안내 페이지 + 카페별 대문(관리자가 편집), 머니 가이드(설명 페이지, 관리자 작성), 데이터 구조(기존 `boards`/`posts` 재사용 vs 새 표 — 장단점 비교), 관리자 화면, 단계별 배포 순서, 금융·세무 안내 문구. 머니 게시판 없음·내돈내산 별개·동호회는 커뮤니티. 계획서를 Kay 에게 보여 주고 확인받은 뒤 구현. 구현 후 관리자 › 메뉴 묶음에서 MONEY 숨김 해제.
Kay 확정(2026-10-12): 홈 · 커뮤니티(게시판·Q&A·채팅·동호회) · 구인구직 · 부동산 · 사고팔기(중고장터·공동구매) · 업소·광고(업소록·NEW 전단) · 생활정보(정보·뉴스·레시피·이벤트) · 즐길거리(숏츠·음악듣기) · 내돈내산 리뷰(단독) · MONEY(허브: 정보 공유 카페 12개(인증 릴레이 포함)·머니 가이드, 머니 게시판 없음). 친구는 MY 로. 모든 페이지 머리줄 한 규칙.
1단계(메뉴 트리·상단/휴대폰 메뉴·PageHead, 스위치 꺼 둔 채 배포) → 2단계(MONEY, 별도 계획서). Kay 1단계 OK(2026-10-12). MONEY 정식 이름은 미정(관리자 화면에서 변경 가능). 2단계는 1단계 후 별도 계획서.

---

## T7. MONEY 다음 다듬기 — status: needs_kay
1) ~~가이드 본문 41편~~ **(2026-10-12 완료·공개 — HANDOFF 14번, 이제 남은 건 내용 검수)** 2) MONEY 정식 이름 3) 후보: 카페 운영자 지정 화면, 머니 글 신고·좋아요·인기글, 홈 MONEY 카드, 사이트 검색에 머니 글 포함. Kay 가 고르면 ready.
- (2026-10-12 완료) 카페 대문 꾸미기·주제·공지 고정·정렬 — HANDOFF 13-2.

## T8. MONEY AI 콘텐츠 자동화 (GPT 지시서) — status: needs_kay
GPT 지시서의 AI 글 생성 파이프라인. API 키·월 예산·사실 확인 책임 결정 필요. 한다면: AI 초안 → 관리자 승인 후 공개, 출처 칸, 월 예산 상한, 가짜 회원 글 금지. 계획서 먼저(`qa/money_ai_plan.md`) → Kay 확인 → 시작.

---

## T9. MONEY 카페 운영팀 글 채우기 — status: ready (Kay 지시로 계속 진행 중, 백그라운드 가능)
기준·방법·진행표는 `qa/HANDOFF_LATEST.md` 15-1. 완료: earn·save·grow·home·insurance·tax(36)·cards(36)·stocks(27)·kids(36)·retire(36). **남음: relay·talk — 회원 참여 카페, Kay 확인 후 시작(안내·질문 형 글만, 가짜 인증 금지)** 주제 6개(18편)씩 `scripts/ship.sh`(이미지+JSON만이면 SKIP_BUILD=1) 배포 → API total 확인 → 이 줄 갱신.

## T10. 내돈내산 카테고리·썸네일 정리 — status: done (2026-10-12, HANDOFF 15-2)

---

## 보류/금지 (손대지 말 것)
환불 정책 · 더미 계정/동호회 삭제(#57·#75) · 게임·안심 서비스 · 소셜 가입·2FA·애드센스 · Resend SPF/DKIM · 서버 업그레이드·백업·재부팅(Kay 직접).
