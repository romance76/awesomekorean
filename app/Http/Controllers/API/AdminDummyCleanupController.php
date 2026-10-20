<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * 시험(더미) 계정 정리 — 1단계: 읽기 전용 미리보기.
 *
 * 대상: 이메일이 @test.com 으로 끝나는 계정. 단 test@test.com(Kay 본인 최고관리자, id 202)·최고관리자·이미 지운 계정(deleted_)·지금 로그인한 관리자는 뺀다.
 * 이 컨트롤러는 아무것도 바꾸지 않는다. 계정이 어떤 표에 몇 줄의 기록을 갖고 있는지만 센다.
 * (실제 삭제는 이 숫자를 Kay 가 확인한 뒤에 따로 만든다.)
 */
class AdminDummyCleanupController extends Controller
{
    private const KEEP_EMAIL = 'test@test.com';

    /** 정리 대상 계정 번호 */
    private function targets(): \Illuminate\Support\Collection
    {
        return DB::table('users')
            ->where('email', 'like', '%@test.com')
            ->where('email', 'not like', 'deleted\_%')
            ->where('email', '!=', self::KEEP_EMAIL)
            ->where('role', '!=', 'super_admin')
            ->where('id', '!=', auth()->id())
            ->orderBy('id')
            ->get(['id', 'email', 'role', 'name', 'points']);
    }

    public function preview()
    {
        $users = $this->targets();
        $ids = $users->pluck('id')->all();
        if (!$ids) return response()->json(['success' => true, 'data' => ['accounts' => 0, 'tables' => []]]);

        $db = DB::getDatabaseName();
        $cols = DB::select(
            "SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND (COLUMN_NAME = 'user_id' OR COLUMN_NAME REGEXP '^(author|owner|sender|receiver|requester|reporter|friend|from_user|to_user|target_user|created_by)_id$|^(from|to)_user$')
             ORDER BY TABLE_NAME", [$db]);

        $tables = [];
        foreach ($cols as $row) {
            if (in_array($row->t, ['users', 'migrations', 'sessions', 'personal_access_tokens'], true)) continue;
            try {
                $n = DB::table($row->t)->whereIn($row->c, $ids)->count();
            } catch (\Throwable $e) { continue; }
            if ($n > 0) $tables[] = ['table' => $row->t, 'column' => $row->c, 'rows' => $n];
        }
        usort($tables, fn ($a, $b) => $b['rows'] <=> $a['rows']);

        // 다른 회원이 이 계정들의 글에 단 댓글(글을 지우면 같이 사라질 것) — 글을 지울 때 고아가 생기는지 보려는 숫자
        $others = [];
        if (DB::getSchemaBuilder()->hasTable('comments') && DB::getSchemaBuilder()->hasColumn('comments', 'commentable_type')) {
            foreach (DB::table('comments')->whereIn('user_id', $ids)->select('commentable_type', DB::raw('COUNT(*) n'))->groupBy('commentable_type')->get() as $r) {
                $others[] = ['comment_target' => class_basename((string) $r->commentable_type), 'by_dummy' => (int) $r->n];
            }
        }

        $byRole = $users->groupBy('role')->map->count();
        return response()->json(['success' => true, 'data' => [
            'accounts' => count($ids),
            'by_role' => $byRole,
            'kept' => self::KEEP_EMAIL,
            'sample' => $users->take(5)->map(fn ($u) => $u->id . ' ' . $u->email . ' (' . $u->role . ')')->values(),
            'tables' => $tables,
            'dummy_comments_by_target' => $others,
        ]]);
    }
}
