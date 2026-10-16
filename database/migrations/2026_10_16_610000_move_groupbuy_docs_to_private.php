<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

// 사업자등록증을 비공개 디스크로 옮긴다 (2026-10-16).
//  1) 공개 디스크(/storage 주소로 누구나 열 수 있음)에 있는 공동구매 서류를 비공개 디스크로 옮기고 공개 쪽 파일은 지운다.
//  2) 시험 중 지운 글에 남아 있던 서류 파일(주소를 아는 사람은 열 수 있었음)을 지운다.
// 이미 옮겨졌거나 파일이 없으면 건너뛴다(여러 번 돌려도 안전). DB 의 경로 값은 바꾸지 않는다.
return new class extends Migration
{
    private const ORPHAN = 'groupbuys/docs/4YLiThJfBNwsLjjXmzD9l0qcGfnsqt0sixMRTj5b.png';

    public function up(): void
    {
        $public = Storage::disk('public');
        $local = Storage::disk('local');

        if (Schema::hasTable('group_buys') && Schema::hasColumn('group_buys', 'business_doc')) {
            foreach (DB::table('group_buys')->whereNotNull('business_doc')->pluck('business_doc') as $raw) {
                $path = ltrim(preg_replace('#^/storage/#', '', (string) $raw), '/');
                if ($path === '' || !$public->exists($path)) continue;
                if (!$local->exists($path)) {
                    $local->put($path, $public->get($path));
                }
                if ($local->exists($path)) $public->delete($path);   // 옮겨진 것이 확인될 때만 공개 쪽을 지운다
            }
        }

        $stillUsed = Schema::hasTable('group_buys') && Schema::hasColumn('group_buys', 'business_doc')
            && DB::table('group_buys')->where('business_doc', self::ORPHAN)->exists();
        if (!$stillUsed) {
            $public->delete(self::ORPHAN);
        }
    }

    public function down(): void
    {
        // 서류를 다시 공개로 되돌리지 않는다.
    }
};
