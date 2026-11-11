<?php

namespace App\Support;

use App\Models\Post;
use App\Models\QaAnswer;
use App\Models\QaPost;
use App\Models\SiteSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 커뮤니티 게시판·Q&A 자동 게시 (더미 회원 이름으로, 해외 커뮤니티의 한국 관련 새 글을 번역해 올림).
 *
 * - 하루 목표(각각 daily_min~daily_max 개)를 그날 처음 상태를 볼 때 정하고, 올릴 시각 슬롯도 무작위로 미리 뽑아 둔다.
 * - 실제 글 작성은 '정보' 글과 같은 방식으로 외부 루틴이 한다. 루틴은 /api/seed-ingest/status 로 지금 올려도 되는 개수(due)를 보고,
 *   /community, /qa 로 올린다. 서버가 켜짐 여부·회원 수 상한·하루 목표·슬롯 시각을 모두 다시 확인하므로 루틴이 과하게 올릴 수 없다.
 * - 전체 회원 수가 member_cap 을 넘으면 저절로 멈춘다. 관리자 > 소통 > 자동 게시 에서 켜고 끄고 숫자를 바꾼다.
 */
class AutoSeedPosts
{
    public const CONFIG_KEY = 'auto_seed_config';
    public const STATE_KEY  = 'auto_seed_state';
    public const TZ = 'America/New_York';
    public const KINDS = ['community', 'qa'];

    public static function defaults(): array
    {
        return ['enabled' => true, 'daily_min' => 5, 'daily_max' => 7, 'member_cap' => 3000, 'start_hour' => 8, 'end_hour' => 23];
    }

    public static function config(): array
    {
        $raw = SiteSetting::where('key', self::CONFIG_KEY)->value('value');
        $c = array_merge(self::defaults(), $raw ? (json_decode($raw, true) ?: []) : []);
        $c['enabled'] = (bool) $c['enabled'];
        $c['daily_min'] = max(0, min(30, (int) $c['daily_min']));
        $c['daily_max'] = max($c['daily_min'], min(30, (int) $c['daily_max']));
        $c['member_cap'] = max(0, (int) $c['member_cap']);
        $c['start_hour'] = max(0, min(22, (int) $c['start_hour']));
        $c['end_hour'] = max($c['start_hour'] + 1, min(24, (int) $c['end_hour']));
        return $c;
    }

    public static function saveConfig(array $in): array
    {
        $c = array_merge(self::config(), array_intersect_key($in, self::defaults()));
        SiteSetting::updateOrCreate(['key' => self::CONFIG_KEY], ['value' => json_encode($c), 'group' => 'auto_seed']);
        // 하루 개수·시간대를 바꾸면 오늘 계획도 바로 다시 뽑는다 (이미 올린 수는 유지)
        $raw = SiteSetting::where('key', self::STATE_KEY)->value('value');
        $s = $raw ? (json_decode($raw, true) ?: []) : [];
        if (($s['date'] ?? null) === Carbon::now(self::TZ)->toDateString()) {
            $cfg = self::config();
            foreach (self::KINDS as $k) {
                $done = (int) ($s['plan'][$k]['done'] ?? 0);
                $n = max($done, random_int($cfg['daily_min'], $cfg['daily_max']));
                $s['plan'][$k] = ['target' => $n, 'slots' => self::randomSlots($n, $cfg['start_hour'], $cfg['end_hour']), 'done' => $done];
            }
            self::saveState($s);
        }
        return $cfg;
    }

    /** 전체 회원 수 (상한 비교용) */
    public static function memberCount(): int
    {
        return (int) DB::table('users')->count();
    }

    /** 지금 올려도 되는지: null 이면 가능, 아니면 멈춘 이유 */
    public static function blockReason(?array $c = null): ?string
    {
        $c ??= self::config();
        if (!$c['enabled']) return 'disabled';
        if ($c['member_cap'] > 0 && self::memberCount() > $c['member_cap']) return 'member_cap';
        return null;
    }

    /** 글쓴이로 쓸 더미 회원 후보 (운영 계정·관리자·정지 회원 제외) */
    public static function authorPool(): array
    {
        return DB::table('users')
            ->where(fn ($q) => $q->where('email', 'like', '%@test.com')->orWhere('email', 'like', '%@awesomekorean.local'))
            ->where('email', '!=', 'money-ops@awesomekorean.local')
            ->whereNotIn('role', ['admin', 'super_admin'])
            ->where(fn ($q) => $q->whereNull('is_banned')->orWhere('is_banned', false))
            ->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /** 돌려가며 고르기: 최근에 쓴 사람은 빼고 무작위 */
    public static function pickAuthor(array $avoid = [], ?array $pool = null): ?int
    {
        $pool ??= self::authorPool();
        if (!$pool) return null;
        $cand = array_values(array_diff($pool, $avoid));
        if (!$cand) $cand = $pool;
        return $cand[array_rand($cand)];
    }

    public static function state(): array
    {
        $raw = SiteSetting::where('key', self::STATE_KEY)->value('value');
        $s = $raw ? (json_decode($raw, true) ?: []) : [];
        $today = Carbon::now(self::TZ)->toDateString();
        if (($s['date'] ?? null) !== $today) {
            $c = self::config();
            $s = ['date' => $today, 'plan' => [], 'recent_authors' => $s['recent_authors'] ?? [], 'seen_sources' => $s['seen_sources'] ?? [], 'log' => $s['log'] ?? []];
            foreach (self::KINDS as $k) {
                $n = random_int($c['daily_min'], $c['daily_max']);
                $s['plan'][$k] = ['target' => $n, 'slots' => self::randomSlots($n, $c['start_hour'], $c['end_hour']), 'done' => 0];
            }
            self::saveState($s);
        }
        return $s;
    }

    public static function saveState(array $s): void
    {
        $s['log'] = array_slice($s['log'] ?? [], 0, 300);
        $s['seen_sources'] = array_slice($s['seen_sources'] ?? [], 0, 1500);
        $s['recent_authors'] = array_slice($s['recent_authors'] ?? [], 0, 40);
        SiteSetting::updateOrCreate(['key' => self::STATE_KEY], ['value' => json_encode($s, JSON_UNESCAPED_UNICODE), 'group' => 'auto_seed']);
    }

    /** 하루 중 무작위 시각 n 개 (오름차순, 애틀랜타 시각 → ISO) */
    private static function randomSlots(int $n, int $startHour, int $endHour): array
    {
        $day = Carbon::now(self::TZ)->startOfDay();
        $mins = [];
        for ($i = 0; $i < $n; $i++) $mins[] = random_int($startHour * 60, $endHour * 60 - 1);
        sort($mins);
        return array_map(fn ($m) => $day->copy()->addMinutes($m)->toIso8601String(), $mins);
    }

    /** 종류별로 지금 올려도 되는 개수 (이미 지난 슬롯 수 − 이미 올린 수) */
    public static function due(array $state, string $kind): int
    {
        $p = $state['plan'][$kind] ?? null;
        if (!$p) return 0;
        $passed = 0;
        foreach ($p['slots'] as $t) if (Carbon::parse($t)->lte(now())) $passed++;
        return max(0, $passed - (int) $p['done']);
    }

    /** 글 하나를 올렸다고 기록 (한도·슬롯은 호출 전에 확인) */
    public static function record(string $kind, int $id, int $authorId, string $title, ?string $sourceUrl, ?string $path = null): void
    {
        $s = self::state();
        $s['plan'][$kind]['done'] = (int) ($s['plan'][$kind]['done'] ?? 0) + 1;
        array_unshift($s['recent_authors'], $authorId);
        if ($sourceUrl) array_unshift($s['seen_sources'], self::sourceKey($sourceUrl));
        array_unshift($s['log'], ['kind' => $kind, 'id' => $id, 'user_id' => $authorId, 'title' => mb_substr($title, 0, 80), 'source' => $sourceUrl, 'path' => $path, 'at' => now()->toIso8601String()]);
        self::saveState($s);
    }

    public static function sourceKey(string $url): string
    {
        $u = strtolower(trim($url));
        $u = preg_replace('#^https?://(www\.|old\.|new\.)?#', '', $u);
        return rtrim(preg_replace('#[?\#].*$#', '', $u), '/');
    }

    public static function isSeen(string $url): bool
    {
        return in_array(self::sourceKey($url), self::state()['seen_sources'] ?? [], true);
    }

    /** 커뮤니티 글 작성 — 포인트 지급 없음(실제 활동이 아니라 시드 글) */
    public static function createCommunity(int $authorId, int $boardId, string $title, string $content): Post
    {
        return Post::create([
            'board_id' => $boardId, 'user_id' => $authorId,
            'title' => PlainText::clean($title), 'content' => PlainText::clean($content),
        ]);
    }

    /** Q&A 질문과(있으면) 답변 작성. 답변은 질문자와 다른 더미 회원이 쓴다. */
    public static function createQa(int $authorId, ?int $categoryId, string $title, string $content, array $answers = []): QaPost
    {
        $post = QaPost::create([
            'user_id' => $authorId, 'category_id' => $categoryId,
            'title' => PlainText::clean($title), 'content' => PlainText::clean($content), 'bounty_points' => 0,
        ]);
        $pool = self::authorPool();
        $avoid = [$authorId];
        foreach ($answers as $text) {
            $text = trim((string) $text);
            if ($text === '') continue;
            $uid = self::pickAuthor($avoid, $pool) ?? $authorId;
            $avoid[] = $uid;
            QaAnswer::create(['qa_post_id' => $post->id, 'user_id' => $uid, 'content' => PlainText::clean($text)]);
            $post->increment('answer_count');
        }
        return $post;
    }
}
