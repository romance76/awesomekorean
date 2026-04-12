<?php

namespace App\Console\Commands;

use App\Models\QaPost;
use App\Models\QaAnswer;
use App\Models\QaCategory;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Q&A 게시판에 현실적인 시드 데이터를 생성한다.
 *
 * - 기존 Q&A 전부 삭제 후 새로 생성
 * - 유저 닉네임/이름을 자연스럽게 업데이트
 * - 11개 카테고리 × ~50개 = ~550개 질문
 * - 질문당 1~10개 답변 (랜덤)
 * - 모든 메타데이터 랜덤화 (조회수, 포인트, 날짜 등)
 */
class SeedQA extends Command
{
    protected $signature = 'qa:seed {--keep-users : 기존 유저 닉네임 유지}';
    protected $description = '현실적인 Q&A 시드 데이터 550개+ 생성';

    public function handle(): int
    {
        $this->info('Q&A 시드 데이터 생성 시작...');

        // 1. 기존 Q&A 삭제
        $this->info('기존 Q&A 데이터 삭제...');
        DB::statement('DELETE FROM qa_answers');
        DB::statement('DELETE FROM qa_posts');

        // 2. 유저 닉네임/이름 업데이트
        if (!$this->option('keep-users')) {
            $this->updateUserProfiles();
        }

        // 3. 카테고리 slug 매핑
        $categories = QaCategory::pluck('id', 'slug')->toArray();
        if (empty($categories)) {
            $this->error('QA 카테고리가 없습니다. DatabaseSeeder를 먼저 실행하세요.');
            return 1;
        }

        // 카테고리 이름→slug 매핑 (QaSeedData 에서 사용하는 키 대응)
        $catMap = [
            'immigration' => null,
            'legal' => null,
            'tax' => null,
            'realestate' => null,
            'auto' => null,
            'medical' => null,
            'education' => null,
            'jobs' => null,
            'life' => null,
            'tech' => null,
            'etc' => null,
        ];

        // DB 카테고리와 매핑
        foreach (QaCategory::all() as $cat) {
            $s = $cat->slug;
            // 기존 slug 가 다를 수 있으므로 이름으로 매핑
            $nameMap = [
                '이민/비자' => 'immigration', '법률' => 'legal', '세금/회계' => 'tax',
                '부동산' => 'realestate', '자동차' => 'auto', '의료/보험' => 'medical',
                '교육' => 'education', '취업' => 'jobs', '생활정보' => 'life',
                'IT/기술' => 'tech', '기타' => 'etc',
            ];
            $key = $nameMap[$cat->name] ?? $s;
            $catMap[$key] = $cat->id;
        }

        // 4. 유저 ID 풀
        $userIds = User::pluck('id')->toArray();
        if (count($userIds) < 10) {
            $this->error('유저가 10명 미만입니다. 유저를 먼저 생성하세요.');
            return 1;
        }

        // 5. 카테고리별 질문 + 답변 생성
        $totalQ = 0;
        $totalA = 0;

        foreach ($catMap as $catKey => $categoryId) {
            if (!$categoryId) {
                $this->warn("  카테고리 미매핑: {$catKey}");
                continue;
            }

            $questions = QaSeedData::questions($catKey);
            $answerTemplates = QaSeedData::answers($catKey);

            if (empty($questions)) {
                $this->warn("  {$catKey}: 질문 데이터 없음");
                continue;
            }

            $this->info("  {$catKey}: " . count($questions) . '개 질문 생성 중...');

            foreach ($questions as $q) {
                $daysAgo = rand(1, 90);
                $hoursAgo = rand(0, 23);
                $createdAt = Carbon::now()->subDays($daysAgo)->subHours($hoursAgo)->subMinutes(rand(0, 59));

                // 포인트 (20% 확률)
                $bounty = rand(1, 100) <= 20 ? [10, 20, 30, 50, 100][array_rand([10, 20, 30, 50, 100])] : 0;

                // 해결 여부 (40% 확률)
                $isResolved = rand(1, 100) <= 40;

                $post = QaPost::create([
                    'user_id'      => $userIds[array_rand($userIds)],
                    'category_id'  => $categoryId,
                    'title'        => $q['title'],
                    'content'      => $q['content'],
                    'bounty_points' => $bounty,
                    'view_count'   => rand(15, 1500),
                    'answer_count' => 0,
                    'is_resolved'  => false,
                    'created_at'   => $createdAt,
                    'updated_at'   => $createdAt,
                ]);
                $totalQ++;

                // 답변 생성 (1~8개 랜덤, 가중치: 1-3개가 더 많도록)
                $answerCount = $this->weightedRandom([1 => 20, 2 => 25, 3 => 20, 4 => 15, 5 => 10, 6 => 5, 7 => 3, 8 => 2]);
                $bestAnswerId = null;

                for ($a = 0; $a < $answerCount; $a++) {
                    // 답변 작성 시간: 질문 후 1시간~7일
                    $answerAt = (clone $createdAt)->addHours(rand(1, 168));
                    if ($answerAt->isFuture()) $answerAt = now()->subMinutes(rand(1, 60));

                    // 랜덤 답변 선택 + 약간 변형
                    $ansContent = $answerTemplates[array_rand($answerTemplates)];

                    $answer = QaAnswer::create([
                        'qa_post_id' => $post->id,
                        'user_id'    => $userIds[array_rand($userIds)],
                        'content'    => $ansContent,
                        'like_count' => rand(0, 20),
                        'is_best'    => false,
                        'created_at' => $answerAt,
                        'updated_at' => $answerAt,
                    ]);
                    $totalA++;

                    // 첫 번째 답변을 best 후보로
                    if ($a === 0) $bestAnswerId = $answer->id;
                }

                // 해결된 질문이면 best answer 지정
                if ($isResolved && $bestAnswerId) {
                    $post->update([
                        'is_resolved' => true,
                        'best_answer_id' => $bestAnswerId,
                        'answer_count' => $answerCount,
                    ]);
                    QaAnswer::where('id', $bestAnswerId)->update(['is_best' => true]);
                } else {
                    $post->update(['answer_count' => $answerCount]);
                }
            }
        }

        $this->info("완료: 질문={$totalQ}개, 답변={$totalA}개");
        return 0;
    }

    private function weightedRandom(array $weights): int
    {
        $sum = array_sum($weights);
        $r = rand(1, $sum);
        $cumulative = 0;
        foreach ($weights as $value => $weight) {
            $cumulative += $weight;
            if ($r <= $cumulative) return $value;
        }
        return array_key_first($weights);
    }

    private function updateUserProfiles(): void
    {
        $this->info('유저 프로필 업데이트...');

        $names = QaSeedData::userNames();
        $nicknames = QaSeedData::nicknames();

        $users = User::where('role', '!=', 'admin')->get();
        $usedNicks = [];

        foreach ($users as $i => $user) {
            $name = $names[$i % count($names)];
            $nick = $nicknames[$i % count($nicknames)];

            // 닉네임 중복 방지
            while (in_array($nick, $usedNicks)) {
                $nick = $nicknames[array_rand($nicknames)] . rand(1, 99);
            }
            $usedNicks[] = $nick;

            $user->update([
                'name' => $name,
                'nickname' => $nick,
            ]);
        }

        $this->info("  {$users->count()}명 유저 프로필 업데이트 완료");
    }
}
