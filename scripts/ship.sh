#!/usr/bin/env bash
# 한 방에 배포: 검사 → 프런트 빌드 → (지정한 파일만) 커밋 → push → 배포 성공 확인 → 스모크 테스트.
#
# 사용법 (저장소 맨 위 폴더에서):
#   scripts/ship.sh "커밋 메시지(한국어)" 파일1 파일2 ...
#   CO_AUTHOR="Claude Sonnet 5.5" scripts/ship.sh "메시지" 파일...      # 커밋 끝줄 이름(기본: Claude)
#   SKIP_BUILD=1 scripts/ship.sh "문서만 수정" qa/HANDOFF_LATEST.md     # 프런트 변경이 없을 때만
#
# 규칙(Kay 확정): 파일을 하나씩 지정해서 커밋(git add -A 금지), main 으로만 push(force 금지),
#   .env·firebase json·naver_scraper.db·CLAUDE.md 는 절대 커밋 안 함. 배포 확인은 서버 로그가 아니라 GitHub Actions 로.
#   코드를 올린 뒤 "배포가 끝나기 전에" 문서를 또 push 하면 진행 중 배포가 줄을 서므로, 이 스크립트가 끝나(success)고 나서 문서를 올릴 것.
set -uo pipefail

cd "$(git rev-parse --show-toplevel)" || exit 1
MSG="${1:-}"; shift || true
FILES=("$@")
if [ -z "$MSG" ] || [ ${#FILES[@]} -eq 0 ]; then
  echo "사용법: scripts/ship.sh \"커밋 메시지\" 파일1 파일2 ..."; exit 2
fi

red() { printf '\033[31m%s\033[0m\n' "$*"; }
grn() { printf '\033[32m%s\033[0m\n' "$*"; }
step() { printf '\n\033[1m▶ %s\033[0m\n' "$*"; }
TMPDIR_="${TEMP:-${TMPDIR:-/tmp}}"

# ── 0) 커밋하면 안 되는 파일 거르기 ──
for f in "${FILES[@]}"; do
  case "$f" in
    .env|*/.env|.env.*|*firebase-adminsdk*|*naver_scraper.db|CLAUDE.md)
      red "커밋하면 안 되는 파일이에요: $f"; exit 2;;
  esac
  [ -e "$f" ] || { red "파일이 없어요: $f"; exit 2; }
done

# ── 1) 흔한 사고 검사: 백슬래시가 사라져 깨진 PHP 이름공간 (셸 heredoc 에서 자주 생김) ──
step "1/6 깨진 이름공간 검사"
if grep -rnE "AppSupport|AppModels|AppServices|AppHttp|IlluminateSupport|IlluminateHttp|IlluminateDatabase" app routes database bootstrap resources/views 2>/dev/null | head -5 | grep .; then
  red "위처럼 '\\' 가 사라진 이름이 있어요. use 문 + 짧은 이름 또는 Edit/Write 도구로 고친 뒤 다시 실행하세요."; exit 1
fi
grn "OK"

# ── 2) 프런트 빌드 ──
if [ "${SKIP_BUILD:-0}" != "1" ]; then
  step "2/6 프런트 빌드 확인 (vite)"
  OUT="$TMPDIR_/vbuild_check_ship"
  if ! node node_modules/vite/bin/vite.js build --outDir "$OUT" --emptyOutDir > "$TMPDIR_/ship_build.log" 2>&1; then
    red "빌드 실패:"; tail -30 "$TMPDIR_/ship_build.log"; exit 1
  fi
  grn "빌드 OK ($(grep -o 'built in [0-9.]*s' "$TMPDIR_/ship_build.log" | tail -1))"
else
  step "2/6 프런트 빌드 건너뜀 (SKIP_BUILD=1)"
fi

# ── 3) 커밋 (지정한 파일만) ──
step "3/6 커밋"
git add -- "${FILES[@]}" || exit 1
if git diff --cached --quiet; then red "커밋할 변경이 없어요."; exit 1; fi
CO="${CO_AUTHOR:-Claude}"
git commit -q -m "$MSG

Co-Authored-By: $CO <noreply@anthropic.com>" || exit 1
grn "커밋: $(git log --oneline -1)"

# ── 4) 다른 세션이 먼저 push 했는지 확인 후 push ──
step "4/6 push (HEAD → main)"
git fetch -q origin
if [ -n "$(git log --oneline HEAD..origin/main | head -1)" ]; then
  echo "origin/main 에 새 커밋이 있어요 → rebase 합니다."
  if ! git rebase origin/main > "$TMPDIR_/ship_rebase.log" 2>&1; then
    git rebase --abort >/dev/null 2>&1; red "rebase 충돌. 수동으로 해결하세요:"; cat "$TMPDIR_/ship_rebase.log" | tail -10; exit 1
  fi
fi
git push origin HEAD:main 2>&1 | tail -2 || { red "push 실패"; exit 1; }
SHA="$(git rev-parse --short=8 HEAD)"

# ── 5) 배포 성공 확인 (GitHub Actions: Deploy to Production + PHP Syntax Check) ──
step "5/6 배포 확인 ($SHA) — 보통 5~8분"
DEADLINE=$(( $(date +%s) + 1200 ))
R=""
while [ "$(date +%s)" -lt "$DEADLINE" ]; do
  sleep 20
  R="$(gh run list --limit 8 --json name,status,conclusion,headSha -q '[.[]|select(.headSha|startswith("'"$SHA"'"))|.name+" "+.status+" "+.conclusion]|join(" | ")' 2>/dev/null)"
  echo "  $(date +%H:%M:%S)  $R"
  if echo "$R" | grep -q "Deploy to Production completed"; then break; fi
done
if ! echo "$R" | grep -q "Deploy to Production completed success"; then
  red "배포가 성공하지 못했어요.";
  RID="$(gh run list --limit 8 --json databaseId,headSha,conclusion -q '[.[]|select(.headSha|startswith("'"$SHA"'"))|select(.conclusion=="failure")|.databaseId][0]' 2>/dev/null)"
  [ -n "$RID" ] && [ "$RID" != "null" ] && gh run view "$RID" --log-failed 2>/dev/null | tail -40
  exit 1
fi
if echo "$R" | grep -q "PHP Syntax Check completed failure"; then red "PHP 문법/프런트 빌드 검사 실패: gh run list 로 확인하세요."; exit 1; fi
grn "배포 success ($SHA)"

# ── 6) 스모크 테스트 ──
step "6/6 스모크 테스트"
bash "$(dirname "$0")/smoke.sh" || { red "스모크 테스트 실패 — 위 항목을 확인하세요."; exit 1; }

grn "✅ 끝: $SHA 배포·검증 완료. (문서 push 는 이제 해도 됩니다)"
