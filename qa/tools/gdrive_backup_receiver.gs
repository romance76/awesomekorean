/**
 * Awesome Korean — DB 백업 받는 곳 (구글 드라이브) · 구글 Apps Script 웹 앱
 *
 * 서버가 매일 백업 파일(base64 글자)을 이 웹 앱 주소로 보내면
 *   1) 내 드라이브의 "awesomekorean-backups" 폴더에 저장하고,
 *   2) 저장이 확인된 뒤에만 그 전 백업은 휴지통으로 옮긴다 (휴지통은 30일 뒤 자동 삭제 — 실수해도 30일 안에는 복구 가능).
 *
 * 설치(브라우저만 사용): qa/DB_BACKUP.md 의 "웹 브라우저로 하는 방법" 참고.
 * 비밀 토큰은 코드에 적지 않고 프로젝트 설정 > 스크립트 속성의 BACKUP_TOKEN 에 넣는다.
 */
const FOLDER_NAME = 'awesomekorean-backups';
const NAME_RE = /^db_\d{8}_\d{6}\.sql\.gz$/;
const MIN_BYTES = 200;

function doGet() { return text_('ok - backup receiver'); }

function doPost(e) {
  try {
    const token = PropertiesService.getScriptProperties().getProperty('BACKUP_TOKEN');
    const p = e.parameter || {};
    if (!token || p.token !== token) return text_('forbidden');
    const name = String(p.name || '');
    if (!NAME_RE.test(name)) return text_('bad name');
    const body = e.postData && e.postData.contents ? e.postData.contents : '';
    if (!body) return text_('empty');

    const bytes = Utilities.base64Decode(body);
    if (bytes.length < MIN_BYTES) return text_('too small');
    if ((bytes[0] & 0xff) !== 0x1f || (bytes[1] & 0xff) !== 0x8b) return text_('not gzip');   // gzip 파일 머리 확인

    const folder = folder_();
    const file = folder.createFile(Utilities.newBlob(bytes, 'application/gzip', name));
    if (file.getSize() < MIN_BYTES) { file.setTrashed(true); return text_('save failed'); }

    // 새 백업이 저장된 것을 확인한 뒤에만, 그 전 백업을 휴지통으로
    let trashed = 0;
    const it = folder.getFiles();
    while (it.hasNext()) {
      const f = it.next();
      if (f.getId() !== file.getId() && NAME_RE.test(f.getName())) { f.setTrashed(true); trashed++; }
    }
    return text_('ok ' + name + ' ' + file.getSize() + ' bytes, trashed ' + trashed);
  } catch (err) {
    return text_('error ' + String(err).slice(0, 200));
  }
}

function folder_() {
  const it = DriveApp.getFoldersByName(FOLDER_NAME);
  return it.hasNext() ? it.next() : DriveApp.createFolder(FOLDER_NAME);
}
function text_(s) { return ContentService.createTextOutput(s); }
