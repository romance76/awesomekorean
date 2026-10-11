# 다른 Claude 계정에서 한 번에 이어서 실행하기

## 방법 A — 한 줄 (이 저장소를 연 Claude Code 에서)
```
/continue
```
(저장소의 `.claude/commands/continue.md` 가 자동으로 불려요. 코드만 올리고 싶을 때는 `/ship "커밋 메시지" 파일...`)

## 방법 B — 슬래시 명령이 안 보이면 이 글을 그대로 붙여넣기
```
awesomekorean.com 프로젝트를 이어받아 주세요. 모든 답은 존댓말로, 시간은 UTC(애틀랜타) 병기.
1) qa/HANDOFF_LATEST.md 와 qa/TASKS_QUEUE.md 를 읽고, qa/NEXT_SESSION_START.md 는 무시하세요.
2) T0 점검(git status, gh run list --limit 3 --workflow "Deploy to Production", bash scripts/smoke.sh)을 하고 이상이 있으면 먼저 고치세요.
3) TASKS_QUEUE.md 에서 status 가 ready 인 맨 위 작업을 끝까지 하세요. needs_kay 작업은 시작하지 말고 Kay 에게 결정할 것만 모아 한 번에 물어보세요. 큰 작업(돈·결제·삭제)은 계획서를 먼저 보여 주세요.
4) 작업은 작게 쪼개서 매번 `CO_AUTHOR="<모델 이름>" bash scripts/ship.sh "커밋 메시지" 파일1 파일2 ...` 로 올리세요(검사·빌드·커밋·push·배포 success 확인·스모크 테스트가 한 번에 돕니다. git add -A 금지).
5) 끝나면 TASKS_QUEUE.md 상태와 HANDOFF_LATEST.md 를 갱신하고, 배포가 끝난 뒤 `SKIP_BUILD=1 bash scripts/ship.sh "인수인계 갱신" qa/TASKS_QUEUE.md qa/HANDOFF_LATEST.md` 로 올리세요.
6) Kay 에게 한 일 / 배포 결과 / 눈으로 확인할 것 / 다음 작업·결정 필요한 것 을 보고하세요.
절대 규칙: 수집 데이터·더미 계정 삭제 금지, 테이블 DROP·force-push 금지, 비밀값 출력 금지, 게임·안심·환불 정책은 보류.
```

## 필요한 것 (처음 한 번)
- `gh` (GitHub CLI) 로그인: `gh auth status` — 배포 성공 확인에 씁니다.
- `node`(프런트 빌드), `git`, `curl`. 서버 읽기 점검(`scripts/server_check.sh`)은 SSH 키(`root@68.183.60.70`)가 있을 때만 — 없거나 막히면 건너뛰어도 됩니다.
- 로컬에 PHP 가 없어도 됩니다(문법 검사는 push 후 GitHub Actions 가 합니다).
