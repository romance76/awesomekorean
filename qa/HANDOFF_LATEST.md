# ⭐ 최신 인수인계 (다른 계정/세션에서 이어 받을 때 가장 먼저 읽기)

작성: 2026-10-11 UTC 11:00 (애틀랜타 새벽 7:00) · 마지막 배포 코드 `02a877a4` (Deploy·PHP Syntax Check 모두 success)
시작 멘트 예: **"qa/HANDOFF_LATEST.md 읽고 이어서 진행해줘."**

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
