# ⭐ 최신 인수인계 (다른 계정/세션에서 이어 받을 때 가장 먼저 읽기)

작성: 2026-10-11 UTC 16:00 (애틀랜타 낮 12:00) · 마지막 배포 코드 `385d227c` (Deploy·PHP Syntax Check 모두 success) · **Stripe 는 테스트 모드(Kay 확인) — 테스트 카드 4242 4242 4242 4242 로 시험** · 최신 변경은 맨 아래 9번
**한 방 실행: 새 Claude 계정에서 `/continue` 한 줄** (안 되면 `qa/CONTINUE_PROMPT.md` 의 글을 붙여넣기). 할 일은 `qa/TASKS_QUEUE.md`(위에서부터 ready 인 것), 배포·검증은 `scripts/ship.sh` 가 한 번에 처리합니다.

읽는 순서: **이 파일 → `qa/points_entry_ads_structure_report.md`(돈/포인트/Entry 설계·Kay 결정) → `qa/WORK_ORDER_2026-10-11.md`(오늘 작업 전체 기록·서버 작업) → `qa/HANDOFF_2026-10-11.md`(옛 기록) → `CLAUDE.md`**
(`qa/NEXT_SESSION_START.md` 는 4월 자료 — 무시)

---

## 1. 일하는 방식 (Kay 확정 — 반드시 지킬 것)
1. **모든 답변은 존댓말**, 쉬운 말. Kay 는 Atlanta 거주 한국인 → 시간은 UTC(애틀랜타/EDT) 병기.
2. **한 단계 안에서는 직접 끝까지**: 수정 → 로컬 빌드 → 커밋(파일 하나씩 지정, `git add -A` 금지) → `git fetch` → `git push origin HEAD:main` → 배포 확인 → Kay 에게 바로 보고. 승인받으려 멈추지 않음.
3. **다음 단계로 넘어가는 건 Kay 가 "넘어가/계속해/시작" 이라고 해야 함.** (Kay 가 범위를 정해 "여기까지 연속"이라 하면 그 안에선 멈추지 않음.)
4. **큰 작업·돈·보안·삭제 관련은 시작 전에 계획을 먼저 보여 주고 확인**. 결정이 필요한 건 한 번에 모아서 질문.
5. 병렬 멀티에이전트 워크플로우는 쓰지 않음(토큰 소모). 직접 하나씩.
6. **절대 금지**: 수집 데이터(news/music_tracks/shorts/businesses/recipe_posts) 수정·삭제, 테이블 DROP, main force-push, 비밀번호·API 키 값 출력/저장, 게임·안심(elder) 서비스 작업(보류 중 — 포커/게임머니 환전도 건드리지 말 것).
7. 커밋 금지 파일: `.env`, `somekorean-c9430-firebase-adminsdk-*.json`, `qa/backups/naver_scraper.db`, `CLAUDE.md`(원래 미추적).
8. 커밋 메시지 끝: `Co-Authored-By: <현재 모델 이름> <noreply@anthropic.com>` (세션 지침의 attribution 줄을 따를 것).
9. **마무리할 때마다 이 파일(`HANDOFF_LATEST.md`)의 "현재 상태·다음 할 일"을 갱신**하고 코드 배포가 끝난 뒤에 문서를 push (문서만 먼저 push 하면 진행 중 배포가 줄을 섬).

## 2. 배포·확인·도구
- 서버: `ssh root@68.183.60.70`, 실제 경로 `/var/www/awesomekorean`. `git push origin HEAD:main` → GitHub Actions → `deploy.sh` (migrate 가 빌드 전에 실행, 빌드 5~7분).
- **배포 확인**: `gh run list --limit 3 --workflow "Deploy to Production"` (success). 서버 접속·DB 조회는 자동 모드 분류기가 막는 때가 있음 → **우회 금지, Kay 에게 허용 요청**. (이번 세션 중간부터는 SSH 읽기/실행이 허용됐음.)
- CI: push 하면 `PHP Syntax Check`(app·routes·config·database·bootstrap 문법 + 프런트 vite 빌드)가 돌음. 로컬엔 PHP 없음.
- 프런트 빌드 확인(로컬): `node node_modules/vite/bin/vite.js build --outDir "$TEMP/vbuild_check" --emptyOutDir`
- **편집 함정(중요)**: bash heredoc / `node -e` 안에서 **백슬래시가 사라짐** (`\App\Support\X` → `AppSupportX`). PHP 는 `use` 문 + 짧은 이름을 쓰거나 Edit/Write 도구로 직접 수정. 수정 뒤 `grep -rn "AppSupport\|AppModels\|AppServices" app routes database bootstrap` 로 깨진 곳 확인. JS `String.replace(a,b)` 는 `() => b` 사용. 멀티라인 큰 변경은 Write 로 `.mjs` 파일을 만들어 실행하고 나서 지울 것.
- 새 관리자 API 는 반드시 `app/Http/Middleware/AdminTier.php` 규칙표에 등급 지정(운영자 1 · 관리자 2 · 최고관리자 3, 기본 2).
- 서버 읽기 전용 점검 예: `ssh root@68.183.60.70 'cd /var/www/awesomekorean && php8.2 artisan tinker --execute="..."'` (비밀값 출력 금지).

## 3. 현재 상태 (2026-10-11 UTC 11:00)

### 3-1. 사이트 구조 개편(관리자) — 완료
7그룹 메뉴 · 공용 부품 · 휴대폰 하단 탭+같은 그룹 칩 줄 · 사이트 설정 분리(9 컴포넌트) · 회원 패널(PC) 정리·일괄 정지/해제·검색 확장 · 감사 로그 화면 · 관리자 즐겨찾기(서버 저장) · 환불 사유 기록 · IP 차단 기간·CIDR · 한국어 검증 메시지 · HTML 저장 방지 · 수집 전용 큐 워커 설치(서버 RUNNING) · nginx bak 정리(`/root/nginx_bak_20261011/` 보관) · 오픈 이벤트(10/15~11/30) 켜짐(상태 '예정') · Chloe(2905) super_admin.

### 3-2. 돈 / 포인트 / Entry 구분 — Kay 결정 (2026-10-11)
| 구분 | 얻는 법 | 쓰는 곳 | 비고 |
|---|---|---|---|
| **돈(USD 카드)** | 카드 결제 | **광고 전부**(배너·텍스트·NEW 전면광고), 상위노출은 카드 선택 가능 | 카드 결제 **환불 불가**, 인보이스 자동 발행 |
| **포인트** | 가입·프로필·글·댓글·좋아요 받음·Q&A 채택·레시피 인기 보상 | 끌어올리기·채팅방 개설/입장·장터 홀드·추가 사진·**상위노출(포인트 선택)** | **판매 중단**(`point_sales_enabled=0`), 나중에 **기프트카드 교환**(Amazon·Starbucks) |
| **Entry** | 출석·내돈내산 리뷰(자동)·이벤트 완료 인증(이벤트별 개수, 비우면 1)·거래/채용/공동구매 완료·가입/인증 보너스·관리자 지급 | **어코 경품 추첨 응모만** | 교환·판매 없음. 일반 글쓰기로는 안 쌓임(`activity_required_count=0`) |

- **광고 가격 단위**: 예전 포인트 숫자를 그대로 **센트**로 봄(100 = $1.00). 예) 왼쪽 프리미엄 최저 8,000 → $80/월, 텍스트 광고 30일 1,000 → $10. 관리자 › 가격 센터 › 광고 가격에서 숫자를 바꾸면 반영(입력 아래 달러 환산 표시). 최소 청구 $0.50. 오픈 이벤트 할인은 청구액에만.
- **광고 결제 흐름**(NEW 전면광고와 동일): 신청 → 카드 확인(보류만, `awaiting_payment`) → 확인 완료(`pending`, 승인 대기) → **관리자 승인 시 청구** → 게재 / 거절·취소 시 청구 없이 보류 해제. **Stripe 보류는 약 7일 뒤 자동 해제 → 7일 안에 승인해야 함.**
- **상위노출**: 결제 방법 선택(포인트=바로 적용 / 카드=결제 창에서 확인 즉시 청구·적용). 코드: `app/Traits/HasPromotions.php`(`handlePromote`, `handlePromoteCardConfirm`), `resources/js/components/PromotionSection.vue`(선택 UI), 공용 창 `usePromoCheckout.js` + `PromoCheckoutModal.vue`(App.vue 설치).
- **인보이스**: 웹 보기·인쇄 = US Letter 정식 양식(`resources/views/invoices/letter.blade.php`), 메일 = `emails/invoice.blade.php`. 받는 사람·결제 수단(카드 종류·끝 4자리)은 발행 시점 스냅샷(`invoices.billing_details`, `payments.card_brand/last4`).
- **마이페이지**(`UserDashboard.vue`) 위쪽 3칸: 내 포인트 / 내 Entry / **내 카드 결제**(이번 달·누적·승인 대기, `GET /api/payments/history` 의 `summary`).
- **관리자**: 광고 센터·광고 목록은 카드 광고 $ / 옛 포인트 광고 P 구분 표시, 매출 현황에 배너·텍스트·상위노출 포함.

### 3-3. Kay 확인이 필요한 것 (아직 안 함)
1. **Stripe 가 테스트 모드인지 실결제인지.** 실결제면 소액으로 광고 신청 → 관리자 승인(청구)·거절(보류 해제)을 한 번씩 확인.
2. 광고 **달러 가격이 적당한지**(위 환산값).
3. 인보이스에 찍히는 **회사 정보**(사이트 설정 › 회사 정보: 회사명·대표·사업자번호·주소·전화·이메일)가 비어 있지 않은지.
4. 눈으로 확인(PC·휴대폰): 광고 신청(배너·텍스트) 카드 창 · 상위노출 포인트/카드 선택(장터·구인·부동산·업소) · 마이페이지 3칸 · 관리자 광고 센터/가격 센터 · 인보이스 보기·인쇄 · 북마크 목록 · 채팅방 입장 속도.

## 4. 남은 할 일 (순서)
1. **3단계 — 공동구매 구조 변경** (Kay 결정: 이벤트를 요청하는 사람(주최자)에게만 어코가 **오픈 수수료**를 받고, 참가자는 결제 없음, 이벤트 종료 후 **참가자 명단·정보를 주최자에게 제공**). 현재 코드는 참가자가 **포인트로 참여 결제**(`GroupBuyController` 332·349줄)하고 취소/반려 시 포인트 환불. → 참가 포인트 결제 제거, 주최자 수수료 카드 결제(Stripe, `DirectPayments` 패턴 + 새 결제 종류), 참가 시 **정보 제공 동의** 체크 + 저장, 종료 후 주최자용 명단 화면/내보내기, 기존 참가 데이터·환불 경로 보존. 시작 전 현재 흐름 조사 → 계획을 Kay 에게 보여 주고 확인.
2. **4단계 — 월간 리더보드** (레시피·답글왕·앤서왕·글 많이 쓴 사람 등): 월 집계, **1~5등 차등 Entry**(관리자 확정 후 지급 — 개수는 설정으로), 공개 화면 + 관리자 화면. (레시피 인기 보상은 포인트 유지로 결정됨.)
3. **5단계 — 포인트 → 기프트카드 교환**: Amazon·Starbucks 두 종류만, **1,000P = $1, 수수료 10%(최소 $1), 최소 $10, 월 $20 · 연 $500**(5번 자리에 적어 주신 값을 한도로 해석 — 틀리면 Kay 확인), 관리자 승인제, 신청 시 포인트 보류·거절/취소 시 환불, `point_redemptions` 표, 약관·포인트 정책 개정, 비상 스위치 "교환 멈추기". 가입 30일·이메일+전화 인증·최근 제재 없음 등 자격. 연 $600 이상 지급 시 세금 서류(1099) 문제 → 세무사 확인 권고.
4. **이벤트 완료 인증 외 Entry 확대**(필요 시): 쇼핑 리뷰는 이미 자동(설정 `review_bonus`, `review_daily_max`).
5. 글쓰기 직후 화면에서 상위노출 카드 결제는 **글 등록 후 결제 창**이 뜨는 방식(취소하면 글만 등록). 불편하면 등록 전 결제로 바꾸는 것 검토.
6. 부분 완료 항목(할 일 목록에 메모): 환불 정책(**Kay 보류**), 일괄 등급 변경, 경품 매분 작업 3개 합치기, 새 기능 킬 스위치 점검·배포 후 화면 왕복 체크리스트.
7. **더미 계정(#57/#75)은 삭제 금지** — Kay 가 "아직 사용할 것"이라고 결정(2,670명 `@somekorean.local` + test.com 200명 + example 15명).
8. test@test.com 은 그대로(로그인 편의), 오픈 후 `admin@awesomekorean.com` 이 메인 최고관리자로 대체 예정(그때 정리). Resend SPF/DKIM(#81)·소셜 가입·2FA·애드센스는 보류.
9. 서버 업그레이드·백업·재부팅(#3,#4,#77~#79)은 Kay 가 직접.

## 5. 파일 지도 (이번 작업 핵심)
- 돈/포인트/Entry: `app/Support/AdPayments.php`, `DirectPayments.php`, `StripeGateway.php`, `EntryService.php`(awardReview·awardEventProof), `OpenEvent.php`, `PointRules.php`, `Invoicing.php`
- 광고: `app/Http/Controllers/API/BannerController.php`(store·textApply·confirmPayment·cancel), `AdminController.php`(approveBanner·rejectBanner·deleteBanner·refundPayment), `resources/js/pages/ads/AdApply.vue`·`AdApplyText.vue`, `pages/admin/AdminAdCenter.vue`·`Banners.vue`·`AdminPricingCenter.vue`
- 상위노출: `app/Traits/HasPromotions.php`, 각 컨트롤러 `promote`/`promoteConfirm`, `routes/api.php`(`/{resource}/{id}/promote`, `/promote/confirm`)
- 마이그레이션(오늘): `2026_10_11_2x0000`~`34`·`35…` (할 일 반영·오픈 이벤트·즐겨찾기·Chloe·채팅 색인·정책 1단계·이벤트 Entry·광고 카드 칸)
- 백업: `qa/backups/*_before_card`, `sitesettings_split_20261011/`, `members_pc_20261011/`, `HasPromotions.php.before_card`

## 6. 서버 현황 메모
- supervisor: `reverb`, `somekorean-worker`, `awesomekorean-collect-worker`(수집 전용, `.env ADMIN_COLLECT_QUEUE=collect`). 되돌리기 명령은 `deploy/setup_collect_worker.sh` 끝 출력 참고.
- 서버 읽기로 확인된 것: Chloe=super_admin, open_event enabled(scheduled), admin_favorites 표·chat_messages 색인 존재.

---

## 7. 추가 업데이트 (UTC 12:00 / 애틀랜타 오전 8:00) — 인보이스 정식 양식 전면 적용 · 지급·보상 센터 · 잔액 표시 (코드 `498bcafa` 배포 success)

### 7-1. 인보이스 (Kay 지적: "이런 간단한 게 아니고 내역·카드 종류·끝 4자리까지 다 나와야")
- 원인: 마이페이지 결제 내역의 예전 팝업(`printInvoice`, "Invoice #26 … 포인트 0P")이 인보이스 시스템 이전 결제·결제 대기 건에 계속 쓰이고 있었음. → **제거하고 모든 결제를 정식 인보이스(US Letter)로 열게 함.**
- 회원: 결제 탭 "인보이스" → 저장된 인보이스가 있으면 `GET /api/invoices/{번호}`, 없으면(옛 결제·결제 대기) `GET /api/payments/{id}/invoice`(내 결제만). 관리자: 결제/오더 상세 "정식 인보이스 (인쇄·PDF)" → `GET /api/admin/payments/{id}/invoice`. 컨트롤러 `PaymentInvoiceController`.
- 저장 안 된 건은 `Invoicing::previewForPayment()` 로 즉석 생성(저장 안 함, 번호 `PAY-000026` = "주문 참조"). 결제 대기는 "UNPAID · 결제 대기" 도장 + 남은 금액 표시.
- **카드 종류·끝 4자리**: 청구 시(`DirectPayments::capture` → `StripeGateway::captureDetailed`)와 포인트 구매 확정 시 `payments.card_brand/card_last4` 저장, 옛 결제는 인보이스를 열 때 `Invoicing::ensureCardDetails()` 가 Stripe 에서 읽어 채움(실패해도 인보이스는 열림). 양식에 "Card brand / Card number •••• •••• •••• 1234" 표시.
- ⚠️ 서버에 **결제 완료된 카드 결제가 아직 한 건도 없음** → 카드 종류·끝 4자리 표시는 첫 실제 결제로 확인 필요(Stripe 테스트 카드 4242… 로 시험 가능). #26 은 결제 대기라 "결제 전"으로 보임(정상).

### 7-2. 지급·보상 센터 `/admin/grants` (돈 그룹 메뉴, 휴대폰 포함)
- 탭 ① **직접 지급**: 회원 검색 → 보유 포인트·Entry·누적 구매 표시 → 포인트 지급/차감(`POST /admin/users/{id}/points`, 관리자) · Entry 지급/차감(`POST /admin/entries/adjust`, 최고관리자) · **실제 받은 구매 금액 기록**(`POST /admin/grants/manual-payment`, 최고관리자 + 비밀번호 재확인 + 감사 로그: 종류·금액·구매 내용·Zelle/현금/수표/기타·메모 → `payments`(status captured, `pay_method='manual'`) 생성 → 매출 반영 + 인보이스 자동 발행, 인보이스에 "Manual payment" 표시). 카드와 별개로 새 광고·상위노출을 대신 열어 주지는 않음.
- 탭 ② **보상 승인**: 예전 "신고·문의 › 보상 승인"(`AdminRewardRequests`)을 그대로 끼워 넣음. 옛 주소 `/admin/rewards` 는 `/admin/grants?section=rewards` 로 자동 이동(알림·오늘 할 일 딥링크 유지), 메뉴에서는 숨김.
- 탭 ③ **지급 기록**: 최근 포인트·Entry·직접 기록 결제 60건(`GET /admin/grants/recent`).
- 회원관리(PC) 상세 헤더에 "지급·보상 센터 ›" 바로가기(`/admin/grants?user=번호`). 휴대폰 회원 화면에는 아직 링크 없음.
- 마이그레이션 `2026_10_11_350000_payments_manual_columns` (payments.pay_method, pay_note).

### 7-3. 결제 화면에 내 잔액 (`MyBalanceStrip.vue`)
- 상위노출(글쓰기·마이페이지·BoostButton)과 광고 신청(배너·텍스트)에서 **내 포인트 · 내 Entry · 카드 결제(이번 달·누적·승인 대기)** 한 줄 표시. 포인트 결제를 고르면 "N P 모자라요"/"결제 후 N P 남아요" 안내. 카드 결제 선택 시엔 카드 쪽을 강조.

### 7-4. 다음 (변동 없음)
3단계 공동구매 구조 변경 → 4단계 월간 리더보드 → 5단계 포인트→기프트카드. (상세는 위 4번.) 시작 전에 Kay 에게 "넘어가" 확인.


---

## 8. 추가 업데이트 (UTC 15:20 / 애틀랜타 오전 11:20) — 카드 최소 결제 · 영어 인보이스 · 검색 복구 · 한 방 인수인계 체계

### 8-1. 이번에 고친 것 (코드 `73f56ae3` 배포 success)
- **긴급: 회원 검색 전체가 오류였음**(내가 넣은 `users.ip_address` 검색 칸이 실제로는 없는 칸). 고침 — 이름·이메일·닉네임·번호·전화로 검색(IP 검색은 제거). 서버에서 확인(chloe→1, 2905→1).
- **카드 결제 최소 금액** = 관리자 › 사이트 설정 › 결제/알림 › "최소 결제금액"(`site_settings.payment_config.min_amount`, 현재 5 → $5.00, 허용 0.50~1000). `app/Support/CardLimits.php`. 서버: `DirectPayments::begin` 이 모든 카드 결제에서 검사, 광고는 입찰이 최소 미만이면 거절·할인으로 줄어도 최소로 청구, 상위노출 카드는 모자라면 "N일 이상으로 하거나 포인트로" 안내(422). 화면: 상위노출(글쓰기 3곳·마이페이지·BoostButton)과 광고 신청에 "카드 결제는 최소 $5.00 이상" 안내 + 등록 전 확인. 공개 API `GET /api/payments/card-limits`.
- **502 오류 원인**: 배포가 PHP-FPM 을 "재시작"하면서 그 순간 진행 중이던 요청이 끊긴 것(코드 오류 아님). `deploy.sh` 를 `systemctl reload`(부드러운 재로드)로 바꿈 — **다음 배포부터 적용**(이번 배포 로그는 옛 스크립트로 실행됨). 서버가 작아 PHP-FPM 최대 5개 한도에 자주 닿음(Kay 가 서버 업그레이드 예정).
- **인보이스 언어**: `?lang=en|ko` — 보는 사람의 사이트 언어(우측 상단 EN/KO)를 따라감. 영어면 영어만(한글 0자 확인, 회원이 직접 쓴 제목은 그대로), 한국어면 영어+한글 병기. 영어 품목 이름은 `Invoicing::enDetail`.
- **결제 설정 탭**(사이트 설정 › 결제/알림): "미연결"이 항상 뜨던 가짜 값과 가짜 숫자($2,340, 92.5%)를 제거 — 연결 상태·모드는 저장된 키 기준, "이번 달 결제 요약"은 실제 매출(`/api/admin/revenue/summary`). 최소 결제금액은 0.5~1000 검사.
- **지급·보상 센터**: 글자를 칠 때마다 실시간 검색(찾는 중…/없어요/오류 문구), 결과를 촘촘한 목록으로, **지급 기록은 한 줄씩 표 + 눌렀을 때 상세 패널**(인보이스·"이 회원으로 지급하기").

### 8-2. 한 방 인수인계 체계 (새로 만듦)
| 파일 | 역할 |
|---|---|
| `.claude/commands/continue.md` → `/continue` | 새 계정이 한 줄로 시작: 문서 읽기 → 점검 → 대기열의 다음 작업 → 배포 → 검증 → 문서 갱신 → 보고 |
| `.claude/commands/ship.md` → `/ship "메시지" 파일…` | 코드만 한 번에 배포 |
| `qa/TASKS_QUEUE.md` | 할 일 대기열(T0 점검, T1 공동구매, T2 리더보드, T3 기프트카드, T4 카드 실결제 시험, T5 작은 정리) — 각 작업에 상태·현재 코드 위치·순서·결정 필요·완료 확인 |
| `scripts/ship.sh` | 깨진 이름공간 검사 → vite 빌드 → 지정 파일만 커밋 → fetch/rebase → push → **GitHub Actions 배포 success 대기** → 스모크 테스트. 실패하면 로그 요약 출력 |
| `scripts/smoke.sh` | 배포 뒤 로그인 없이 확인 가능한 10여 가지(화면·공개 API·로그인 필요 경로) |
| `scripts/server_check.sh` | 서버 읽기 전용 점검(마지막 배포·대기 마이그레이션·supervisor·메모리·오늘 오류·결제 요약). SSH 막히면 건너뜀 |
| `qa/CONTINUE_PROMPT.md` | 슬래시 명령이 안 보일 때 붙여넣을 글 + 처음 필요한 도구 |

### 8-3. 지금 대기열 상태
T0 항상 · T1 공동구매 = needs_kay(계획서 `qa/groupbuy_plan.md` 작성 완료, 결정 대기) · T2 리더보드 = needs_kay · T3 기프트카드 = needs_kay · T4 실결제 시험 = needs_kay · T5 작은 정리 = **done**(아래 9번).

### 8-4. Kay 확인 필요 (변동)
1. Stripe 테스트/실결제 모드와 광고 달러 가격(이전 7-3).
2. 새 인보이스 영어/한국어 모양 확인(EN 토글 후 결제 탭 › 인보이스).
3. 지급·보상 센터 검색·지급 기록 목록이 보기 편한지.
4. **서버는 결제 완료된 카드 결제가 아직 0건** → 카드 종류·끝 4자리 표시는 첫 실제 결제로 확인(T4).

---

## 9. 추가 업데이트 (UTC 16:00 / 애틀랜타 낮 12:00) — 작은 정리(T5) 4건 완료 (코드 `0059cd46` 배포 success)

### 9-1. 한 일
1. **휴대폰 회원 상세에 "지급·보상 센터로 이동 ›"** (`AdminMembersMobile.vue`, 관리자 이상에게만) — `57ccd182`.
2. **관리자 광고 목록 페이지 넘기기** (`Banners.vue` + `AdminController::bannerList`) — `23b3a88e`. 휴대폰·PC 모두 50건씩 "더 보기", 90일 이전 끝난 광고는 "90일 이전 지난 광고 보기"(`GET /api/admin/banners?older=1&page=N`). 기본 응답(최근 90일+진행 중, 1000건)은 그대로라 슬롯 화면은 영향 없음.
3. **경품 매분 작업 3개 → `sweepstakes:tick` 하나** (`routes/console.php`, `app/Console/Commands/SweepstakesTick.php`) — `5612c1a9`. 서버 매분 프로세스 3개가 1개로. 겹침 방지 잠금 10분. 옛 3개 명령은 그대로 남음. 서버 `schedule:list` 확인, 배포 후 오류 없음.
4. **회원 일괄 등급 변경** (`AdminController::bulkUsers` `action=role`, `Members.vue`) — `0059cd46`. 최고관리자 전용, 일반↔기업만(운영진 승급은 한 명씩), 본인·운영진·이미 같은 등급은 건너뜀, 바뀐 회원마다 감사 로그. 서버에서 롤백되는 트랜잭션으로 6가지 시나리오 확인.
5. **(Kay 지적) 휴대폰 인보이스 화면** — `e3c741d9`. 위쪽 "인쇄 / PDF 저장" 버튼이 상태바에 가려 잘리고 닫기 버튼이 없던 문제 → `resources/views/invoices/letter.blade.php` 의 위쪽 막대를 **화면 위에 고정**(sticky, `viewport-fit=cover` + 상태바 안전 영역 반영), 왼쪽에 **"← 닫기"**(새 탭이면 탭 닫기, 안 닫히면 뒤로, 그래도 없으면 홈) 추가, 휴대폰에서 맨 아래 여백 확보(하단 주소 막대에 안 가림). 인쇄 때는 막대 숨김, 메일용 양식은 그대로. PC·휴대폰 크기로 확인, 서버에서 한국어/영어 양식 생성 확인(회원 마이페이지·관리자 결제/지급 센터가 모두 이 양식 사용).
6. **(Kay 지적) 카드 최소 $5 미만에서 막다른 길** — `385d227c`. 원인: 상세 페이지 상위노출 버튼(`BoostButton.vue`)이 1·3·7일뿐이라 하루 $0.50 기준 7일이 $3.50 → 카드로는 어떤 기간도 $5 를 못 넘겼음(안내 문구만 있고 해결 수단 없음). 고침: 기간에 **14·30일 추가**, 최소 미만이면 빨간 상자에 **"N일로 늘리기 · $5.00"**(카드 최소를 딱 넘기는 날 수, 예 10일)과 **"포인트로 결제하기"** 버튼. 글쓰기 3곳·마이페이지(`PromotionSection.vue`)도 같은 두 버튼, 광고 신청(`AdApply.vue`·`AdApplyText.vue`)에는 **"$5.00 으로 올리기"** 버튼. 서버는 그대로(결제액은 항상 하루 가격 × 일수, 최소 미만이면 거절 — "기간 그대로 $5 청구" 방식은 사용자에게 손해라 넣지 않음. 원하면 말씀하세요).
- 백업: `qa/backups/cardmin_options_20261011/`, `qa/backups/t5_20261011/` (AdminMembersMobile.vue · Banners.vue · Members.vue · AdminController.php · AdminController_before_bulkrole.php · console.php), `qa/backups/invoice_mobile_20261011/letter.blade.php`.

### 9-2. Kay 가 눈으로 확인할 것
- 휴대폰: 관리자 › 회원 › 한 명 열기 → "지급·보상 센터로 이동 ›" 가 보이고 눌러서 그 회원으로 열리는지.
- PC: 관리자 › 회원관리에서 두세 명 체크 → 위쪽 줄에 "일반회원으로 / 기업회원으로"(최고관리자 로그인일 때) → 확인창 → 완료 알림. 감사 로그 화면에 "일반회원 → 기업회원 (일괄 등급 변경)" 이 한 줄씩 남는지. (되돌릴 때는 반대 버튼.) 더미 계정 말고 시험용 계정으로 확인하세요.
- 관리자 › 광고 목록: 지금은 광고가 0건이라 "광고 신청 없음" + "90일 이전 지난 광고 보기" 버튼만 보임(눌러도 없음). 광고가 쌓이면 50건씩 더 보기가 나옴.

### 9-3. 다음 (변동 없음 — 전부 Kay 결정 대기)
- **T1 공동구매** (`qa/groupbuy_plan.md` 읽고 7가지 결정): ① 오픈 수수료 금액(제안 $10) ② 등록 시 보류→승인 때 청구(제안) vs 등록 즉시 청구 ③ 참가자 명단에 이메일·전화 포함 여부.
- **T2 리더보드**: ① 카테고리 ② 1~5등 Entry 개수 ③ 최소 글자 수 ④ 더미 제외 OK ⑤ 지급은 관리자 확정 후(제안) vs 자동.
- **T3 기프트카드 교환**: 연 $500 한도 해석이 맞는지 + 세무사 확인.
- **T4 실제 카드 결제 시험**: Stripe 테스트/실결제 모드 확인.
- ready 인 작업은 없음(T5 완료). Kay 의 답이 오면 `qa/TASKS_QUEUE.md` 해당 작업을 `ready` 로 바꾸고 시작.
