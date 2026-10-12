<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// 머니 카페 주제별 운영팀 글 — database/data/money_posts/<카페>.json (Kay 지시 2026-10-12: 주제마다 10편, 글쓴이 "어썸코리안").
// - 글쓴이는 로그인할 수 없는 운영 계정(무작위 비밀번호, 이메일 도메인 somekorean.local → 순위·집계에서 더미로 제외).
// - 회원 경험담을 지어내지 않는다: 정보·질문(토론 유도)·소통 글만. 인증 릴레이도 '양식·예시(가상)·챌린지' 안내만.
// - 이미 있는 글(같은 카페·같은 제목)은 건드리지 않는다 → 다시 돌려도 중복 없음. 새 JSON 이 생기면 같은 방식 마이그레이션을 더 만들 것.
return new class extends Migration
{
    private const OPS_EMAIL = 'money-ops@somekorean.local';

    public function up(): void
    {
        $files = glob(database_path('data/money_posts/*.json')) ?: [];
        if (!$files) return;

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
        foreach ($files as $file) {
            $slug = pathinfo($file, PATHINFO_FILENAME);
            $rows = json_decode(file_get_contents($file), true);
            $cafe = DB::table('money_cafes')->where('slug', $slug)->first();
            if (!$cafe || !is_array($rows)) continue;

            $cats = DB::table('money_categories')->where('cafe_id', $cafe->id)->pluck('id', 'name')->all();
            $topicIdx = []; $perTopic = [];
            $batch = [];
            foreach ($rows as $r) {
                $topic = $r['topic'];
                if (!isset($topicIdx[$topic])) $topicIdx[$topic] = count($topicIdx);
                $pi = $perTopic[$topic] = ($perTopic[$topic] ?? -1) + 1;
                $ti = $topicIdx[$topic];
                if (!isset($cats[$topic])) continue;
                if (DB::table('money_posts')->where('cafe_id', $cafe->id)->where('title', $r['title'])->exists()) continue;

                // 같은 날 몰리지 않게 며칠에 걸쳐 흩어 둔다 (최근 약 4주)
                $at = $now->copy()->subDays((int) floor($pi * 2.6 + $ti * 0.4))->subMinutes(($ti * 53 + $pi * 17) % 600);
                $batch[] = [
                    'cafe_id' => $cafe->id, 'category_id' => $cats[$topic], 'user_id' => $opsId,
                    'kind' => in_array($r['kind'], ['info', 'question', 'talk'], true) ? $r['kind'] : 'info',
                    'title' => mb_substr($r['title'], 0, 200), 'content' => $this->toHtml($r['content']),
                    'image' => $r['image'] ?? null,
                    'blocks' => !empty($r['blocks']) ? json_encode($r['blocks'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'view_count' => 0, 'comment_count' => 0, 'is_hidden' => false, 'is_pinned' => false,
                    'created_at' => $at, 'updated_at' => $at,
                ];
            }
            foreach (array_chunk($batch, 50) as $chunk) DB::table('money_posts')->insert($chunk);
        }
    }

    // 글 형식: "- " 로 시작하는 줄은 목록, 나머지 줄은 문단 (모두 이스케이프)
    private function toHtml(string $text): string
    {
        $out = ''; $inList = false;
        foreach (preg_split('/\R/u', trim($text)) as $line) {
            $line = trim($line);
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

    public function down(): void
    {
        // 데이터 보호: 글·계정을 지우지 않는다.
    }
};
