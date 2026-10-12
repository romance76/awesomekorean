# MONEY 허브 계획서 (메뉴 개편 2단계) — 2026-10-12, Kay "시작해" 승인

## Kay 확정 내용
- MONEY = 허브. 첫 화면(`/money`) = ① **분야별 정보 공유 카페 12개** ② **머니 가이드(설명 페이지)**. 머니 게시판 없음.
- 카페: **가입 없이** 누구나 보기·답글, 새 글은 로그인 회원. 나중에 활동 많은 회원을 카페 운영자로 지정(칸만 준비).
- "분야별 카페 둘러보기" → **카페 전체 안내 페이지**(`/money/cafes`, 카페마다 대문 설명이 쭉).
- 카페를 누르면 맨 위 **대문**(다루는 것·처음 읽을 가이드·인기 정보·규칙 — 관리자가 편집) + 아래 게시판(전체·정보·질문·소통).
- 내돈내산 리뷰는 MONEY 와 별개(상단 단독), 동호회는 커뮤니티. 정식 이름은 미정(관리자 › 메뉴 묶음에서 변경).
- 금융·세무·보험 정보는 "전문가 상담을 대체하지 않음" 안내 필수.

## 데이터 (커뮤니티 게시판과 섞이지 않게 새 표 — 기존 데이터 손대지 않음)
- `money_cafes`: slug, name, icon, summary(한 줄), intro(대문 HTML), sort_order, is_active, manager_user_id(나중), timestamps
- `money_posts`: cafe_id, user_id, kind(info|question|talk), title, content(HTML 정리), view_count, comment_count, is_hidden, timestamps
- `money_guides`: slug, icon, title, summary, content(HTML), is_published, sort_order, timestamps
- 댓글은 기존 댓글 시스템(`comments`, 종류 `money_post`) 재사용. 글 포인트는 기존 `WritePoints` 규칙.
- 시드: 카페 12개(대문 기본 문구 포함), 가이드 5개(**비공개 초안** — 관리자가 내용 쓰고 공개).

## 화면
- `/money` 허브 · `/money/cafes` 카페 안내 · `/money/cafe/:slug` 대문+게시판 · `/money/post/:id` 글(댓글) · `/money/write` 글쓰기 · `/money/guide/:slug` 가이드
- 모든 화면 제목 자리: `MONEY › …` 경로. 초록색 계열(샘플 그대로).
- 관리자 › (콘텐츠) **MONEY 관리**: 카페(이름·아이콘·한 줄·대문·순서·켜기), 가이드(쓰기·공개), 글(숨김/복구).

## 배포 순서 (각각 `ship.sh`, 끝날 때마다 인수인계 갱신)
1. 표·모델·시드·공개 API(카페 목록/하나/글 목록/글/쓰기/수정/삭제, 가이드) + 댓글 종류 추가
2. 화면 6개 + 라우트
3. 관리자 MONEY 관리 + AdminTier 규칙
4. 관리자 › 메뉴 묶음에서 MONEY 숨김 해제(스위치는 Kay)
