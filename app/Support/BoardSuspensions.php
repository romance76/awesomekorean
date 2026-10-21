<?php

namespace App\Support;

use App\Models\Board;
use App\Models\BoardSuspension;
use App\Models\Notification;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * 신고로 인한 게시판 이용 정지.
 *
 *  - 관리자가 신고를 확인한 뒤 그 글쓴이를 "해당 게시판"에서 기간(며칠/영구) 동안 정지한다.
 *  - 정지된 회원이 그 게시판의 화면·API 에 들어오면 EnforceBoardSuspension 이 막고 "신고로 인하여 정지되어 사용할 수 없습니다" 를 보여 준다.
 *  - 정지 1건 = 1회. 풀리지 않은 정지가 MAX_STRIKES(3)회가 되면 계정 이용이 정지된다 (로그인 시 "관리자에게 문의해 주세요").
 *  - 게시판 키: 아래 BOARDS 의 이름, 그리고 커뮤니티는 게시판마다 따로 community:{게시판 번호}.
 */
class BoardSuspensions
{
    public const MAX_STRIKES = 3;

    /** 계정 이용 정지(3회 누적)로 막은 계정의 사유 머리말 — 정지를 풀어 3회 미만이 되면 이 머리말이 붙은 계정만 자동으로 되살린다 */
    public const BAN_PREFIX = '[신고 3회 누적] ';

    public const BOARDS = [
        'market' => '장터', 'jobs' => '구인구직', 'realestate' => '부동산', 'events' => '이벤트', 'clubs' => '동호회',
        'qa' => 'Q&A', 'recipes' => '레시피', 'news' => '뉴스', 'business' => '업소록', 'music' => '음악',
        'groupbuy' => '공동구매', 'shorts' => '숏츠', 'shopping' => '쇼핑',
    ];

    /** API 주소(/api/ 다음 첫 마디) → 게시판 키 */
    private const PATHS = [
        'market' => 'market', 'jobs' => 'jobs', 'resumes' => 'jobs', 'realestate' => 'realestate', 'events' => 'events', 'clubs' => 'clubs',
        'qa' => 'qa', 'recipes' => 'recipes', 'news' => 'news', 'businesses' => 'business', 'music' => 'music',
        'groupbuys' => 'groupbuy', 'shorts' => 'shorts', 'shopping' => 'shopping', 'shopping-reviews' => 'shopping', 'amazon-products' => 'shopping',
    ];

    /** 이 API 주소 첫 마디는 정지 검사 대상인가 (아니면 바로 통과 — 대부분의 요청은 여기서 끝난다) */
    public static function watches(?string $segment): bool
    {
        return $segment !== null && (isset(self::PATHS[$segment]) || in_array($segment, ['posts', 'comments'], true));
    }

    public static function label(string $key): string
    {
        if (str_starts_with($key, 'community:')) {
            $name = Board::whereKey((int) substr($key, 10))->value('name');
            return '커뮤니티 · ' . ($name ?: '게시판');
        }
        return self::BOARDS[$key] ?? $key;
    }

    public static function isValidKey(string $key): bool
    {
        if (isset(self::BOARDS[$key])) return true;
        return (bool) preg_match('/^community:\d+$/', $key) && Board::whereKey((int) substr($key, 10))->exists();
    }

    /** 정지를 걸 수 있는 게시판 전체 목록 (관리자 화면의 선택지) */
    public static function options(): array
    {
        $out = [];
        foreach (Board::orderBy('sort_order')->orderBy('id')->get(['id', 'name']) as $b) {
            $out[] = ['key' => 'community:' . $b->id, 'label' => '커뮤니티 · ' . $b->name];
        }
        foreach (self::BOARDS as $k => $v) $out[] = ['key' => $k, 'label' => $v];
        return $out;
    }

    /** 신고 대상 글이 속한 게시판 키 (모르면 null — 회원 신고 등) */
    public static function keyForTarget(?Model $target): ?string
    {
        if (!$target) return null;
        if ($target instanceof \App\Models\Comment) {
            $parent = class_exists((string) $target->commentable_type) ? $target->commentable : null;
            return $parent ? self::keyForTarget($parent) : null;
        }
        if ($target instanceof \App\Models\Post) return $target->board_id ? 'community:' . $target->board_id : null;
        $map = [
            \App\Models\MarketItem::class => 'market', \App\Models\RealEstateListing::class => 'realestate',
            \App\Models\GroupBuy::class => 'groupbuy', \App\Models\AmazonProduct::class => 'shopping',
            \App\Models\JobPost::class => 'jobs', \App\Models\Event::class => 'events', \App\Models\Club::class => 'clubs',
            \App\Models\QaPost::class => 'qa', \App\Models\RecipePost::class => 'recipes', \App\Models\Business::class => 'business',
        ];
        foreach ($map as $cls => $key) if ($target instanceof $cls) return $key;
        return null;
    }

    /** 이 요청이 가려는 게시판 키 (게시판 요청이 아니면 null) */
    public static function keyForRequest(Request $request): ?string
    {
        $seg = $request->segment(2);
        if (isset(self::PATHS[$seg])) return self::PATHS[$seg];

        if ($seg === 'posts') {
            $id = $request->segment(3);
            $boardId = ctype_digit((string) $id)
                ? \App\Models\Post::whereKey((int) $id)->value('board_id')
                : $request->input('board_id');
            return $boardId ? 'community:' . (int) $boardId : null;
        }
        if ($seg === 'comments' && $request->isMethod('post')) {
            $type = strtolower(ltrim((string) $request->input('commentable_type'), '\\'));
            if (in_array($type, ['post', 'app\\models\\post'], true)) {
                $boardId = \App\Models\Post::whereKey((int) $request->input('commentable_id'))->value('board_id');
                return $boardId ? 'community:' . (int) $boardId : null;
            }
        }
        return null;
    }

    /** 이 회원의 지금 효력 있는 정지 목록 (1분 캐시 — 정지를 걸거나 풀면 바로 비운다) */
    public static function activeFor(int $userId): array
    {
        return Cache::remember('board_susp_' . $userId, 60, fn () => BoardSuspension::where('user_id', $userId)->active()
            ->get(['id', 'board_key', 'board_label', 'ends_at', 'reason'])
            ->map(fn ($s) => ['id' => $s->id, 'key' => $s->board_key, 'label' => $s->board_label, 'ends_at' => $s->ends_at?->toIso8601String(), 'reason' => $s->reason])
            ->all());
    }

    public static function forget(int $userId): void
    {
        Cache::forget('board_susp_' . $userId);
    }

    /** 이 회원이 이 게시판에서 정지 중이면 그 정지 한 건 */
    public static function blockFor(int $userId, ?string $key): ?array
    {
        if (!$key) return null;
        foreach (self::activeFor($userId) as $s) {
            if ($s['key'] !== $key) continue;
            if ($s['ends_at'] === null || \Carbon\Carbon::parse($s['ends_at'])->isFuture()) return $s;
        }
        return null;
    }

    public static function strikes(int $userId): int
    {
        return BoardSuspension::where('user_id', $userId)->whereNull('lifted_at')->count();
    }

    /** 정지 안내 문구 (화면에 그대로 보여 준다) */
    public static function message(string $label, ?string $endsAt): string
    {
        $until = $endsAt ? \Carbon\Carbon::parse($endsAt)->timezone('America/New_York')->format('Y년 n월 j일') . '까지' : '영구';
        return "이 게시판({$label})은 신고로 인하여 정지되어 사용할 수 없습니다. (정지 기간: {$until})";
    }

    public static function payload(array $s): array
    {
        return [
            'success' => false, 'code' => 'board_suspended',
            'message' => self::message($s['label'], $s['ends_at']),
            'board' => $s['label'], 'until' => $s['ends_at'], 'permanent' => $s['ends_at'] === null,
        ];
    }

    /**
     * 정지 한 건 걸기. 누적 3회가 되면 계정 이용도 정지한다.
     * @return array{suspension: BoardSuspension, strikes: int, banned: bool}
     */
    public static function issue(User $target, string $key, ?int $days, ?Report $report, ?User $admin, ?string $reason): array
    {
        $s = BoardSuspension::create([
            'user_id' => $target->id, 'board_key' => $key, 'board_label' => self::label($key),
            'report_id' => $report?->id, 'reason' => $reason !== null && $reason !== '' ? mb_substr($reason, 0, 255) : null,
            'ends_at' => $days ? now()->addDays($days) : null, 'created_by' => $admin?->id,
        ]);
        self::forget($target->id);
        $strikes = self::strikes($target->id);

        $banned = false;
        if ($strikes >= self::MAX_STRIKES && !$target->is_banned) {
            $target->forceFill(['is_banned' => true, 'ban_reason' => self::BAN_PREFIX . '신고로 인한 게시판 정지가 ' . self::MAX_STRIKES . '회 쌓여 계정 이용이 정지되었습니다. 관리자에게 문의해 주세요.'])->save();
            $banned = true;
        }

        try {
            $msg = $banned
                ? '신고로 인한 게시판 정지가 ' . self::MAX_STRIKES . '회 쌓여 계정 이용이 정지되었습니다. 관리자에게 문의해 주세요.'
                : self::message($s->board_label, $s->ends_at?->toIso8601String()) . " (누적 {$strikes}/" . self::MAX_STRIKES . '회 — ' . self::MAX_STRIKES . '회가 되면 계정 이용이 정지돼요)';
            Notification::create([
                'user_id' => $target->id, 'type' => 'board_suspended', 'title' => '게시판 이용 정지 안내',
                'content' => $msg, 'data' => ['board' => $s->board_label, 'suspension_id' => $s->id],
            ]);
        } catch (\Throwable $e) {
            // 알림이 실패해도 정지는 그대로
        }
        return ['suspension' => $s, 'strikes' => $strikes, 'banned' => $banned];
    }

    /** 정지를 중간에 풀기. 3회 누적으로 막힌 계정은 3회 미만이 되면 함께 풀어 준다 */
    public static function lift(BoardSuspension $s, ?User $admin): bool
    {
        if ($s->lifted_at) return false;
        $s->forceFill(['lifted_at' => now(), 'lifted_by' => $admin?->id])->save();
        self::forget($s->user_id);

        $user = User::find($s->user_id);
        if ($user && $user->is_banned && str_starts_with((string) $user->ban_reason, self::BAN_PREFIX) && self::strikes($user->id) < self::MAX_STRIKES) {
            $user->forceFill(['is_banned' => false, 'ban_reason' => null])->save();
        }
        return true;
    }
}
