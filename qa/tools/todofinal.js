// Apply today's marking migrations to the reconstructed list and print what is left, grouped.
const items = require('./todosim.json');
const like = (pat) => { const esc = pat.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/%/g, '.*'); const r = new RegExp('^' + esc + '$', 'i'); return (t) => r.test(t); };
const find = (pat) => items.filter((i) => like(pat)(i.title));
const setDone = (pat) => find(pat).forEach((i) => { i.st = 'done'; });
const setCat = (pat, cat) => find(pat).filter((i) => i.st !== 'done').forEach((i) => { i.cat = cat; });
const setDoing = (pat) => find(pat).filter((i) => i.st === 'todo').forEach((i) => { i.st = 'doing'; });

// done by today's migrations
['deploy.sh 에 queue:restart%', '배포 중복 실행 방지%', '사이트 설정 껍데기 토글 점검%', '관리자 수집 버튼을 큐로%', '인보이스 1단계%', '진짜 VAPID 키 생성%',
  '.claude/settings.local.json 의 임시 허용 규칙%', '관리자 알림을 누르면 관리자 처리 화면으로 이동%', '관리자 2단계 인증(2FA) 추가%', 'CI 보강%', '회원 일괄 작업·검색 확장·IP 차단 기간%',
  '게임 포인트 지급 한도%', '안심서비스 체크인 포인트%'].forEach(setDone);
['DB 자동 백업 + 복구 연습%', '전체 CSP(스크립트 실행 제한) 켜기%'].forEach(setDoing);

const mine = ['구글에 색인된 페이지 수 확인%', 'Google AdSense 가입 + 코드 연동%', '가입 인증 메일이 실제로 도착하는지 확인%', '안 쓰는 구글 클라우드 정리%', '서버 재부팅%', '애널리틱스 "향상된 측정" 켜기%',
  '애드센스 거절 사유 확인%', '오픈 전 서버 작업 순서%', 'MySQL 느린 쿼리 기록%', 'Kay 휴대폰 실기기 확인%', 'SPF/DKIM 에 실제 발신 서비스%', '푸시 알림(Firebase) 테스트 결과 확인%',
  '내일 방문 분석에 본 사이트 방문이 나오는지 확인%', '실제 아이폰에서 홈 마퀴 끊김 확인%', 'API 키 변경 메일 수신 확인%', '관리자 보안 화면 동작 확인%', '새 관리자 화면 휴대폰 점검 루틴%',
  '애널리틱스 계정/속성/스트림 이름을%', '옛 info.awesomekorean.com 을 새 주소로 301 이동%', 'fail2ban 에 nginx 404/403%', '음악 관리자 표시150/숨김84%', '반복 일정·가입 보너스 실제 동작 시험%',
  '구글 지도 키를 사이트 주소로만 제한%', '스케줄러 매분 작업 정리%'];
const decide = ['AI 생성 글 검수 방침%', '네이버 지식인에서 가져온 Q&A%', '재신청 시점 정하기%', '테스트 운영자·더미 계정 40개 정리%', '임시 중고장터/부동산 매물 정리%', '정보 글 가짜 조회수%',
  '구매·환불·환전 포인트를 회원 등급에 반영할지 결정%', '친구 카드·채팅 참가자·쪽지함의 시·주 표시%', '오타 계정 id 2902%', '신규 가입 시 관리자 알림 메일 필요 여부 결정%', '어썸 뮤직 PWA%',
  '쿠키 동의 배너 필요성 검토%', '뉴스(RSS·번역) 게재 범위 점검%', '더미 사용자·더미 동호회 정리%', '환불 정책 정리%', '정보 글 품질 점검%', '오래된 pending 결제 자동 정리%'];
mine.forEach((p) => setCat(p, '내가 할 것'));
decide.forEach((p) => setCat(p, '결정 필요'));
// new items the migration adds (not in the reconstruction)
const added = [['내가 할 것', '내 관리자 계정에 2단계 인증 켜기 + 로그인 시험'], ['내가 할 것', 'DB 백업을 서버 밖에도 보관하기 (BACKUP_UPLOAD_CMD)'], ['내가 할 것', '첫 DB 복구 연습 실행 (php artisan db:restore-check)'], ['보안', 'CSP 보고 로그 확인 후 강제 모드로 전환'], ['결정 필요', '광고 할인이 실제 결제 금액에 적용되는지 확인']];
added.forEach(([cat, title]) => items.push({ cat, title, st: 'todo', pri: 'high', seq: 9999 }));

const open = items.filter((i) => i.st !== 'done');
const by = {};
open.forEach((i) => { (by[i.cat === '내가 할 것' || i.cat === '결정 필요' ? i.cat : '나머지'] ||= []).push(i); });
console.log('total', items.length, '| done', items.length - open.length, '| open', open.length);
for (const k of ['내가 할 것', '결정 필요', '나머지']) { console.log('\n== ' + k + ' (' + (by[k] || []).length + ')'); (by[k] || []).forEach((i) => console.log('  - [' + i.st + '] ' + i.title.slice(0, 80))); }
