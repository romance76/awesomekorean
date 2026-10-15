// 화면(SPA) 라우터의 최상위 주소와 서버의 SeoHead::SPA_SEGMENTS 목록이 서로 같은지 확인한다.
// 어긋나면 새 화면이 HTTP 404 상태로 내려가 검색에 안 잡히므로(화면은 열림), 라우터에 주소를 추가했다면 SeoHead.php 도 같이 고쳐야 한다.
// 사용: node qa/scripts/check_spa_segments.cjs   (CI 의 "Frontend build check" 에서도 실행)
const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..', '..');
const router = fs.readFileSync(path.join(root, 'resources/js/router/index.js'), 'utf8');
const php = fs.readFileSync(path.join(root, 'app/Support/SeoHead.php'), 'utf8');

const fromRouter = new Set();
for (const m of router.matchAll(/\bpath:\s*'(\/[^']*)'/g)) {
  const first = m[1].split('/').filter(Boolean)[0];
  if (first && !first.startsWith(':')) fromRouter.add(first);
}
fromRouter.delete('404');   // '/404' 화면 자체는 일부러 404 상태로 내린다

const block = (php.match(/SPA_SEGMENTS\s*=\s*\[([\s\S]*?)\];/) || [])[1] || '';
const fromServer = new Set([...block.matchAll(/'([a-z0-9-]+)'/gi)].map((m) => m[1]));
// 서버가 따로 처리하는 주소는 라우터에 없어도 된다
const serverOnly = new Set(['info', 'go']);

const missingOnServer = [...fromRouter].filter((s) => !fromServer.has(s));
const extraOnServer = [...fromServer].filter((s) => !fromRouter.has(s) && !serverOnly.has(s));
if (missingOnServer.length || extraOnServer.length) {
  if (missingOnServer.length) console.error('라우터에는 있는데 SeoHead::SPA_SEGMENTS 에 없음 (이 화면이 404 상태로 내려감):', missingOnServer.join(', '));
  if (extraOnServer.length) console.error('SeoHead::SPA_SEGMENTS 에는 있는데 라우터에 없음:', extraOnServer.join(', '));
  process.exit(1);
}
console.log('OK: 라우터와 서버의 화면 주소 목록이 같습니다 (' + fromRouter.size + '개)');
