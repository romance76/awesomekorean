#!/bin/bash
# 외국어(일본어/러시아어/인도어/인도네시아어/터키어 등) 숏츠 41건 삭제 — Kay 승인 2026-10-08
# 서버 백업 후 id + user_id IS NULL 조건으로만 삭제. 다른 테이블/행은 건드리지 않음.
cd "$(dirname "$0")/../.." || exit 1
IDS='2555,3007,6443,6458,6557,6604,6607,6668,6680,6681,6682,6683,6684,6697,6731,6801,6815,6818,6821,6913,6922,7002,7434,7436,7437,7438,7503,7522,7532,7706,7711,7804,7818,7896,7917,7970,7985,8235,8257,8262,8278'

ssh -o BatchMode=yes root@68.183.60.70 "IDS='$IDS' bash -s" <<'REMOTE'
cd /var/www/awesomekorean || exit 1
U=$(grep ^DB_USERNAME .env | cut -d= -f2)
P=$(grep ^DB_PASSWORD .env | cut -d= -f2)
DB=$(grep ^DB_DATABASE .env | cut -d= -f2)
mkdir -p storage/backups
F=storage/backups/shorts_foreign_removed_20261008.sql
mysqldump -u"$U" -p"$P" --no-create-info --skip-extended-insert --where="id IN ($IDS) AND user_id IS NULL" "$DB" shorts > "$F" 2>/dev/null
ROWS=$(grep -c '^INSERT' "$F")
echo "backup_rows=$ROWS"
if [ "$ROWS" -lt 1 ]; then echo "백업 실패 - 삭제 중단"; exit 1; fi
B=$(mysql -u"$U" -p"$P" "$DB" -N -e 'select count(*) from shorts' 2>/dev/null)
mysql -u"$U" -p"$P" "$DB" -e "delete from shorts where id in ($IDS) and user_id is null" 2>/dev/null
A=$(mysql -u"$U" -p"$P" "$DB" -N -e 'select count(*) from shorts' 2>/dev/null)
echo "before=$B after=$A"
REMOTE

scp -q root@68.183.60.70:/var/www/awesomekorean/storage/backups/shorts_foreign_removed_20261008.sql qa/backups/ && ls -la qa/backups/shorts_foreign_removed_20261008.sql
