<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * 복구 연습 — 가장 최근 백업을 "임시 DB"에 실제로 되살려 보고 표 개수를 운영 DB 와 비교한 뒤, 임시 DB 를 지운다.
 *
 *  - 운영 DB 는 읽기만 한다(표 개수 세기). 되살리는 곳은 항상 "<운영DB이름>_restorecheck" 라는 별도 DB 하나뿐이고,
 *    이름이 운영 DB 와 같으면 시작하지 않는다.
 *  - DB 사용자에게 CREATE/DROP DATABASE 권한이 없으면 아무것도 하지 않고 안내만 한다.
 *  - 오래 걸리고 디스크를 쓰므로 자동 일정에는 넣지 않았다. 한 달에 한 번쯤 새벽에 직접 실행한다.
 *
 * 사용: php artisan db:restore-check
 */
class DbRestoreCheck extends Command
{
    protected $signature = 'db:restore-check {--file= : 되살릴 백업 파일(비우면 가장 최근)} {--keep : 확인 후에도 임시 DB 를 지우지 않는다}';
    protected $description = '최근 DB 백업을 임시 DB 에 되살려 보고 표 개수를 비교(복구 연습)';

    public function handle(): int
    {
        $cfg = config('database.connections.' . config('database.default'));
        if (!is_array($cfg) || !in_array($cfg['driver'] ?? '', ['mysql', 'mariadb'], true)) return $this->bad('MySQL/MariaDB 가 아니에요');

        $main = (string) ($cfg['database'] ?? '');
        $tmp = $main . '_restorecheck';
        if ($main === '' || !preg_match('/^[A-Za-z0-9_]+$/', $tmp) || $tmp === $main) return $this->bad('임시 DB 이름이 안전하지 않아 중단했어요');

        $file = (string) $this->option('file') ?: (DbBackup::latest(storage_path('app/backups')) ?? '');
        if ($file === '' || !is_file($file)) return $this->bad('되살릴 백업 파일이 없어요 (먼저 php artisan db:backup)');
        $this->line('백업 파일: ' . basename($file) . ' (' . round(filesize($file) / 1048576, 2) . 'MB)');

        $conn = !empty($cfg['unix_socket'])
            ? '--socket=' . escapeshellarg($cfg['unix_socket'])
            : '-h ' . escapeshellarg($cfg['host'] ?? '127.0.0.1') . ' -P ' . escapeshellarg((string) ($cfg['port'] ?? 3306));
        $mysql = 'mysql ' . $conn . ' -u ' . escapeshellarg($cfg['username'] ?? '');
        $env = ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')];
        $run = function (string $cmd, int $timeout = 60) use ($env) {
            $p = new Process(['bash', '-o', 'pipefail', '-c', $cmd], null, $env, null, $timeout);
            $p->run();
            return $p;
        };

        // 1) 임시 DB 만들기 (권한이 없으면 여기서 멈춤)
        $c = $run($mysql . ' -e ' . escapeshellarg("DROP DATABASE IF EXISTS `{$tmp}`; CREATE DATABASE `{$tmp}` CHARACTER SET utf8mb4"));
        if (!$c->isSuccessful()) return $this->bad("임시 DB 를 만들 수 없어요(권한 부족일 수 있어요): " . mb_substr(trim($c->getErrorOutput()), -300));

        $ok = false;
        try {
            // 2) 되살리기
            $r = $run('gzip -dc ' . escapeshellarg($file) . ' | ' . $mysql . ' ' . escapeshellarg($tmp), 3600);
            if (!$r->isSuccessful()) return $this->bad('되살리기 실패: ' . mb_substr(trim($r->getErrorOutput()), -300));

            // 3) 표 개수 비교 (운영 DB 는 읽기만)
            $count = fn (string $schema) => (int) trim($run($mysql . ' -N -e ' . escapeshellarg("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$schema}' AND table_type = 'BASE TABLE'"))->getOutput());
            $live = $count($main);
            $restored = $count($tmp);
            $this->line("운영 DB 표 {$live}개 / 되살린 DB 표 {$restored}개");
            if ($restored === 0) return $this->bad('되살린 DB 에 표가 하나도 없어요');
            if ($restored < $live) $this->warn('되살린 표가 운영보다 적어요 — 백업 이후 새 표가 생겼을 수 있어요(내일 백업과 다시 비교).');
            $this->info('복구 연습 성공: 백업을 실제로 되살릴 수 있어요');
            Log::info('[db:restore-check] ok', ['file' => basename($file), 'live_tables' => $live, 'restored_tables' => $restored]);
            $ok = true;
        } finally {
            if (!$this->option('keep')) {
                $run($mysql . ' -e ' . escapeshellarg("DROP DATABASE IF EXISTS `{$tmp}`"));
                $this->line('임시 DB 정리 완료');
            }
        }
        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function bad(string $msg): int
    {
        $this->error($msg);
        Log::error('[db:restore-check] ' . $msg);
        return self::FAILURE;
    }
}
