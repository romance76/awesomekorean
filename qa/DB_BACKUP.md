# DB 자동 백업 · 복구 연습

## 지금 돌아가는 것
- 매일 **새벽 03:50 (뉴욕 시간)** 에 `php artisan db:backup` 이 자동으로 실행됩니다 (`routes/console.php`).
- 결과 파일: 서버의 `/var/www/awesomekorean/storage/app/backups/db_날짜_시각.sql.gz` (권한 600, 같은 서버 안)
- 7일 지난 백업은 자동으로 지웁니다 (최근 3개는 항상 남김). 디스크 여유가 1GB 미만이면 백업을 건너뛰고 로그에 남깁니다.
- 로그: `storage/logs/db-backup.log` (성공/실패 모두 기록)
- 백업은 **임시 이름(.part)으로 만든 뒤** gzip 이 온전한지, 덤프 끝에 `Dump completed` 표시가 있는지 확인하고 나서야 정식 이름이 됩니다. 중간에 끊긴 백업이 정상처럼 남지 않습니다.
- DB 비밀번호는 명령줄에 나오지 않고 환경변수로만 전달됩니다. DB 는 읽기만 합니다.

## ⚠️ 같은 서버 안의 백업만으로는 부족합니다
서버가 망가지거나 계정이 해킹되면 같은 서버의 백업도 같이 사라집니다. **서버 밖 사본**이 있어야 진짜 백업입니다.
서버의 `.env` 에 한 줄을 추가하면, 백업이 끝날 때마다 그 명령을 실행합니다 (`{file}` 자리에 백업 파일 경로가 들어갑니다).

```
BACKUP_UPLOAD_CMD="rclone copy {file} remote:awesomekorean-backups/"
```

- `rclone` 은 Google Drive · Backblaze B2 · AWS S3 등 거의 모든 저장소에 올릴 수 있는 도구입니다. 서버에 설치하고 `rclone config` 로 저장소를 한 번 연결해 두면 됩니다.
- 어떤 저장소를 쓸지는 직접 정하셔야 합니다 (계정·결제가 필요). 비용은 DB 크기가 작으면 월 $1 안팎(B2/S3)이거나 무료(Google Drive)입니다.
- 설정 전에는 백업할 때마다 "서버 밖 사본은 만들지 않았어요" 경고가 로그에 남습니다.
- 개인정보가 들어 있으니 올리는 저장소는 비공개로 두세요. 필요하면 `rclone crypt` 로 암호화해서 올릴 수도 있습니다.

## 확인하는 법
```bash
cd /var/www/awesomekorean
sudo -u www-data php8.2 artisan db:backup            # 지금 바로 한 번 백업
sudo -u www-data php8.2 artisan db:backup --verify   # 가장 최근 백업이 온전한지 확인
ls -lh storage/app/backups/
tail -n 30 storage/logs/db-backup.log
```

## 복구 연습 (한 달에 한 번 권장)
백업이 있어도 **되살려 본 적이 없으면 없는 것과 같습니다.**
```bash
sudo -u www-data php8.2 artisan db:restore-check
```
- 가장 최근 백업을 `<DB이름>_restorecheck` 라는 **임시 DB** 에 되살려서 표 개수를 운영 DB 와 비교하고, 끝나면 임시 DB 를 지웁니다.
- 운영 DB 는 건드리지 않습니다(표 개수만 읽음). DB 사용자에게 `CREATE`/`DROP DATABASE` 권한이 없으면 아무것도 하지 않고 안내만 합니다 (이때는 아래 "수동 복구" 로 연습).
- 오래 걸리고 디스크를 쓰므로 자동 일정에는 넣지 않았습니다. 이용자가 적은 새벽에 실행하세요.

## 진짜로 복구해야 할 때 (수동)
```bash
# 1) 사이트를 잠시 점검 모드로: 관리자 > 시스템 > 점검 모드 켜기 (또는 php artisan down)
# 2) 되살리기 전에, 지금 상태도 한 번 더 백업해 두기
sudo -u www-data php8.2 artisan db:backup
# 3) 되살리기 (운영 DB 이름이 awesomekorean 인 경우) — 기존 표를 덮어씁니다
zcat storage/app/backups/db_20261014_035000.sql.gz | mysql -u 사용자 -p awesomekorean
# 4) 점검 모드 끄기, 사이트에서 글·회원이 보이는지 확인
```
서버 밖 사본에서 되살릴 때는 먼저 그 파일을 서버로 내려받은 뒤 3번부터 똑같이 합니다.

## DB 사용자 권한 참고
`mysqldump` 는 `SELECT`, `LOCK TABLES`(생략 가능), `SHOW VIEW`, `TRIGGER`, `EVENT` 권한이 있으면 전부 받습니다. 일부 권한이 없으면 루틴·이벤트·트리거를 뺀 최소 백업으로 자동 재시도하고, 그 사실을 로그에 남깁니다(표 데이터는 전부 들어갑니다).
`.env` 값은 `php artisan config:cache` 를 쓰지 않는 현재 방식에서 바로 반영됩니다 (config:cache 를 쓰게 되면 `BACKUP_UPLOAD_CMD` 를 config 파일로 옮겨야 합니다).


## 구글 드라이브로 서버 밖 백업 + "새 백업이 올라가면 이전 것은 지우기" (Kay 결정 2026-10-19)
구글 드라이브(약 500GB 사용 가능)에 올리고, 새 백업이 올라간 뒤에는 그 전 백업을 지웁니다. DB 가 작아 용량은 문제없습니다.

### 동작 (코드는 배포됨 — `app/Console/Commands/DbBackup.php`)
1. 새 백업을 만들고 끝까지 만들어졌는지 확인(gzip 검사 + 덤프 끝 표시).
2. `BACKUP_UPLOAD_CMD` 로 서버 밖에 올림. **실패하면 여기서 끝나고 이전 백업은 그대로 남음.**
3. 올리기가 성공했을 때만, `BACKUP_PRUNE_AFTER_UPLOAD=true` 이면
   - 서버 밖의 이전 백업을 `BACKUP_REMOTE_PRUNE_CMD` 로 지우고 (`{name}` = 방금 올린 파일 이름),
   - 서버 안의 이전 백업도 지움(방금 만든 1개만 남김).
- 켜지 않으면(기본) 예전처럼 7일 보관·최근 3개 유지입니다.
- ⚠️ 이전 것을 지우므로 **항상 최신 백업 1개만** 남습니다. 오늘 백업이 깨진 줄 모르고 지나가면 되돌아갈 곳이 없으니, 확인 명령(`db:backup --verify`, 한 달에 한 번 `db:restore-check`)을 꼭 해 두세요. 불안하면 서버 밖 정리 명령의 `--min-age 12h` 를 `--min-age 60h` 로 바꾸면 2~3일치가 남습니다.

### 설정 순서 (서버 접속이 필요한 부분은 서버 접속 권한이 있는 사람이 한 번만)
1. **내 컴퓨터**에 rclone 설치(rclone.org/downloads) 후 터미널에서 `rclone authorize "drive"` 실행 → 브라우저가 열리면 백업용으로 쓸 구글 계정으로 로그인·허용 → 터미널에 나오는 `{"access_token":...}` 한 줄을 복사. (이 줄은 비밀번호와 같으니 채팅·메일에 붙이지 말 것)
2. **서버**에 rclone 설치(`sudo apt install rclone` 또는 rclone.org 안내) 후 `rclone config`: `n`(새 저장소) → 이름 `gdrive` → 종류 `drive` → client id/secret 은 엔터 → **scope 는 `drive.file`**(rclone 이 만든 파일만 접근, 드라이브 전체는 못 봄) → "Use auto config?" 는 `n` → 1번의 한 줄 붙여넣기 → 나머지는 엔터.
3. (권장) 개인정보가 들어 있으니 암호화: `rclone config` 에서 `n` → 이름 `gdrivecrypt` → 종류 `crypt` → 저장 위치 `gdrive:awesomekorean-backups` → 비밀번호 설정(**꼭 따로 적어 보관** — 잃으면 백업을 못 엽니다).
4. 서버 `.env` 에 추가(암호화하지 않으면 `gdrivecrypt` 를 `gdrive` 로):
```
BACKUP_UPLOAD_CMD="rclone copy {file} gdrivecrypt:awesomekorean-backups/"
BACKUP_PRUNE_AFTER_UPLOAD=true
BACKUP_REMOTE_PRUNE_CMD="rclone lsf gdrivecrypt:awesomekorean-backups/ --include {name} | grep -q . && rclone delete gdrivecrypt:awesomekorean-backups/ --include 'db_*.sql.gz' --min-age 12h"
```
   서버 밖 정리는 **방금 올린 파일이 서버 밖에 실제로 보일 때만**(`rclone lsf … | grep -q .`) 실행됩니다.
5. 시험: 서버에서 `sudo -u www-data php8.2 artisan db:backup` 을 한 번 실행 → 드라이브에 파일이 생겼는지, 서버·드라이브에 새 파일 1개만 남았는지 확인. 그다음 `db:restore-check`(복구 연습)도 한 번.


## 웹 브라우저만으로 하는 방법 — 구글 Apps Script (rclone·명령어 설치 불필요)
구글 쪽은 전부 브라우저에서 하고, 서버 쪽은 `.env` 한 줄만 넣으면 됩니다. 받는 코드는 `qa/tools/gdrive_backup_receiver.gs`.
- 서버가 백업 파일을 이 웹 앱 주소로 보냄 → 내 드라이브 `awesomekorean-backups` 폴더에 저장 → 저장이 확인되면 **그 전 백업은 휴지통으로**(30일 뒤 자동 삭제, 그 안에는 복구 가능).
- 서버에 구글 로그인 정보(토큰)가 남지 않습니다. 서버가 아는 것은 "웹 앱 주소 + 비밀 토큰"뿐이고, 이것으로는 **올리기만** 되고 내 드라이브를 읽을 수는 없습니다.
- ⚠️ 한계(실제로 해 보고 확인 필요): 한 번에 보낼 수 있는 크기에 구글 쪽 제한이 있습니다(대략 수십 MB). 백업이 그보다 커지면 실패하므로 첫 실행 결과를 꼭 확인하세요. 파일은 암호화하지 않고 내 구글 드라이브(비공개)에 저장됩니다.

### 구글 쪽 (브라우저)
1. script.google.com → **새 프로젝트** → 이름 `awesomekorean-backup-receiver`.
2. 기본 `Code.gs` 내용을 지우고 `qa/tools/gdrive_backup_receiver.gs` 내용을 통째로 붙여넣기 → 저장.
3. 왼쪽 **프로젝트 설정(톱니바퀴) → 스크립트 속성 → 스크립트 속성 추가**: 속성 `BACKUP_TOKEN`, 값은 **영문·숫자 30자 이상 아무거나**(직접 만든 긴 비밀번호). 저장. (이 값은 아래 `.env` 에도 똑같이 넣습니다. 채팅·메일에 쓰지 마세요.)
4. 오른쪽 위 **배포 → 새 배포 → 유형: 웹 앱**: 실행 사용자 **나**, 액세스 권한 **모든 사용자** → 배포. 드라이브 접근 허용 창이 뜨면 **고급 → 허용**(내 계정으로 폴더를 만들고 파일을 저장하는 권한).
5. 나오는 **웹 앱 URL**(`https://script.google.com/macros/s/…/exec`)을 복사.

### 서버 쪽 (브라우저로 서버에 들어가기)
- DigitalOcean 사이트 → Droplets → 해당 서버 → **Access → Launch Droplet Console** (브라우저 안의 검은 터미널 창).
- `.env` 를 엽니다: `nano /var/www/awesomekorean/.env` → 맨 아래에 아래 두 줄을 넣고(URL·TOKEN 자리 교체) `Ctrl+O` 엔터, `Ctrl+X`:
```
BACKUP_UPLOAD_CMD="base64 -w0 {file} | curl -sS -L --fail --max-time 900 --data-binary @- -H 'Content-Type: text/plain' 'https://script.google.com/macros/s/여기에-웹앱-주소/exec?token=여기에-토큰&name='{name} | grep -q '^ok'"
BACKUP_PRUNE_AFTER_UPLOAD=true
```
  (서버 밖의 이전 백업 정리는 구글 쪽 코드가 하므로 `BACKUP_REMOTE_PRUNE_CMD` 는 필요 없습니다. 서버 안의 이전 백업은 `BACKUP_PRUNE_AFTER_UPLOAD=true` 가 지웁니다.)
- 첫 백업: `cd /var/www/awesomekorean && sudo -u www-data php8.2 artisan db:backup` → "서버 밖 복사 완료" 가 나오고, 드라이브 `awesomekorean-backups` 폴더에 `db_날짜_시각.sql.gz` 가 생기면 성공. 이 폴더에는 항상 **가장 최근 1개만** 보입니다(이전 것은 휴지통).
- 복구 확인(한 달에 한 번): `sudo -u www-data php8.2 artisan db:restore-check`.
