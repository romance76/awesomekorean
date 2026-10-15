<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// 옛 사이트 이름(somekorean / SomeKorean / 썸코리안)을 어썸코리안(awesomekorean)으로 바꾼다 — DB의 모든 글자 칸.
// - 이미 'awesomekorean' 인 것은 건드리지 않는다 ('awe' 바로 뒤의 somekorean 제외, '어썸코리안' 도 제외).
// - 건너뜀: 로그인 세션·큐·캐시 표, 비밀번호/토큰 칸, PHP 직렬화 값(길이가 어긋나 깨짐), 실제 회원 이메일(users.email 은 @somekorean.local 가상 계정만 바꿈).
// - 고유값(이메일 등)이 이미 있어 충돌하면 그 줄만 건너뛰고 로그에 남긴다. 되돌릴 수 없음(down 없음).
return new class extends Migration
{
    private const SKIP_TABLES = ['migrations', 'jobs', 'failed_jobs', 'job_batches', 'sessions', 'cache', 'cache_locks', 'password_reset_tokens', 'personal_access_tokens'];
    private const SKIP_COLUMN_PATTERN = '/(password|token|secret|remember|api_key|signature)/i';

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        $db = DB::getDatabaseName();

        $cols = DB::select(
            "SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext','json')",
            [$db]
        );
        $hasId = [];
        foreach (DB::select("SELECT TABLE_NAME t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = 'id'", [$db]) as $r) $hasId[$r->t] = true;

        $total = 0;
        foreach ($cols as $col) {
            $t = $col->t; $c = $col->c;
            if (in_array($t, self::SKIP_TABLES, true) || preg_match(self::SKIP_COLUMN_PATTERN, $c) || empty($hasId[$t])) continue;
            $total += $this->fixColumn($t, $c);
        }
        Log::info("[rename somekorean→awesomekorean] 바뀐 칸 {$total}개");
        \Illuminate\Support\Facades\Cache::forget('hero_banners_public_v2');
    }

    private function fixColumn(string $t, string $c): int
    {
        $changed = 0;
        DB::table($t)
            ->where(fn ($q) => $q->where($c, 'like', '%somekorean%')->orWhere($c, 'like', '%썸코리안%'))
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($t, $c, &$changed) {
                foreach ($rows as $row) {
                    $old = $row->{$c};
                    if (!is_string($old) || preg_match('/^[aOsCib]:\d+[:;]/', $old)) continue; // PHP 직렬화 값은 건너뜀
                    $new = self::rename($old);
                    if ($new === $old) continue;
                    // 실제 회원 이메일은 그대로 (가상 계정 @somekorean.local 만 변경)
                    if ($t === 'users' && $c === 'email' && !preg_match('/@somekorean\.local$/i', $old)) continue;
                    try {
                        DB::table($t)->where('id', $row->id)->update([$c => $new]);
                        $changed++;
                    } catch (\Throwable $e) {
                        Log::warning("[rename somekorean] {$t}.{$c} #{$row->id} 건너뜀: " . $e->getMessage());
                    }
                }
            });
        return $changed;
    }

    public static function rename(string $s): string
    {
        $s = preg_replace('/(?<![Aa][Ww][Ee])somekorean/u', 'awesomekorean', $s);
        $s = preg_replace('/(?<![Aa][Ww][Ee])SomeKorean/u', 'AwesomeKorean', $s);
        $s = preg_replace('/(?<![Aa][Ww][Ee])SOMEKOREAN/u', 'AWESOMEKOREAN', $s);
        $s = preg_replace('/(?<!어)썸코리안/u', '어썸코리안', $s);
        return $s;
    }
};
