# 작업지시서 — 2026-10-11 (UTC) / 애틀랜타 10-11 새벽 · 다른 계정/세션에서 이어 받을 때

**읽는 순서**: 이 파일 → `qa/HANDOFF_2026-10-11.md`(18~21번 섹션이 최신) → `CLAUDE.md`.
`qa/NEXT_SESSION_START.md` 는 **4월 자료라 지금 작업과 무관**(무시).
첫 메시지 예: "qa/WORK_ORDER_2026-10-11.md 읽고 이어서 진행해줘."

---

## 0. 지켜야 할 규칙 (Kay 확정)
1. **모든 답변은 존댓말**, 쉬운 말, 시간은 UTC(애틀랜타/EDT) 병기. Kay 는 Atlanta 거주 한국인.
2. **한 단계 안에서는 직접**: 수정 → 로컬 빌드 → 커밋(파일 하나씩 지정, `git add -A` 금지) → `git fetch` → `git push origin HEAD:main` → 배포 확인 → Kay 에게 바로 보고. 승인받으려고 멈추지 않음.
3. **다음 단계로 넘어가는 건 Kay 가 "넘어가/계속해" 해야 함.** 단, Kay 가 범위를 정해서 "여기까지 연속으로"라고 하면 그 범위 안에서는 멈추지 않음(2026-10-11 새벽에 아래 범위가 허락됐고 모두 끝남).
4. **큰 작업·위험한 작업(결제·보안 대수술, 큰 파일 쪼개기)은 시작 전에 먼저 여쭘.**
5. 병렬 멀티에이전트 워크플로우는 쓰지 않음(토큰 소모 큼) — 직접 하나씩.
6. **절대 금지**: 수집 데이터(news/music_tracks/shorts/businesses/recipe_posts) 수정·삭제, 테이블 DROP, main force-push, 비밀번호·API 키 값 출력/저장, 게임·안심(elder) 서비스 작업.
7. 커밋 금지 파일: `.env`, `somekorean-c9430-firebase-adminsdk-*.json`, `qa/backups/naver_scraper.db`, `CLAUDE.md`(원래 미추적 — 올리지 말 것).
8. 커밋 메시지 끝: `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## 1. 배포·확인 방법 (중요)
- 서버: `/var/www/awesomekorean` (CLAUDE.md 에 정정함). `git push origin HEAD:main` → GitHub Actions → 서버 deploy.sh (migrate 가 빌드 전에 실행, 빌드 5~7분).
- **배포 확인은 서버 로그 대신** `gh run list --limit 3 --workflow "Deploy to Production"` (success 확인). 서버 SSH 읽기는 자동 모드 분류기가 막는 경우가 있음 — 우회하지 말고 Kay 에게 물을 것.
- **문서만 따로 push 하지 말고, 코드 배포가 끝난 뒤에** 올릴 것(진행 중 배포가 줄을 서서 취소·지연됨).
- 로컬엔 PHP 가 없음 → PHP 문법은 push 하면 CI `PHP Syntax Check`(app/routes/config/database/bootstrap + 프런트 vite 빌드)가 검사함.
- 프런트 빌드 확인(로컬): `node node_modules/vite/bin/vite.js build --outDir "$TEMP/vbuild_check" --emptyOutDir`
- **편집 도구 함정**: bash heredoc/`node -e` 안의 `\\` 는 백슬래시가 깨짐(예: `\App\Support` → `AppSupport`) → PHP 는 `use` 문 + 짧은 이름을 쓰거나 Edit/Write 도구로 직접 수정. JS 의 `String.replace(a,b)` 는 `() => b` 사용.

## 2. 이번 세션(2026-10-11)에 끝낸 것 — 전부 배포 success (마지막 코드 `00f192aa`)
1. 사이트 설정 분리: `SiteSettings.vue` 2090줄 → 약 655줄, 탭별 컴포넌트 9개(`resources/js/components/admin/siteSettings/`).
2. 할 일 목록 정리 마이그레이션 2개(`250000` 메뉴 개편, `280000` 2차 작업 반영).
3. **회원 패널(PC)** `Members.vue`: 정지 필터 연결, 포인트 지급을 기록 남는 `/users/{id}/points` 로, 옛 confirm/alert 16곳 → 확인 시트·알림, 바뀐 칸만 저장, 권한 변경 확인, 여러 명 일괄 정지/해제(`POST /api/admin/users/bulk`, 최대 50명·운영진·본인 제외), 검색에 번호·전화·IP 추가. 백업 `qa/backups/members_pc_20261011/`.
4. **감사 로그 화면** `/admin/audit-log`(최고관리자, 휴대폰 포함) + `GET /api/admin/audit-log`. 환불은 사유까지 기록.
5. **오픈 이벤트(10/15~11/30)** 추천 설정으로 "켜 둠": 마이그레이션 `260000`(직접 저장한 설정이 있으면 덮어쓰지 않음). 끄려면 관리자 › 홍보·이벤트 › 오픈 이벤트 에서 '오픈 이벤트 사용'을 끄고 저장. 할 일 #50 완료.
6. **관리자 즐겨찾기**(#91): 메뉴 허브 타일의 ☆ → 홈 "즐겨찾기". 새 표 `admin_favorites`, `GET/PUT /api/admin/favorites`(운영자도 사용).
7. **휴대폰 같은 그룹 칩 줄**: 휴대폰 관리자 화면 위쪽에 같은 그룹(예: 홍보·이벤트) 화면으로 바로 이동하는 칩(PC 상단 서브탭과 같은 목록). 홍보·이벤트 화면을 한 파일로 합치지는 **않음**(딥링크 `?tab=`·권한 차이 위험이 이득보다 큼).
8. **환불 사유**(#104 1단계): 휴대폰 시트·PC 확인창에 사유 입력, 서버는 선택값으로 받음, 감사 로그에 처리자·사유·금액 기록. 확인 시트(`ConfirmSheet`)에 `inputLabel`/`inputRequired` 옵션 추가.
9. **권한 세부**(#133 일부): 채팅 영구제명에 운영진·본인 보호, Entry 거래 조회의 회원 이메일은 최고관리자만.
10. **한국어 입력 오류 메시지**(`lang/ko/validation.php` + `SetApiLocale`, API 한정), 없는 항목 404 를 한국어로(내부 클래스 이름 숨김).
11. **HTML 저장 방지**: 글 제목·내용, 댓글, 쪽지에서 HTML 태그를 저장 안 함(`app/Support/PlainText.php`, 부등호·수식 글은 그대로) + 쪽지 금칙어 필터.
12. **CI 보강**(#100): database/·bootstrap/ 문법 검사 + 프런트 vite 빌드 검사. **CLAUDE.md 서버 경로 정정**(#101).
13. 수집 전용 큐 워커 **설치 스크립트** `deploy/setup_collect_worker.sh` 작성(아직 서버에 설치 안 함 — 아래 3번).

## 3. 서버에서 해야 하는 일 (코드로 불가 — Kay 가 직접 하거나 SSH 허용 시)
1. **수집 큐 워커 분리** (권장, 오픈 전): 서버에서 root 로 `bash /var/www/awesomekorean/deploy/setup_collect_worker.sh` 1회. (supervisor 워커 `--queue=collect` 추가 + `.env` 에 `ADMIN_COLLECT_QUEUE=collect` + 캐시 갱신. 여러 번 실행해도 안전, `.env` 는 먼저 백업. 되돌리기 명령은 스크립트 마지막 출력에 있음.) 안 하면: 수집 버튼이 최대 20분 동안 기본 워커를 묶어 채팅·알림 실시간 전송이 밀릴 수 있음.
2. nginx `sites-enabled` 의 bak 사본 제거(#99 일부).
3. (Kay 가 직접 하겠다고 한 것) 서버 재부팅/2GB 플랜 업그레이드/PHP-FPM 튜닝, DB 자동 백업+복구 연습(#3, #4, #77, #78, #79).

## 4. Kay 의 결정 (2026-10-11)
- `test@test.com`: **지금처럼 그대로 둠**(로그인 편의용). 오픈 후에는 `admin@awesomekorean.com` 이 메인 최고관리자로 test@test.com 을 **대체**할 예정(그때 역할·비밀번호 정리).
- **Chloe(id 2905) = super_admin 이 맞음.** → 코드로 DB 를 바꾸지 않았음. 이미 super_admin 인 계정으로 관리자 › 회원관리에서 Chloe 의 권한을 최고관리자로 바꾸고 저장(새 권한 변경 확인창 거침). 지금 상태를 먼저 확인할 것.
- 큰 글씨(노안) 모드는 지금 그대로 둠. 아이폰 확인은 Kay 가 함.
- **보류/스킵**: Resend SPF/DKIM(#81, 어디에 필요한지 아직 모름), 소셜 가입(#65)·나머지 SNS(인스타·쓰레드만 가입함), 2FA(#1), 애드센스.
- music/shorts 자동 삭제는 그대로 유지(이전 결정).
- 더미 계정(#57)·동호회(#75) 삭제: **미결**(글 62·댓글 100 유지 여부 결정 필요) → 삭제하지 말 것.

## 5. 남은 할 일 (이 순서 제안)
1. **눈으로 확인** (Kay): PC `/admin` 사이드바 7그룹 · 휴대폰 하단 5탭과 새 칩 줄 · 사이트 설정 7개 탭 · 회원관리(필터·일괄·포인트·확인창) · 결제/오더 환불 사유 · 메뉴 허브 ☆ → 홈 즐겨찾기 · 감사 로그 · 로그아웃 상태 홈이 애틀랜타 근처 매물을 보여주는지 · 오픈 이벤트 상태가 '예정'(10/15 전)인지(마이그레이션은 직접 저장해 둔 설정이 있으면 켜지 않고 건너뛰므로, '사용'이 꺼져 있으면 화면에서 직접 켜고 저장).
2. 3번의 서버 작업(수집 워커 분리 등).
3. 부분 완료 항목의 남은 부분(할 일 목록에 메모로 적혀 있음): 환불(부분 환불·직접 결제 경로) · 권한 세부(경품 claims 조회의 읽기+쓰기 혼합) · HTML 저장(없는 글에 댓글) · IP 차단 기간·CIDR · 일괄 등급 변경 · 경품 매분 작업 3개 합치기 · 새 기능 킬 스위치/배포 후 화면 왕복 체크리스트.
4. **#134 관리자 목록 가볍게**: 할 일의 상세가 서버 DB 에만 있어 코드에서 내용을 확인 못 했음 → 관리자 › 할 일 목록에서 #134 설명을 읽고 진행.
5. 계정 정리: 위 4번 결정대로(Chloe 권한 확인, 오픈 후 admin@ 교체).
6. 기타 낮은 우선순위: 즐겨찾기 PC 사이드바 표시(지금은 휴대폰 홈 중심), 오픈 이벤트 종료(11/30) 후 정리.

## 6. 파일 위치 요약
- 관리자 메뉴 정의(단일 원본): `resources/js/admin/adminMenu.js`, 레이아웃 `resources/js/pages/admin/AdminLayout.vue`
- 공용 부품: `resources/js/components/admin/` (ConfirmSheet·DetailPanel·SaveBar·AdmTable·siteSettings/…), `resources/js/composables/useAdminConfirm.js`, `useAdminFavorites.js`
- 권한 등급표(서버): `app/Http/Middleware/AdminTier.php` (새 관리자 API 는 반드시 규칙표에 등급 지정, 운영자=1·관리자=2·최고관리자=3)
- 오픈 이벤트: `app/Support/OpenEvent.php`, `AdminOpenEventController.php`, 화면 `AdminOpenEvent.vue`
- 백업: `qa/backups/sitesettings_split_20261011/`, `qa/backups/members_pc_20261011/`

## 7. 추가 업데이트 (UTC 08:20) — 못 했던 것 재작업 결과 (코드 `9b5ba926` 배포 success)
- **서버 수집 워커 분리 완료**: SSH 가 허용돼 `deploy/setup_collect_worker.sh` 를 실행함. `awesomekorean-collect-worker` RUNNING, `.env` 에 ADMIN_COLLECT_QUEUE=collect 설정(백업 `.env.bak_collect_*` 있음). 이제 3번 1항은 끝. 되돌리기 명령은 스크립트 마지막 출력 참고.
- **서버 상태 읽기 확인**: Chloe(2905)=super_admin, 오픈 이벤트 enabled=true(2026-10-15~11-30, 상태 scheduled), admin_favorites 표·chat 색인 있음. 할 일 완료 66 / 남은 66.
- **#134 완료**(레시피 후보 DB 필터, 배너 목록 90일·1000건 제한), **IP 차단 기간·CIDR**, **댓글 대상 검증**, **경품 조회 정리**.
- **마이페이지 북마크** 가 `AppModelsChatRoom #33` 처럼 보이던 것 수정(서버가 제목·종류·이동주소를 계산, 채팅방은 /chat/:id, 지워진 항목 안내).
- **채팅방 입장 속도**: 메시지 목록 COUNT(*) 제거(simplePaginate) + chat_messages(chat_room_id, created_at) 색인 추가 + 참가자 목록 동시 로딩. 체감이 부족하면 다음은 방 목록(/api/chat/rooms) 쿼리와 공개방 입장 흐름을 측정할 것.
- 아직 남음: 환불 정책 2단계(포인트 부족 시 부분 환불·직접 결제 환불 — **Kay 정책 결정 필요**), 일괄 등급 변경, 경품 매분 작업 3개 합치기, nginx bak 사본 제거, 더미 계정 삭제 결정(#57/#75), 관리자 배너 화면 페이지 넘기기.

## 8. 추가 업데이트 (UTC 10:30) — 포인트·Entry 구분 1단계, Entry 보상, 광고 카드 결제 2단계 (코드 `a65571bd` 배포 success)
- 기준 문서: `qa/points_entry_ads_structure_report.md` (Kay 결정 7번·추가 결정·2단계 완료 섹션).
- nginx bak 4개 → `/root/nginx_bak_20261011/` 로 옮김(지우지 않고 보관), nginx 재로드·사이트 정상.
- 더미 데이터는 **모두 유지**(Kay 결정). 환불 정책 **보류**.
- 다음 단계: 3단계 공동구매(주최자 오픈 수수료 카드 결제·참가 무료·정보 제공 동의·종료 후 명단 제공) → 4단계 월간 리더보드 + 1~5등 차등 Entry → 5단계 포인트→기프트카드(Amazon·Starbucks, 1,000P=$1, 수수료 10% 최소 $1, 최소 $10, 월 $20·연 $500, 관리자 승인).
- Kay 확인 필요: 광고 달러 가격(1P=1¢ 로 환산한 값)이 적당한지, Stripe 가 테스트/실결제 어느 모드인지(실결제면 실제 카드로 소액 테스트 후 관리자 승인·거절까지 확인).
