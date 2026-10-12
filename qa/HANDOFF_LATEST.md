# ⭐ 최신 인수인계 (다른 계정/세션에서 이어 받을 때 가장 먼저 읽기)

작성: 2026-10-12 UTC 17:10 (애틀랜타 낮 1:10) · 최신 코드 배포 `126d9177`→`f0c8d843`(썸네일 이름 빼기) · **Stripe 는 테스트 모드** · 최신 변경은 맨 아래 **15번**(MONEY 운영팀 글 진행표·내돈내산 카테고리)

## 0. 지금 바로 알아야 할 것 (2026-10-11 밤 기준)
1. **메뉴 개편 1단계 + MONEY 허브 화면은 사이트에 켜져 있음** (Kay 가 2026-10-12 UTC 00:14 스위치 켜고 MONEY 숨김 해제). 끄려면 관리자 › 사이트 설정 › 메뉴 묶음.
2. **MONEY 허브 완료**(화면 6개 + 관리자 MONEY 관리) + **카페 대문 꾸미기(13-2)**. 다음은 12-2번 Kay 결정(가이드 본문·정식 이름·다음 후보) — 대기열 T7·T8(needs_kay). 결정 전에는 T1~T4 처럼 질문만 모아서.
   - **헤더·목록 제목 모양은 app.css 공용 클래스(13-1)** — `/info`(Blade)도 같은 클래스. 화면 바꿀 때 `/info` 같이 확인할 것(Kay 가 여러 번 지적).
3. 서버 확인은 `bash scripts/server_check.sh`, 운영 DB 시험은 **롤백 트랜잭션**으로만. Playwright MCP 브라우저는 **Kay 의 실제 Chrome** 이라 조작 금지(보기만, 가능하면 앱 내장 브라우저 `mcp__Claude_Browser__*` 사용).
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
7. **(Kay 지적) 상위노출 카드 결제 "처리 중..." 멈춤** — `dc0f203d`. 서버는 정상(요청이 오자마자 200, 서버 오류 없음)이었고 폰이 요청을 붙잡았다가 한꺼번에 보낸 것. 그래도 빠져나갈 길을 만듦: `BoostButton.vue` 카드 결제 시작에 30초 제한(안내 문구 "카드에는 청구되지 않았어요"), 대기 중에도 "취소" 가능, 창을 닫으면 이어가지 않음(포인트 결제는 서버가 바로 차감하므로 제한 없음).
8. **(Kay 지적) 폰에서 버튼·칸이 잘림 / 상위노출 줄 모서리 / 로고 안 보임** — `306a843a`. 원인과 수정: (a) 로그인 헤더(로고 144px + 큰 글씨 + 알림 + 프로필 58px + EN)가 393px 폰보다 넓어 페이지가 옆으로 밀려 결제 창이 좌우로 잘림 → `NavBar.vue` 로고 `clamp(92px,26vw,144px)`, 프로필 ≤430px 에서 46px(360px 폭에서도 맞음을 실제 화면에서 계산 확인). (b) `StripeCardForm.vue` 카드 입력 글자 15px → 16px(아이폰이 16px 미만 입력에서 화면을 확대·이동). (c) 구인 등록 만료일 칸이 카드 밖으로 넘침 → `app.css` 에 휴대폰 폭 날짜·시간 입력 규칙(`min-width:0; max-width:100%`, `.input-soft` 는 appearance none + 높이 44px)과 날짜 입력 글자 16px. (d) `JobList.vue` 상위노출 줄을 카드(둥근 18px) 안쪽 8px 로 띄우고 모서리 10px 로 같은 중심 곡선 → 테두리가 안 잘림. (e) 구인 목록에 올린 회사 로고 표시(기존엔 상세에만 있었음; 서버 저장은 정상 — `/storage/job_logos/...`) + **Kay 지시로 로고 있는 줄은 재배치**(`de77566c`): 로고(56px) 왼쪽 · 오른쪽 위에 제목(2줄까지) · 그 아래 급여 · 뱃지(전국구·직종) · 메타 순서, 하트는 오른쪽 위. 로고 없는 줄은 예전 배치(급여 오른쪽) 그대로. CSS `order-*` 로 한 템플릿에서 순서만 바꿈(`JobList.vue`). 폰 폭 393px 실제 화면으로 확인. 아이폰 실기기는 확인 불가 → Kay 가 확인. 다른 목록(장터·부동산·업소)에도 같은 방식이 필요하면 말씀하세요.
9. **(Kay 지적) 왼쪽 메뉴와 하단 메뉴의 순서·이름이 달랐음** — `694eb15e`. 원인: 왼쪽(햄버거)·PC 위쪽 메뉴는 관리자 메뉴 설정(`site.menuConfig`)의 순서·이름을 쓰는데, 하단 탭(`stores/navFavorites.js` → `BottomNav.vue`)은 자기 하드코딩 목록(이름 "쇼핑")과 "별 누른 순서"를 썼음. 고침: 하단 탭도 같은 메뉴 설정에서 순서·이름·경로를 가져오고(끈 메뉴·관리자 전용·로그인 필요 메뉴도 같은 규칙으로 숨김), 별(★)을 누른 것만 표시, **MY 는 맨 끝**(메뉴 설정에 없는 고정 메뉴), "정보"는 서버 렌더 페이지라 일반 링크로 이동. 폰 폭 실제 화면에서 모든 별을 켠 상태로 서랍과 하단 탭의 순서·이름이 같은지 확인(로그아웃 상태 기준). 이제 관리자 › 메뉴 설정에서 이름·순서를 바꾸면 하단 메뉴에도 같이 바뀜(캐시 때문에 한 번 새로고침 필요할 수 있음). 로그인 안 한 방문자는 "로그인 필요" 메뉴(채팅·친구)가 하단에서도 숨겨짐(서랍과 동일).
10. **(Kay 요청) PC 상단이 답답함 → 높이 키움(MissyUSA 보다는 낮게)** — `a76c84f4`. PC(768px 이상)만: 헤더 106 → 140px(위 여백 14px, 1줄 76px, 메뉴 줄 48px), 로고 144 → 172px, 검색칸 높이·글씨 키움, 메뉴 글씨 13 → 14px. 휴대폰은 그대로. 헤더 실제 높이를 `--hdr-h` 변수로 공유해, `sticky top-20` 사이드바(목록·상세 왼쪽 카테고리 등)와 태블릿 상세 머리글이 헤더 밑에 가려지지 않게 `app.css` 에서 위치 보정. 되돌리려면 `qa/backups/header_height_20261011/`. Kay 가 보고 "더/덜" 조정 예정.
- 백업: `qa/backups/header_height_20261011/`, `qa/backups/nav_sync_20261011/`(navFavorites·BottomNav), `qa/backups/mobile_fit_20261011/`(NavBar·JobList·app.css), `qa/backups/cardmin_options_20261011/`, `qa/backups/t5_20261011/` (AdminMembersMobile.vue · Banners.vue · Members.vue · AdminController.php · AdminController_before_bulkrole.php · console.php), `qa/backups/invoice_mobile_20261011/letter.blade.php`.

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

---

## 11. 추가 업데이트 (2026-10-11 UTC 23:45 / 애틀랜타 저녁 7:45) — PC 상단·글씨, 메뉴 개편 1단계

### 11-1. 화면 다듬기 (모두 배포 success)
- **PC 상단 높이**(`a76c84f4`): 헤더 106→140px(768px 이상만), 로고 172px, 검색칸·메뉴 줄 키움. 헤더 실제 높이를 CSS 변수 `--hdr-h`(NavBar 가 ResizeObserver 로 갱신)로 공유 → `app.css` 가 `sticky top-20` 사이드바·태블릿 상세 머리글 위치를 보정. 백업 `qa/backups/header_height_20261011/`.
- **PC 글씨 한 단계 크게**(`097a597f`, Kay 승인): 768px 이상에서 12→13.5, 13→14.5, 14→15, 16→17.5, 18→19, 20→21.5px. 상단 메뉴(`nav.ev-header`)·관리자 화면(`.admin-shell`, `AdminLayout.vue` 루트에 붙임) 제외, "큰 글씨로 보기"를 켠 경우엔 그 설정 우선. `app.css` 맨 끝. 비교 캡처 `qa/design/font_before_community.png`·`font_after_community.png`. 백업 `qa/backups/pc_font_20261012/`.
- **구인 로고**: 목록 PC 100px(`070902a1`), 상세 PC 120px·휴대폰 88px(`8ac331e8`).

### 11-2. 메뉴 개편 1단계 (계획서 `qa/menu_restructure_plan.md`, 샘플 `.claude/mockup/menu_grouping_sample.html`)
Kay 확정 묶음: 홈 · 커뮤니티(게시판·Q&A·채팅·동호회) · 구인구직 · 부동산 · 사고팔기(중고장터·공동구매) · 업소·광고(업소록·NEW) · 생활정보(정보·뉴스·레시피·이벤트) · 즐길거리(숏츠·음악듣기) · 내돈내산 리뷰 · MONEY(허브, **지금은 숨김** — 2단계). 친구는 MY 로.
| 커밋 | 내용 |
|---|---|
| `7b3a9899` | 서버: `app/Support/MenuGroups.php`(기본값·검사 `sanitize`·서버 페이지용 `publicGroups`), 마이그레이션 `2026_10_12_100000_seed_menu_groups`(없을 때만 넣음, 스위치 `menu_groups_enabled=0`), `GET/POST /api/admin/settings/menu-groups`(최고관리자 — AdminTier `settings/*`), 공개 설정에 `menu_groups`·`menu_groups_enabled` 포함 |
| `06ba542e` | 관리자 › 사이트 설정 › **메뉴 묶음** 탭(`components/admin/siteSettings/SiteMenuGroupsTab.vue`): 끌어서·▲▼로 순서/묶음 이동, 묶음 이름·영어 이름·아이콘·종류(혼자/묶음/허브)·숨김, 묶음에 없는 메뉴 넣기, 미리보기, 켜기 스위치, 기본 묶음으로. `stores/site.js` 에 `menuGroups`(켜졌을 때만 값)·`updateMenuGroups`(localStorage 캐시 `ak_menu_groups_cache`). `NavBar.vue`: 켜지면 PC 상단 묶음 + 마우스 올리면 하위 메뉴, 휴대폰 ☰ 묶음 제목/하위 메뉴 + MY(마이페이지·쪽지·친구). `navFavorites.js`: 하단 탭 순서 = 묶음 순서 |
| `620f184e` | `components/GroupTitle.vue` — 목록 페이지 제목 자리(PC·휴대폰): 켜져 있고 이 메뉴가 2개 이상 보이는 묶음이면 `[묶음 이름][하위 탭]`, 아니면 원래 제목. 적용: BoardList·QaList·ClubList·ChatRooms·MarketList·GroupBuyHome·BusinessList·NewBoard·NewsList·RecipeList·EventList·MusicHome (숏츠는 전체화면이라 제외) |
| `a06dcf7c` | `/info`(서버 Blade) 상단·☰·제목 줄도 같은 묶음 (`resources/views/info/_header.blade.php`, `index.blade.php`) |
| `80882312` | 묶음 안에서만 쓰는 이름 `item_labels`(커뮤니티 묶음 안 '커뮤니티' → '게시판'), 마이그레이션 `2026_10_12_110000`, 편집기에서 수정 가능 |
- 검증: 서버 롤백 트랜잭션으로 저장·검사 규칙·`/info` 켠 상태 렌더 확인, 앱 내장 브라우저에서 **그 브라우저에만** 켜서 PC 1280·휴대폰 393 확인(가로 넘침 없음). 로그아웃 방문자에겐 관리자 전용/로그인 필요 메뉴가 빠지므로 하위 탭이 1개뿐인 묶음은 탭 없이 원래 제목(정상).
- 백업: `qa/backups/menu_groups_20261012/`, `qa/backups/group_title_20261012/`.
- **되돌리기**: 관리자 화면에서 스위치 끄기(즉시). 코드까지 되돌리려면 위 커밋들을 `git revert`(force-push 금지).

### 11-3. 다음 (순서)
1. Kay: 메뉴 묶음 스위치 켜고 PC·휴대폰 확인 → 고칠 점 받기.
2. **T6 2단계 MONEY 허브 계획서** 작성 → Kay 확인 → 구현. Kay 설명(확정): MONEY 첫 화면 = ① 분야별 **정보 공유 카페 12개**(돈 벌기·돈 아끼기·돈 불리기·부동산·주택·보험·금융 보호·세금·정부 혜택·신용카드·포인트·주식·자녀·교육비·은퇴·노후·돈 벌었어요 인증 릴레이·돈 이야기) — **가입 없이** 누구나 보고 답글, 글쓰기는 로그인, 나중에 카페 운영자 지정 ② **머니 가이드 = 설명 페이지**(운영자가 쓰는 글) ③ "분야별 카페 둘러보기" → **카페 전체 안내 페이지**(카페마다 대문 설명이 쭉), 각 카페를 누르면 맨 위 **대문**(다루는 것·처음 읽을 가이드·인기 정보·규칙) + 아래 정보/질문/소통 게시판. **머니 게시판은 없음**, 내돈내산 리뷰는 MONEY 와 별개(상단 단독), 동호회는 커뮤니티. 정식 이름 미정(관리자에서 변경).
3. 그 외 대기열: T1 공동구매·T2 리더보드·T3 기프트카드·T4 카드 시험 — 모두 Kay 결정 대기(`qa/TASKS_QUEUE.md`).

---

## 12. MONEY 허브 (T6 2단계) 진행 기록 — 계획서 `qa/money_hub_plan.md` (Kay "시작해" 2026-10-12)
- [x] 1) `aac0dea7` 표 3개(`money_cafes`·`money_posts`·`money_guides`) + 카페 12개·가이드 5개(비공개 초안), 모델, `MoneyController`(공개: `/api/money/hub`·`/cafes`·`/cafes/{slug}`·`/cafes/{slug}/posts`·`/posts/{id}`·`/guides`·`/guides/{slug}` / 로그인: POST·PUT·DELETE `/api/money/posts`), 댓글 종류 `money_post`, 글쓰기 포인트(`WritePoints::COUNTED_TYPES`)
- [x] 2) `ad4c2649` 화면: `/money` 허브 · `/money/cafes` 카페 안내 · `/money/cafe/:slug` 대문+게시판 · `/money/post/:id` · `/money/write` · `/money/guide/:slug` (샘플 `.claude/mockup/menu_grouping_sample.html` 의 MONEY 화면 모양, 초록 계열, 제목 자리 `MONEY › …`)
- [x] 3) `e690c895` 관리자 › 콘텐츠 › **MONEY** (`/admin/money`, `AdminMoney.vue` + `AdminMoneyController`: 카페 저장·새 카페 / 가이드 쓰기·공개·새 가이드 / 카페 글 숨김·복구, 쉬운 글쓰기 형식 ## 제목·- 목록·빈 줄 + 미리보기, 지우는 기능 없음) — 원래 계획: 관리자 MONEY 관리(카페 이름·아이콘·한 줄·대문·순서·켜기 / 가이드 쓰기·공개 / 글 숨김·복구) + `adminMenu.js` + AdminTier
- [x] 4) **Kay 가 직접** 메뉴 묶음 스위치 켜고 MONEY 숨김 해제 (2026-10-12 UTC 00:14) → 지금 모든 방문자에게 묶음 메뉴 + MONEY 노출 중
- (고침) `hub()` 응답에서 intro 제외. 화면 확인: PC 1280·휴대폰 393 넘침 없음.

### 12-2. MONEY 다음 (Kay 확인·결정)
1. 관리자 › MONEY 관리에서 **가이드 5개 본문 쓰고 공개**(지금 비공개 초안이라 첫 화면 가이드 칸이 안 보임) · 카페 대문 문구 다듬기.
2. MONEY 정식 이름(관리자 › 사이트 설정 › 메뉴 묶음에서 바꾸면 MONEY 화면 제목도 같이 바뀜).
3. 다음 후보(Kay 결정 후): 카페 운영자 지정 화면, 머니 카페 글 신고·인기글, 홈 화면에 MONEY 카드, 머니 카페 글 검색을 사이트 검색에 포함.

---

## 13. 추가 업데이트 (2026-10-12 UTC 02:40 / 애틀랜타 10/11 밤 10:40) — 화면 모양 통일 · 카페 대문 꾸미기

### 13-1. 화면 모양 통일 (Kay: "인포 페이지만 매번 빠진다", "채팅·필터·글쓰기 깨짐", "서브메뉴 칩 너무 큼")
- `4d52abb8` **헤더·묶음 제목 공용 클래스** (`resources/css/app.css` 의 `.site-hdr-*`, `.grp-*`): `NavBar.vue` 와 `/info` 의 `resources/views/info/_header.blade.php` 가 **같은 클래스**를 씀 → 앞으로 헤더 크기는 app.css 만 고치면 둘 다 바뀜. **Blade 쪽에 숫자 쓰지 말 것.**
  - `/info` 목록(`info/index.blade.php`)·글(`show.blade.php`): 제목은 공용 부분 `info/_title.blade.php`(= Vue `GroupTitle.vue` 와 같은 `.grp-*`), 목록 줄은 커뮤니티(`BoardList.vue`)와 같은 모양(한 카드 + 줄, 사진 오른쪽), 휴대폰 검색·카테고리 추가, 아바타 46/58px + 등급 링, `--hdr-h` 공유.
  - 채팅(`ChatRooms.vue`): 제목 줄을 왼쪽 좁은 칸 밖(전체 폭)으로.
  - MONEY 화면 6개 바깥 폭 `max-w-7xl` 통일(글·글쓰기·가이드는 안쪽 `max-w-4xl` 가운데).
- `444fadc2` 휴대폰 목록 머리: `GroupTitle stack` → `.grp-stack`(display:contents) + `:has()` 로 **[제목 …… 필터·글쓰기] / [하위 탭 전체 폭]** 두 줄. 칩 13px(PC 13.5px). 주의: `.grp-*` 안에서 `text-*` 를 `@apply` 하면 PC 글씨 키우기 규칙까지 딸려와 커짐 → font-size 직접 씀.

### 13-2. MONEY 카페 대문 꾸미기 (Kay 시안 `awesomekorean_12_money_pages.zip` 기준) — 코드 `e01363d9` (+ `2cae12c2` 주제 누를 때 깜빡임·휴대폰 제목 줄)
- 새 표 `money_categories`(카페별 주제 10~12개, 시안 주제 + 한 줄 설명 씨앗), 칸 추가: `money_cafes.headline/theme/art/first_steps`, `money_posts.category_id/is_pinned`, `money_guides.cafe_id` (+ 카페마다 시안의 가이드 3개를 **비공개 초안**으로 36개). 마이그레이션 `2026_10_12_130000_money_cafe_fronts` — 이미 값이 있으면 덮어쓰지 않음, 지우는 것 없음.
- 화면: `/money/cafe/:slug` = 큰 문구+그림(색은 `components/money/moneyThemes.js`) → 주제 카드(누르면 `?topic=` 로 그 주제 글만) → 처음 방문했다면/함께 나누는 방법 → 이 카페 가이드(준비 중 표시)·많이 본 글 → 게시판(주제·정보/질문/소통·최신/조회/답글순·검색, 📌공지 맨 위) → 함께 시작해요 → 소개·이용 규칙(접힘) → 다른 카페. `/money/cafes` = 12개 그림 카드. 글쓰기에 주제 고르기.
- 관리자 › MONEY 관리: 카페에 큰 문구·그림·색·처음 3단계·**주제 목록**(추가·순서·끄기, 지우기 없음) / 가이드에 "어느 카페 대문" / 카페 글에 📌고정.
- API: `PUT /api/admin/money/cafes/{id}/categories`, `POST /api/admin/money/posts/{id}/pin`, 공개 `posts` 에 `category`·`sort` 파라미터.

### 13-2b. 같이 고친 것
- `7e5fce35` **휴대폰 필터 창이 회색 막 아래 깔려 안 눌리던 문제**(MobileFilter 패널 z 101 → 10001; 10/9 음악 수정 때 오버레이만 10000 으로 올라갔던 것) — 필터 쓰는 11개 목록 모두. 커뮤니티·Q&A 필터에 검색어 칸 + PC 머리 줄 검색 추가.
- 주의: App.vue 의 router-view 가 `route.fullPath` 를 key 로 씀 → **같은 화면에서 주소(쿼리)를 바꾸면 화면 전체가 새로 그려짐**. 화면 안 필터는 주소를 바꾸지 말 것.
- 서버 롤백 시험(`test_money2`): 대문 데이터·주제 132개·가이드 36개, 주제 글쓰기·다른 카페 주제 무시·주제 필터·공지 맨 위·주제 저장(끄기)·카페 저장 모두 통과, DB 전후 같음.

### 13-3. GPT 지시서("Money Cafes 통합 게시판 및 AI 콘텐츠 자동화") 검토 — Kay 에게 의견 드림
- 이미 반영: 카페별 통합 게시판, 카테고리(주제) 나누기, 공지 고정, 정렬, 가짜 회원 후기 금지.
- 아직 안 함(필요하면 다음): 좋아요, 글 상태(draft/pending_review/published), origin_type(회원/운영자/AI 보조).
- **AI 자동 글 생성 파이프라인은 보류 권장** — API 키·월 예산·사실 확인 책임이 필요, 하더라도 "AI 초안 → 관리자 승인 후 공개" + 출처 표시 + 한 달 예산 상한으로 단계적. Kay 결정 전 시작 금지 (T8 needs_kay).

## 14. 머니 가이드 41편 전부 공개 (2026-10-12 UTC · Kay "다 만들어서 배포·공개" 지시) — 코드 `83b99967` 배포 success
**배경**: Kay 가 "가이드가 글자·싸구려 이모지뿐" 이라 지적 → 글 대신 **꾸밈 블록**(그림·도표·링크·자료)으로 새로 만듦.
- **블록 형식**: `money_guides.blocks`(JSON) 가 있으면 `GuideBlocks.vue` 로 그림 화면, 없으면 예전처럼 `content`(HTML) 를 보여 줌. 블록 종류: hero·tldr·h·text·stats·steps·compare·bars·proscons·callout·checklist·links·downloads·faq·calc(`mortgage`·`compound`·`cardfee`·`budget`). 모든 글자는 이스케이프 출력, 링크는 https 와 `/downloads/money/…` 만 허용(코드: `resources/js/components/money/GuideBlocks.vue`). 아이콘은 AppIcon 선 아이콘(이모지 아님), 카페별 색 12가지.
- **내용 위치**: `database/data/money_guides/<slug>.json` 41개(공통 5 + 카페 12개 × 3). 마이그레이션 `2026_10_13_100000`(blocks 칸) · `110000`/`120000`(내용 채움 — **blocks 가 비어 있는 가이드에만**, 관리자가 고친 건 덮어쓰지 않음) · `130000`(41편 공개). **새 가이드 JSON 을 추가하면 같은 방식의 마이그레이션을 하나 더 만들어야 서버에 들어감**(이미 실행된 마이그레이션은 다시 안 돎).
- **내려받기 자료** `public/downloads/money/` 16개(CSV 11 · TXT 5, 한글 깨짐 방지 BOM): 집 구입 총비용·PMI 해지 편지·구독 점검·부수입 기록·50/30/20 가계부·카드 비교·카드 혜택 기록·저축 비교·관심 종목·보험 견적 비교·세금 서류·대학 비용·은퇴 생활비·마트 단위가격·질문 템플릿·캡처 가리기·제휴 고지 문구. (`tmp` 스크립트로 만들었으므로 고칠 땐 파일을 직접 편집)
- **관리자**: 관리자 › MONEY 관리 › 머니 가이드 편집 창 아래에 "꾸민 화면 구성(JSON)" 칸(비우면 본문 사용). 개별 비공개는 "공개" 체크 해제.
- **링크 검증**: 사용한 https 링크는 전부 접속 확인(403 은 봇 차단일 뿐 정부 사이트). 확인 못 해서 **뺀 것**: path2college.com, gsfc.org(타임아웃), ftc.gov 심층 주소들(404). 공개 후에도 가끔 클릭 점검 권장.
- **사실 확인 원칙**: 해마다 바뀌는 숫자(IRS 납입 한도·공제액·세액공제 금액·소득 한도·금리·카드 혜택·FAFSA 날짜)는 **단정하지 않고** 공식 링크로 안내. 그래프 숫자는 모두 "예시/가정" 표기. 히트펌프 연방 세액공제는 종료·변경 가능성 때문에 "확인하세요"로만 씀. 조지아 법정 최소 자동차보험 25/50/25 와 HOPE 3.0 GPA 는 일반 지식 기반 — **Kay 또는 전문가 검수 권장**.
- **확인한 것**: 로컬 미리보기(PC·휴대폰, 계산기·색상), 서버 `/api/money/guides` 41개 공개, 다운로드 파일 200, 실제 화면(`/money/guide/retire-guide-1`) 렌더 확인.
- **이 PC 한계**: PHP 없음 → 서버 PHP 는 배포 후 Actions 의 PHP 문법 검사와 스모크로만 확인.

### 다음에 할 만한 것 (Kay 선택)
1. 41편 **내용 검수**(특히 세금·보험·은퇴·교육 숫자) — 틀린 곳은 JSON 고치고 `blocks` 를 갱신하려면 **관리자 JSON 칸에서 수정**하거나, 서버 값을 덮어쓰는 새 마이그레이션이 필요(기존 값 있으면 안 덮음).
2. 가이드 표지 그림(일러스트) 추가 — 지금은 색 그라데이션 + 선 아이콘. 이미지를 쓰면 `assets` 용량·속도 고려.
3. 홈 MONEY 카드·검색에 가이드 포함·가이드 조회수/북마크(T7 후보).



---

## 15. MONEY 카페 운영팀 글 채우기 + 내돈내산 정리 (2026-10-12 UTC 17:10 / 애틀랜타 낮 1:10)

### 15-1. MONEY 운영팀 글 (Kay 기준: 노인도 따라 할 수 있게 — 실제 화면 캡처+번호 핀, 단계, 출처 필수, 글당 약 2,300자+, 주제당 3편)
- 글 위치: `database/data/money_posts/<카페slug>.json` (배열, 항목 `topic`(= money_categories 이름과 정확히 같게)·kind·title·content·image(`/images/money/topics/<slug>-NN.svg`)·blocks). 배포 때마다 `php artisan money:sync-posts`(`app/Console/Commands/SyncMoneyPosts.php`)가 카페+제목 기준으로 새 글 추가·blocks 갱신. 작성자는 운영 계정.
- 캡처: `public/images/money/shots/*.jpg`(1100×760, *.png 는 gitignore). 헤드리스 Chrome: `chrome --headless=new --window-size=1100,760 --screenshot=...` → PIL 로 jpg. 앱 화면은 App Store 공식 스크린샷(`app-<이름>-N.jpg`, narrow). **404 캡처 주의**(irs-aotc.jpg 는 404라 미사용·미커밋).
- 진행표: earn 31 · save 30 · grow 30 · home 36 · insurance 36 · **tax 36(완료, `bb209a83`·`e79484be`)** · **남은 카페: cards(신용카드·포인트) → stocks(주식) → kids(자녀·교육비) → retire(은퇴·노후) → relay(인증 릴레이) → talk(돈 이야기)**. 주제 목록은 `database/migrations/2026_10_12_130000_money_cafe_fronts.php`.
- 다음 계정은 cards 카페부터 같은 방식으로(주제 6개씩 18편 단위 배포, 배포 후 `/api/money/cafes/<slug>/posts?per_page=100` 의 total 로 개수 확인).
- **tax 후반 18편 Kay/전문가 검수 필요**: CAPS 소득 기준(50% vs 30% SMI), AOTC·LLC SSN 조건 시작 연도, 2026.10.1 이민자 Medicaid·SNAP 자격 변경의 조지아 시행, MSP 소득 비율(약 100/120/135%), Lifeline 고객센터 번호, 조지아 퇴거 절차 세부(HB 404 출처 LegiScan). 숫자는 대부분 "공식 표 확인"으로 씀.

### 15-2. 내돈내산 (코드 `e374c04e`, `126d9177`, + 썸네일 이름 빼기)
- 위쪽 "새로 올라온 후기" 사진을 작은 썸네일로(PC 2×2 h-24, 휴대폰 한 줄 4개 h-16), 사진 아래 이름은 Kay 지시로 뺌(마우스 올리면 title).
- 카테고리를 `resources/js/utils/shoppingCategories.js` 하나로 통일(목록·상세·글쓰기·관리자). 16개 '/' 묶음 + 특별 3개(인기상품·오늘의 딜·Awesome Korean 추천 — 회원 글쓰기엔 안 보임). 기존 상품은 마이그레이션 `2026_10_13_230000_shopping_categories_grouped` 로 새 이름으로 옮김(비타민 → 건강/비타민/영양제, 창고·잔디·차고·발전기 → 정원/공구/DIY). 휴대폰 목록에 카테고리 고르기 칸 추가. 백업 `qa/backups/shopping_cats_20261012/`.
- **카테고리 이름을 바꿀 땐** JS 목록 + 기존 상품 옮기는 마이그레이션을 같이.
