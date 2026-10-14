<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * DB 자동 백업 — 매일 한 번 mysqldump 를 gzip 으로 묶어 storage/app/backups 에 두고, 오래된 것은 지운다.
 *
 *  - 덤프는 임시 이름(.part)으로 만들고, 끝까지 만들어졌는지(gzip 검사 + 덤프 끝 표시) 확인한 뒤에만 정식 이름으로 바꾼다.
 *    → 중간에 끊긴 불완전한 백업이 "정상 백업"처럼 남지 않는다.
 *  - DB 비밀번호는 명령줄이 아니라 환경변수(MYSQL_PWD)로만 넘긴다 (ps 로 보이지 않게).
 *  - 서버 밖 보관: .env 에 BACKUP_UPLOAD_CMD 를 적으면 백업이 끝난 뒤 그 명령을 실행한다. {file} 자리에 백업 파일 경로가 들어간다.
 *      예) BACKUP_UPLOAD_CMD="rclone copy {file} remote:awesomekorean-backups/"
 *    (같은 서버 안의 백업은 서버가 망가지면 같이 사라지므로, 서버 밖 사본이 있어야 진짜 백업이다.)
 *  - 읽기만 한다: DB 를 바꾸거나 지우지 않는다.
 *
 * 사용: php artisan db:backup            (백업 만들기)
 *       php artisan db:backup --verify   (가장 최근 백업이 온전한지만 확인)
 * 복구 연습: php artisan db:restore-check  / 사용법은 qa/DB_BACKUP.md
 */
class DbBackup extends Command
{
    protected $signature = 'db:backup {--keep=7 : 이 일수보다 오래된 백업은 지운다(최근 3개는 항상 남김)} {--verify : 만들지 않고 가장 최근 백업만 확인}';
    protected $description = 'DB 를 gzip 으로 백업(storage/app/backups), 오래된 백업 정리, 선택적으로 서버 밖 복사';

    private const MIN_KEEP_FILES = 3;
    private const MIN_FREE_BYTES = 1073741824; // 1GB — 서버가 작아서 디스크가 가득 차지 않게 막는다

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) return $this->bad("백업 폴더를 만들 수 없어요: {$dir}");

        if ($this->option('verify')) {
            $latest = self::latest($dir);
            if (!$latest) return $this->bad('확인할 백업이 없어요');
            return $this->verifyFile($latest) ? self::SUCCESS : self::FAILURE;
        }

        $cfg = config('database.connections.' . config('database.default'));
        if (!is_array($cfg) || !in_array($cfg['driver'] ?? '', ['mysql', 'mariadb'], true)) {
            return $this->bad('MySQL/MariaDB 가 아닌 DB 라서 백업하지 않았어요');
        }

        $free = @disk_free_space($dir);
        if ($free !== false && $free < self::MIN_FREE_BYTES) {
            return $this->bad('디스크 여유가 1GB 미만이라 백업을 건너뛰었어요(' . round($free / 1048576) . 'MB 남음)');
        }

        $final = $dir . '/db_' . now()->format('Ymd_His') . '.sql.gz';
        $part = $final . '.part';
        $p = null;
        foreach ([true, false] as $full) {
            $p = new Process(['bash', '-o', 'pipefail', '-c', self::dumpScript($cfg, $part, $full)], null, ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')], null, 1800);
            $p->run();
            if ($p->isSuccessful()) { if (!$full) $this->warn('루틴·이벤트·트리거 권한이 없어 그것들만 빼고 백업했어요(표 데이터는 전부 들어 있어요)'); break; }
            @unlink($part);
            // 권한 문제일 때만 최소 덤프로 한 번 더 시도, 그 밖의 오류(연결 실패 등)는 바로 중단
            if ($full && !str_contains($p->getErrorOutput(), 'Access denied')) break;
        }
        if (!$p->isSuccessful()) {
            @unlink($part);
            return $this->bad('mysqldump 실패: ' . self::tail($p->getErrorOutput() ?: $p->getOutput()));
        }
        if (!$this->verifyFile($part)) { @unlink($part); return self::FAILURE; }
        if (!@rename($part, $final)) { @unlink($part); return $this->bad('백업 파일 이름을 바꾸지 못했어요'); }
        @chmod($final, 0600);
        $mb = round(filesize($final) / 1048576, 2);
        $this->info("백업 완료: {$final} ({$mb}MB)");
        Log::info('[db:backup] ok', ['file' => basename($final), 'mb' => $mb]);

        // 오래된 백업 정리
        foreach (self::pickToDelete(glob($dir . '/db_*.sql.gz') ?: [], (int) $this->option('keep'), time()) as $old) {
            if (@unlink($old)) $this->line('오래된 백업 삭제: ' . basename($old));
        }

        // 서버 밖 사본 (선택)
        $upload = trim((string) env('BACKUP_UPLOAD_CMD', ''));
        if ($upload !== '') {
            $u = new Process(['bash', '-c', str_replace('{file}', escapeshellarg($final), $upload)], null, null, null, 1800);
            $u->run();
            if (!$u->isSuccessful()) return $this->bad('서버 밖 복사 실패: ' . self::tail($u->getErrorOutput() ?: $u->getOutput()));
            $this->info('서버 밖 복사 완료');
        } else {
            $this->warn('BACKUP_UPLOAD_CMD 가 비어 있어 서버 밖 사본은 만들지 않았어요 (qa/DB_BACKUP.md 참고)');
        }
        return self::SUCCESS;
    }

    /** mysqldump | gzip 을 한 줄로. 비밀번호는 넣지 않는다(환경변수 MYSQL_PWD 로 전달). */
    public static function dumpScript(array $cfg, string $outFile, bool $full = true): string
    {
        $a = fn ($v) => escapeshellarg((string) $v);
        $conn = !empty($cfg['unix_socket'])
            ? '--socket=' . $a($cfg['unix_socket'])
            : '-h ' . $a($cfg['host'] ?? '127.0.0.1') . ' -P ' . $a($cfg['port'] ?? 3306);
        // $full=false: DB 사용자에게 EVENT/TRIGGER/루틴 조회 권한이 없을 때를 위한 최소 덤프(표 데이터와 구조는 그대로)
        $extras = $full ? '--routines --triggers --events' : '--skip-triggers';
        return 'nice -n 19 mysqldump --single-transaction --quick ' . $extras . ' --no-tablespaces --default-character-set=utf8mb4 '
            . $conn . ' -u ' . $a($cfg['username'] ?? '') . ' ' . $a($cfg['database'] ?? '')
            . ' | nice -n 19 gzip -c > ' . $a($outFile);
    }

    /** 지울 백업 목록: 보관 일수보다 오래됐고, 최신 MIN_KEEP_FILES 개 밖에 있는 것만. */
    public static function pickToDelete(array $files, int $keepDays, int $now): array
    {
        rsort($files, SORT_STRING); // 이름에 날짜·시각이 있어 문자열 역순 = 최신순
        $out = [];
        foreach (array_slice($files, self::MIN_KEEP_FILES) as $f) {
            $m = @filemtime($f);
            if ($m !== false && $m < $now - max(1, $keepDays) * 86400) $out[] = $f;
        }
        return $out;
    }

    public static function latest(string $dir): ?string
    {
        $files = glob($dir . '/db_*.sql.gz') ?: [];
        rsort($files, SORT_STRING);
        return $files[0] ?? null;
    }

    /** gzip 이 온전하고, 덤프 맨 끝의 "Dump completed" 표시가 있는지 */
    private function verifyFile(string $file): bool
    {
        if (!is_file($file) || filesize($file) < 200) { $this->bad('백업 파일이 없거나 너무 작아요: ' . basename($file)); return false; }
        $gz = new Process(['gzip', '-t', $file]);
        $gz->run();
        if (!$gz->isSuccessful()) { $this->bad('gzip 검사 실패(파일이 깨졌어요): ' . basename($file)); return false; }
        $t = new Process(['bash', '-o', 'pipefail', '-c', 'gzip -dc ' . escapeshellarg($file) . ' | tail -n 5']);
        $t->run();
        if (!$t->isSuccessful() || !str_contains($t->getOutput(), 'Dump completed')) {
            $this->bad('덤프가 끝까지 만들어지지 않았어요(끝 표시 없음): ' . basename($file));
            return false;
        }
        $this->info('백업 확인 OK: ' . basename($file) . ' (' . round(filesize($file) / 1048576, 2) . 'MB)');
        return true;
    }

    private function bad(string $msg): int
    {
        $this->error($msg);
        Log::error('[db:backup] ' . $msg);
        return self::FAILURE;
    }

    private static function tail(string $s): string
    {
        $s = trim($s);
        return mb_substr($s, -400);
    }
}
