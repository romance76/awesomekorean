<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 오픈 전 더미 구인·구직 글 정리 (Kay 2026-10-15: "구인구직에 올려 있는 건 다 더미 데이터")
 *
 * 대상: 가상(더미) 회원이 쓴 job_posts 전부 (구인 + 구직 글, 비활성 포함).
 * 가상 회원 = 이메일이 @test.com / @awesomekorean.local(옛 @somekorean.local) 이면서 관리자·슈퍼관리자가 아닌 계정
 *            (DummyCleanup 명령과 같은 기준).
 *
 * 안 지우는 것 (안전장치 — 실수로 진짜 기록을 지우지 않도록):
 *   - 관리자·슈퍼관리자가 쓴 글, 가상 회원이 아닌 실제 회원이 쓴 글
 *   - 상위노출(job_promotions) 기록이 있거나, 아직 유효한 상위노출이 걸려 있는 글 (결제와 연결됨)
 *   - 가상 회원이 아닌 회원이 지원(job_applications)한 글
 *   남은 글은 관리자 › 구인구직 화면에서 직접 확인할 수 있고, 개수는 storage/logs 에 남는다.
 *
 * 되돌리기: 지우기 전에 job_posts_dummy_archive_20261015 표에 글을 그대로 복사해 둔다
 *   (down() 이 글을 다시 넣어 준다. 댓글·북마크 같은 딸린 기록은 복원 대상이 아니다).
 *   그 밖에 매일 03:50(뉴욕) DB 자동 백업도 있다.
 *
 * 지우는 딸린 기록: 이 글들의 댓글(답글 포함)과 그 좋아요, 북마크, 좋아요, 신고. (지원서·상위노출은 외래키로 같이 지워짐)
 * 여러 번 실행해도 안전하다 (두 번째부터는 지울 글이 없어서 아무것도 안 함).
 */
return new class extends Migration
{
    private const ARCHIVE = 'job_posts_dummy_archive_20261015';
    private const DUMMY_DOMAINS = ['%@test.com', '%@awesomekorean.local', '%@somekorean.local'];
    /** 딸린 기록에서 이 글을 가리킬 수 있는 종류 이름 (클래스 이름 + 옛 짧은 이름) */
    private const TYPES = ['App\\Models\\JobPost', 'job', 'jobs', 'job_post'];

    public function up(): void
    {
        if (!Schema::hasTable('job_posts') || !Schema::hasTable('users')) {
            return;
        }

        $dummyUsers = DB::table('users')
            ->where(function ($q) {
                foreach (self::DUMMY_DOMAINS as $like) {
                    $q->orWhere('email', 'like', $like);
                }
            })
            ->whereNotIn('role', ['admin', 'super_admin'])
            ->pluck('id')->all();

        if (!$dummyUsers) {
            Log::info('[더미 구인구직 정리] 가상 회원이 없어 건너뜀');
            return;
        }

        // ── 지울 글 고르기 ──
        $candidates = [];
        foreach (array_chunk($dummyUsers, 500) as $chunk) {
            foreach (DB::table('job_posts')->whereIn('user_id', $chunk)->pluck('id') as $id) {
                $candidates[(int) $id] = true;
            }
        }
        $candidates = array_keys($candidates);
        if (!$candidates) {
            Log::info('[더미 구인구직 정리] 지울 글이 없음(이미 정리됨)');
            return;
        }

        $protected = [];
        foreach (array_chunk($candidates, 500) as $chunk) {
            // 상위노출 기록 (결제와 연결)
            if (Schema::hasTable('job_promotions')) {
                foreach (DB::table('job_promotions')->whereIn('job_post_id', $chunk)->pluck('job_post_id') as $id) {
                    $protected[(int) $id] = 'promotion';
                }
            }
            // 아직 유효한 상위노출
            foreach (DB::table('job_posts')->whereIn('id', $chunk)
                ->where('promotion_tier', '!=', 'none')->where('promotion_expires_at', '>', now())->pluck('id') as $id) {
                $protected[(int) $id] = 'active_promotion';
            }
            // 가상 회원이 아닌 회원이 지원한 글
            if (Schema::hasTable('job_applications')) {
                foreach (DB::table('job_applications')->whereIn('job_post_id', $chunk)
                    ->whereNotIn('user_id', $dummyUsers)->pluck('job_post_id') as $id) {
                    $protected[(int) $id] = 'real_applicant';
                }
            }
        }

        $ids = array_values(array_filter($candidates, fn ($id) => !isset($protected[$id])));
        if (!$ids) {
            Log::info('[더미 구인구직 정리] 지울 글이 없음(전부 보호 대상)', ['보호' => count($protected)]);
            return;
        }

        // ── 보관 표 만들기 (DDL 은 트랜잭션 밖에서) ──
        if (!Schema::hasTable(self::ARCHIVE)) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('CREATE TABLE `' . self::ARCHIVE . '` LIKE `job_posts`');
            } else {
                DB::statement('CREATE TABLE "' . self::ARCHIVE . '" AS SELECT * FROM job_posts WHERE 0');
            }
        }
        $ignore = DB::getDriverName() === 'mysql' ? 'INSERT IGNORE INTO' : 'INSERT OR IGNORE INTO';

        $deleted = 0;
        DB::transaction(function () use ($ids, $ignore, &$deleted) {
            foreach (array_chunk($ids, 300) as $chunk) {
                // 1) 보관 — 복사에 성공해야 아래 삭제로 넘어간다 (실패하면 전체 되돌림)
                $marks = implode(',', array_fill(0, count($chunk), '?'));
                DB::insert("$ignore " . self::ARCHIVE . " SELECT * FROM job_posts WHERE id IN ($marks)", $chunk);

                // 2) 딸린 기록 (표·칸이 없는 환경에서도 오류 없이 건너뜀)
                if ($this->has('comments', ['id', 'commentable_type', 'commentable_id'])) {
                    $cids = DB::table('comments')->whereIn('commentable_type', self::TYPES)->whereIn('commentable_id', $chunk)->pluck('id')->all();
                    if ($cids && $this->has('likes', ['likeable_type', 'likeable_id'])) {
                        foreach (array_chunk($cids, 500) as $cc) {
                            DB::table('likes')->where('likeable_type', 'App\\Models\\Comment')->whereIn('likeable_id', $cc)->delete();
                        }
                    }
                    DB::table('comments')->whereIn('commentable_type', self::TYPES)->whereIn('commentable_id', $chunk)->delete();
                }
                if ($this->has('bookmarks', ['bookmarkable_type', 'bookmarkable_id'])) {
                    DB::table('bookmarks')->whereIn('bookmarkable_type', self::TYPES)->whereIn('bookmarkable_id', $chunk)->delete();
                }
                if ($this->has('likes', ['likeable_type', 'likeable_id'])) {
                    DB::table('likes')->whereIn('likeable_type', self::TYPES)->whereIn('likeable_id', $chunk)->delete();
                }
                if ($this->has('reports', ['reportable_type', 'reportable_id'])) {
                    DB::table('reports')->whereIn('reportable_type', self::TYPES)->whereIn('reportable_id', $chunk)->delete();
                }
                if ($this->has('job_applications', ['job_post_id'])) {
                    DB::table('job_applications')->whereIn('job_post_id', $chunk)->delete();
                }

                // 3) 글 삭제
                $deleted += DB::table('job_posts')->whereIn('id', $chunk)->delete();
            }
        });

        Log::info('[더미 구인구직 정리] 완료', [
            '삭제' => $deleted,
            '보호(남김)' => count($protected),
            '남은 구인구직 글 전체' => DB::table('job_posts')->count(),
            '보관 표' => self::ARCHIVE,
        ]);
    }

    /** 표와 칸이 모두 있는지 */
    private function has(string $table, array $columns): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }
        foreach ($columns as $c) {
            if (!Schema::hasColumn($table, $c)) {
                return false;
            }
        }
        return true;
    }

    public function down(): void
    {
        // 보관해 둔 글을 다시 넣는다 (이미 있는 번호는 건드리지 않음). 댓글·북마크 등은 복원되지 않는다.
        if (!Schema::hasTable(self::ARCHIVE) || !Schema::hasTable('job_posts')) {
            return;
        }
        $ignore = DB::getDriverName() === 'mysql' ? 'INSERT IGNORE INTO' : 'INSERT OR IGNORE INTO';
        DB::statement("$ignore job_posts SELECT * FROM " . self::ARCHIVE);
    }
};
