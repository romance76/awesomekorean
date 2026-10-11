---
description: 인수인계 이어받기 — 할 일 대기열의 다음 작업을 끝까지(코드→배포→검증→문서) 처리하고 보고
---

당신은 awesomekorean.com 프로젝트를 이어받은 개발자입니다. 아래 순서를 **멈추지 말고 끝까지** 실행하세요. (모든 대화는 Kay 에게 **존댓말**, 쉬운 말, 시간은 UTC(애틀랜타) 병기)

1. `qa/HANDOFF_LATEST.md` 와 `qa/TASKS_QUEUE.md` 를 읽는다. (옛 `qa/NEXT_SESSION_START.md` 는 무시)
2. **T0(시작 점검)** 을 한다: `git status`, `git log --oneline -5`, `gh run list --limit 3 --workflow "Deploy to Production"`, `bash scripts/smoke.sh`. 이상이 있으면 새 작업 전에 원인부터 고친다.
3. 대기열에서 status 가 `ready` 인 **맨 위 작업**을 골라 실행한다.
   - `needs_kay` 작업은 시작하지 않는다. 그 작업이 맨 위라면 Kay 에게 **결정할 것만 모아서 한 번에** 질문하고, 그동안 `ready` 인 다른 작업(T5 등)을 처리한다.
   - 큰 작업(결제·돈·보안·삭제)은 계획을 `qa/*_plan.md` 에 먼저 쓰고 Kay 확인을 받는다.
4. 작업은 **작게 쪼개** 각각 `scripts/ship.sh "커밋 메시지" 파일1 파일2 ...` 로 배포한다. (빌드·깨진 이름공간 검사 → 파일 지정 커밋 → push → 배포 success 확인 → 스모크 테스트가 한 번에 돈다.) 커밋 끝줄 이름은 `CO_AUTHOR="<현재 모델 이름>"` 환경변수로.
5. 배포 뒤 가능한 만큼 **직접 검증**한다: `bash scripts/smoke.sh`, `bash scripts/server_check.sh`(서버 읽기 전용, 막히면 우회하지 말고 Kay 에게 허용 요청), 서버 DB 시험은 롤백되는 트랜잭션으로(운영 DB에 흔적 남기지 않기).
6. 작업이 끝나면 `qa/TASKS_QUEUE.md` 의 status 를 `done` 으로 바꾸고 결과를 적고, `qa/HANDOFF_LATEST.md` 의 "현재 상태·다음 할 일"을 갱신한 뒤 **배포가 끝난 다음** `SKIP_BUILD=1 scripts/ship.sh "인수인계 갱신" qa/TASKS_QUEUE.md qa/HANDOFF_LATEST.md` 로 올린다.
7. Kay 에게 보고한다: ① 한 일(쉬운 말) ② 배포 결과(success 와 커밋) ③ Kay 가 눈으로 확인할 것 ④ 다음 작업과 결정이 필요한 것.

절대 규칙: 수집 데이터(news/music_tracks/shorts/businesses/recipe_posts)·더미 계정 삭제 금지, 테이블 DROP·main force-push 금지, 비밀값 출력 금지, 게임·안심 서비스 보류, 환불 정책 보류, 파일 하나씩 지정해 커밋(`git add -A` 금지), 셸 heredoc/`node -e` 안에서 백슬래시가 사라지니 PHP 는 `use` + 짧은 이름 또는 Edit/Write 도구 사용.
