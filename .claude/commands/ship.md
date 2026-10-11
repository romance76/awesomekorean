---
description: 지금까지 수정한 파일을 검사·빌드·커밋·push·배포 확인·스모크 테스트까지 한 번에
argument-hint: "커밋 메시지" 파일1 파일2 ...
---

`scripts/ship.sh` 로 한 번에 배포하세요. 인자: $ARGUMENTS

1. `git status -s` 로 바뀐 파일을 확인하고, 이번 작업과 관련된 파일만 골라 **하나씩 지정**합니다(`git add -A` 금지, `.env`·firebase json·`CLAUDE.md` 제외).
2. `CO_AUTHOR="<현재 모델 이름>" bash scripts/ship.sh "한국어 커밋 메시지" <파일들...>` 를 실행합니다. 배포가 끝날 때까지(보통 5~8분) 기다리며 출력(검사→빌드→커밋→push→배포 success→스모크)을 확인합니다.
3. 실패하면 출력의 원인(빌드 오류, 깨진 이름공간, 배포 로그)을 고친 뒤 같은 명령을 다시 실행합니다.
4. 성공하면 Kay 에게 존댓말로 보고합니다: 한 일, 커밋 해시, 배포 success, 눈으로 확인할 것.
