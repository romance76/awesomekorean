# qa/tools — PHP 없이 하는 검증 도구

이 저장소를 작업하는 PC 에 PHP 가 없을 때, PHP 8.2 를 WebAssembly(`@php-wasm/node`)로 Node 안에서 돌려 **문법 검사 · 순수 함수 단위 시험**을 합니다. (배포 때 GitHub Actions 의 `php -l` 이 한 번 더 검사함.)

```bash
cd qa/tools && npm i            # 처음 한 번
export AK_ROOT=<저장소 루트 경로>  # refcheck/rulecheck 등이 저장소를 찾는 곳 (생략하면 현재 폴더)
node php.js version              # PHP 8.2 + openssl/hash 확인
node php.js lint ../../app/Support/WriteLimits.php ../../routes/web.php     # 문법
node php.js run t_writelimits.php ../../app/Support/WriteLimits.php         # 단위 시험 (시험 파일 + 대상 파일들)
node refcheck.js                 # 바뀐 php 의 클래스·메서드·라우트 존재 점검 (거짓 경고 있음: Eloquent 정적 메서드 등)
node rulecheck.js                # git diff 에 새로 들어간 검증 규칙 문자열이 진짜 규칙 이름인지
node vuecheck.cjs ../../resources/js/pages/groupbuy/GroupBuyHome.vue   # Vue 템플릿·스크립트 컴파일 오류 (npm i 한 폴더에서)
node routesegs.js                # 라우터 첫 구간 목록 (SeoHead::SPA_SEGMENTS 와 비교용, CI 는 qa/scripts/check_spa_segments.cjs)
node writeaudit.js               # 쓰기 함수(store/update…)에 검증이 있는지 훑어 보는 목록
node todosim.js && node todofinal.js   # database/migrations 의 할 일(admin_todos) 마이그레이션을 재생해 목록을 재구성(운영 DB 를 못 읽을 때의 근사치)
```

- 시험 파일(`t_*.php`)은 `namespace` 블록으로 필요한 클래스(Model·Cache·Schema 등)를 **가짜로 정의**하고 `require '/tmp/<파일>.php'` 로 대상 코드를 불러 씁니다. `node php.js run` 이 인자로 준 파일들을 가상 `/tmp` 에 복사합니다.
- **PHP/JS 코드를 셸 heredoc·`node -e` 로 만들지 말 것**: 백슬래시가 사라집니다. Edit/Write 도구를 쓰세요.
- 게임 관련 시험(`t_gamecap`)은 게임 서비스를 실행하지 않기로 해서 넣지 않았습니다.
