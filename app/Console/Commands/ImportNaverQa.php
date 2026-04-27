<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\QaCategory;
use App\Models\QaPost;
use App\Models\QaAnswer;
use App\Models\Comment;

/**
 * 네이버 지식인 스크래핑 + KR→ES→KR 번역 + 더미 작성자명 SQLite DB 를
 * 우리 사이트 qa_posts / qa_answers 로 일괄 import.
 *
 * 사용법:
 *   php artisan qa:import-naver --db=/path/to/naver_scraper.db --truncate
 *   php artisan qa:import-naver --db=/path/to/naver_scraper.db --truncate --limit=500
 *
 * - --truncate: 기존 qa_posts / qa_answers / 관련 댓글 모두 삭제 후 import
 * - --limit=N: import 하는 questions 수를 N 개로 제한 (디버그용)
 *
 * 더미 유저:
 *   email = "naver_dummy_{md5(fake_name)[:10]}@somekorean.local"
 *   같은 fake_name 은 같은 user 로 재사용
 *   role = 'user', password 는 임의 (로그인 불가)
 */
class ImportNaverQa extends Command
{
    protected $signature = 'qa:import-naver {--db= : SQLite DB 파일 경로 (필수)} {--truncate : 기존 Q&A 모두 삭제} {--limit=0 : 가져올 질문 수 제한 (0=전체)}';
    protected $description = '네이버 스크래핑 SQLite DB → qa_posts/qa_answers 일괄 import';

    public function handle(): int
    {
        $dbPath = $this->option('db');
        if (!$dbPath || !file_exists($dbPath)) {
            $this->error("--db 옵션 필요. 파일이 존재해야 함. 받은 값: {$dbPath}");
            return 1;
        }

        $limit = (int) $this->option('limit');
        $truncate = (bool) $this->option('truncate');

        $this->info("📂 SQLite 파일: {$dbPath}");
        $this->info("🔢 limit: " . ($limit > 0 ? $limit : '전체'));

        // SQLite 연결
        $sqlite = new \PDO('sqlite:' . $dbPath);
        $sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // 1) 기존 Q&A 삭제
        if ($truncate) {
            $this->warn('⚠️  기존 Q&A 전부 삭제합니다...');
            DB::transaction(function () {
                // Q&A 글에 달린 댓글 (Comment 다형성: commentable_type)
                Comment::where('commentable_type', QaPost::class)->delete();
                Comment::where('commentable_type', QaAnswer::class)->delete();
                // 답변 좋아요/싫어요 — 테이블 존재 시
                if (\Schema::hasTable('qa_answer_likes')) {
                    DB::table('qa_answer_likes')->delete();
                }
                QaAnswer::query()->delete();
                QaPost::query()->delete();
            });
            $this->info('✅ 기존 Q&A 삭제 완료');
        }

        // 2) 카테고리 매핑 (이름으로 매칭, 없으면 만들기)
        $catMap = QaCategory::pluck('id', 'name')->toArray();
        $this->info('📂 기존 카테고리 ' . count($catMap) . '개');

        // 3) Question 가져오기
        $sql = "SELECT id, category, fake_title, fake_body, fake_author_name, posted_at, view_count
                FROM questions
                WHERE fake_title IS NOT NULL AND fake_body IS NOT NULL
                ORDER BY id";
        if ($limit > 0) $sql .= " LIMIT {$limit}";

        $stmt = $sqlite->query($sql);
        $questions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->info('📥 Question ' . count($questions) . '개 발견');

        // 4) 답변 prefetch (question_id 별 그룹)
        $ansStmt = $sqlite->query("SELECT question_id, fake_author_name, fake_body, is_accepted, like_count, posted_at, display_order
                                   FROM answers
                                   WHERE fake_body IS NOT NULL
                                   ORDER BY question_id, display_order");
        $allAnswers = $ansStmt->fetchAll(\PDO::FETCH_ASSOC);
        $answersByQ = [];
        foreach ($allAnswers as $a) {
            $answersByQ[$a['question_id']][] = $a;
        }
        $this->info('💬 Answer ' . count($allAnswers) . '개 발견');

        // 5) 더미 유저 캐시 (fake_name → user_id)
        $userCache = [];

        // 5-1) author_mapping 의 fake_name 풀 — 질문 작성자가 NULL 일 때 랜덤 할당용
        //      (source DB 의 모든 질문은 fake_author_name 이 NULL 이라 풀에서 랜덤 선택해야 함)
        $authorPool = $sqlite->query("SELECT DISTINCT fake_name FROM author_mapping WHERE fake_name IS NOT NULL AND fake_name != ''")->fetchAll(\PDO::FETCH_COLUMN);
        $authorPoolCount = count($authorPool);
        $this->info("👤 더미 작성자 풀: {$authorPoolCount}개");

        $bar = $this->output->createProgressBar(count($questions));
        $bar->start();

        $importedQ = 0;
        $importedA = 0;
        $newUsers = 0;

        foreach ($questions as $q) {
            try {
                // 질문 작성자: fake_author_name 이 있으면 사용, 없으면 풀에서 deterministic 랜덤 선택
                $authorName = $q['fake_author_name'] ?: ($authorPoolCount > 0
                    ? $authorPool[crc32('q' . $q['id']) % $authorPoolCount]
                    : '익명');
                $userId = $this->getOrCreateUser($authorName, $userCache, $newUsers);

                $catId = $catMap[$q['category']] ?? null;

                $postedAt = $q['posted_at'] ?: now();

                $answers = $answersByQ[$q['id']] ?? [];
                $answerCount = count($answers);

                $post = QaPost::create([
                    'user_id'       => $userId,
                    'category_id'   => $catId,
                    'title'         => mb_substr($q['fake_title'], 0, 250),
                    'content'       => $q['fake_body'],
                    'view_count'    => (int) $q['view_count'],
                    'answer_count'  => $answerCount,
                    'is_resolved'   => false,
                    'created_at'    => $postedAt,
                    'updated_at'    => $postedAt,
                ]);
                $importedQ++;

                $bestAnswerId = null;
                foreach ($answers as $a) {
                    $aAuthor = $a['fake_author_name'] ?: ($authorPoolCount > 0
                        ? $authorPool[crc32('a' . $a['question_id'] . '_' . ($a['display_order'] ?? 0)) % $authorPoolCount]
                        : '익명');
                    $aUserId = $this->getOrCreateUser($aAuthor, $userCache, $newUsers);
                    $aPostedAt = $a['posted_at'] ?: $postedAt;

                    $ans = QaAnswer::create([
                        'qa_post_id' => $post->id,
                        'user_id'    => $aUserId,
                        'content'    => $a['fake_body'],
                        'like_count' => (int) $a['like_count'],
                        'is_best'    => (bool) $a['is_accepted'],
                        'created_at' => $aPostedAt,
                        'updated_at' => $aPostedAt,
                    ]);
                    if ($a['is_accepted'] && !$bestAnswerId) {
                        $bestAnswerId = $ans->id;
                    }
                    $importedA++;
                }

                if ($bestAnswerId) {
                    $post->update(['is_resolved' => true, 'best_answer_id' => $bestAnswerId]);
                }
            } catch (\Throwable $e) {
                $this->error("\n질문 #{$q['id']} 실패: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ 완료");
        $this->info("   질문: {$importedQ}");
        $this->info("   답변: {$importedA}");
        $this->info("   신규 더미 유저: {$newUsers}");
        $this->info("   재사용된 유저(캐시): " . (count($userCache) - $newUsers));

        return 0;
    }

    private function getOrCreateUser(string $fakeName, array &$cache, int &$newCount): int
    {
        if (isset($cache[$fakeName])) return $cache[$fakeName];

        $emailHash = substr(md5($fakeName), 0, 10);
        $email = "naver_dummy_{$emailHash}@somekorean.local";

        $user = User::where('email', $email)->first();
        if (!$user) {
            $user = User::create([
                'name'     => $fakeName,
                'nickname' => $fakeName,
                'email'    => $email,
                'password' => Hash::make(\Str::random(32)),
                'role'     => 'user',
                'language' => 'ko',
                'allow_friend_request' => false,
                'allow_messages' => false,
            ]);
            $newCount++;
        }
        $cache[$fakeName] = $user->id;
        return $user->id;
    }
}
