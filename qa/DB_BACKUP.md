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
