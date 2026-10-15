<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// database/data/money_posts/<cafe>.json 을 money_posts 테이블과 매번 동기화한다(같은 카페+제목이면 건너뜀).
// 마이그레이션은 서버에서 딱 한 번만 실행되므로, 운영팀 글 JSON을 계속 늘려 가는 이 작업에는
// 맞지 않는다 — 배포마다(deploy.sh) 실행되는 이 커맨드로 대체한다.
class SyncMoneyPosts extends Command
{
    protected $signature = 'money:sync-posts';
    protected $description = 'database/data/money_posts/*.json 의 운영팀 글을 DB와 동기화 (배포마다 실행, 중복 없음)';

    private const OPS_EMAIL = 'money-ops@awesomekorean.local';

    public function handle(): int
    {
        $files = glob(database_path('data/money_posts/*.json')) ?: [];
        if (!$files) {
            $this->info('동기화할 JSON 파일이 없어요.');
            return self::SUCCESS;
        }

        $ops = DB::table('users')->where('email', self::OPS_EMAIL)->first();
        if (!$ops) {
            $u = new User();
            $u->forceFill([
                'name' => '어썸코리안', 'nickname' => '어썸코리안', 'email' => self::OPS_EMAIL,
                'password' => Hash::make(Str::random(48)), 'role' => 'user', 'language' => 'ko',
                'allow_friend_request' => false, 'allow_messages' => false, 'email_verified_at' => now(),
            ])->save();
            $opsId = $u->id;
        } else {
            $opsId = $ops->id;
        }

        $now = now();
        $totalInserted = 0; $totalUpdated = 0;
        foreach ($files as $file) {
            $slug = pathinfo($file, PATHINFO_FILENAME);
            $rows = json_decode(file_get_contents($file), true);
            $cafe = DB::table('money_cafes')->where('slug', $slug)->first();
            if (!$cafe || !is_array($rows)) continue;

            $cats = DB::table('money_categories')->where('cafe_id', $cafe->id)->pluck('id', 'name')->all();
            $existing = DB::table('money_posts')->where('cafe_id', $cafe->id)->where('user_id', $opsId)->pluck('id', 'title')->all();
            $existingTitles = DB::table('money_posts')->where('cafe_id', $cafe->id)->pluck('title')->flip()->all();

            $topicIdx = []; $perTopic = [];
            $batch = [];
            foreach ($rows as $r) {
                $topic = $r['topic'];
                if (!isset($topicIdx[$topic])) $topicIdx[$topic] = count($topicIdx);
                $pi = $perTopic[$topic] = ($perTopic[$topic] ?? -1) + 1;
                $ti = $topicIdx[$topic];
                if (!isset($cats[$topic])) continue;
                $title = mb_substr($r['title'], 0, 200);
                $blocksJson = !empty($r['blocks']) ? json_encode($r['blocks'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
                if (isset($existing[$title])) {
                    // 운영팀 글은 JSON 이 기준: 내용이 바뀌었으면 갱신 (회원 글·댓글·조회수는 건드리지 않음)
                    $upd = DB::table('money_posts')->where('id', $existing[$title])->where('blocks', '!=', $blocksJson)
                        ->update(['blocks' => $blocksJson, 'content' => $this->toHtml($r['content']), 'image' => $r['image'] ?? null, 'updated_at' => $now]);
                    $totalUpdated += $upd;
                    continue;
                }
                if (isset($existingTitles[$title])) continue;

                $at = $now->copy()->subDays((int) floor($pi * 2.6 + $ti * 0.4))->subMinutes(($ti * 53 + $pi * 17) % 600);
                $batch[] = [
                    'cafe_id' => $cafe->id, 'category_id' => $cats[$topic], 'user_id' => $opsId,
                    'kind' => in_array($r['kind'], ['info', 'question', 'talk'], true) ? $r['kind'] : 'info',
                    'title' => $title, 'content' => $this->toHtml($r['content']),
                    'image' => $r['image'] ?? null,
                    'blocks' => $blocksJson,
                    'view_count' => 0, 'comment_count' => 0, 'is_hidden' => false, 'is_pinned' => false,
                    'created_at' => $at, 'updated_at' => $at,
                ];
            }
            foreach (array_chunk($batch, 50) as $chunk) {
                DB::table('money_posts')->insert($chunk);
                $totalInserted += count($chunk);
            }
            if ($batch) $this->info("{$slug}: " . count($batch) . '개 추가');
        }

        $this->info("총 {$totalInserted}개 추가, {$totalUpdated}개 갱신됐어요.");
        return self::SUCCESS;
    }

    private function toHtml(string $text): string
    {
        $out = ''; $inList = false;
        foreach (preg_split('/\R/u', trim($text)) as $line) {
            $line = trim(str_replace('**', '', $line));
            if ($line === '') continue;
            if (str_starts_with($line, '- ')) {
                if (!$inList) { $out .= '<ul>'; $inList = true; }
                $out .= '<li>' . e(substr($line, 2)) . '</li>';
            } else {
                if ($inList) { $out .= '</ul>'; $inList = false; }
                $out .= '<p>' . e($line) . '</p>';
            }
        }
        if ($inList) $out .= '</ul>';
        return $out;
    }
}
