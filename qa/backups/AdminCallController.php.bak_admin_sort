<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Call;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 관리자 통화 로그 — 누가 누구에게 걸었고, 받았는지/왜 끊겼는지/어떤 연결(직접·중계)이었는지/지연은 얼마였는지.
 * 날짜 경계는 미국 동부시간(ET) 기준.
 */
class AdminCallController extends Controller
{
    private const TZ = 'America/New_York';

    private function since(Request $request): \Carbon\Carbon
    {
        $days = max(1, min(90, (int) $request->input('days', 7)));
        return now(self::TZ)->subDays($days - 1)->startOfDay()->setTimezone(config('app.timezone'));
    }

    private function base(Request $request)
    {
        $q = Call::query()->where('created_at', '>=', $this->since($request));
        if ($request->filled('type')) $q->where('call_type', $request->type);
        return $q;
    }

    /** GET /admin/calls */
    public function index(Request $request)
    {
        $q = $this->base($request)->with(['caller:id,name,nickname,email', 'callee:id,name,nickname,email'])->orderByDesc('id');

        if ($request->filled('outcome')) {
            match ($request->outcome) {
                'connected' => $q->whereNotNull('answered_at'),
                'unanswered' => $q->whereNull('answered_at')->whereIn('end_reason', ['no_answer', 'cancelled', 'stale']),
                'declined' => $q->where('end_reason', 'declined'),
                'offline' => $q->where('end_reason', 'offline'),
                'busy' => $q->where('end_reason', 'busy'),
                'failed' => $q->where(fn($w) => $w->where('end_reason', 'failed')->orWhere('status', 'failed')),
                default => null,
            };
        }
        if ($request->filled('conn')) $q->where('conn_type', $request->conn);
        if ($s = trim((string) $request->input('search'))) {
            $q->where(function ($w) use ($s) {
                $like = "%{$s}%";
                foreach (['caller', 'callee'] as $rel) {
                    $w->orWhereHas($rel, fn($u) => $u->where('name', 'like', $like)->orWhere('nickname', 'like', $like)->orWhere('email', 'like', $like));
                }
            });
        }

        $page = $q->paginate(30);
        $page->getCollection()->transform(function (Call $c) {
            return [
                'id' => $c->id, 'call_type' => $c->call_type ?: 'friend', 'status' => $c->status, 'end_reason' => $c->end_reason,
                'caller' => $c->caller ? ['id' => $c->caller->id, 'name' => $c->caller->nickname ?: $c->caller->name, 'email' => $c->caller->email] : null,
                'callee' => $c->callee ? ['id' => $c->callee->id, 'name' => $c->callee->nickname ?: $c->callee->name, 'email' => $c->callee->email] : null,
                'created_at' => $c->created_at?->toISOString(), 'answered_at' => $c->answered_at?->toISOString(), 'ended_at' => $c->ended_at?->toISOString(),
                'ring_seconds' => ($c->answered_at ?? $c->ended_at) && $c->created_at ? max(0, (int) $c->created_at->diffInSeconds($c->answered_at ?? $c->ended_at, true)) : null,
                'duration' => (int) $c->duration, 'conn_type' => $c->conn_type, 'rtt_ms' => $c->rtt_ms, 'note' => $c->failure_note,
                'caller_device' => self::device($c->caller_ua), 'callee_device' => self::device($c->callee_ua),
                'ended_by' => $c->ended_by === null ? null : ($c->ended_by === $c->caller_id ? 'caller' : ($c->ended_by === $c->callee_id ? 'callee' : 'system')),
            ];
        });
        return response()->json(['success' => true, 'data' => $page]);
    }

    /** GET /admin/calls/stats */
    public function stats(Request $request)
    {
        $q = fn() => $this->base($request);
        $total = $q()->count();
        $connected = $q()->whereNotNull('answered_at')->count();
        $reasons = $q()->select('end_reason', DB::raw('COUNT(*) c'))->groupBy('end_reason')->pluck('c', 'end_reason');
        $conn = $q()->whereNotNull('conn_type')->select('conn_type', DB::raw('COUNT(*) c'))->groupBy('conn_type')->pluck('c', 'conn_type');
        $failNotes = $q()->whereNotNull('failure_note')->whereIn('end_reason', ['failed'])->select('failure_note', DB::raw('COUNT(*) c'))
            ->groupBy('failure_note')->orderByDesc('c')->limit(5)->get();

        // 하루별 (ET 기준)
        $daily = $q()->get(['created_at', 'answered_at'])->groupBy(fn($c) => $c->created_at->setTimezone(self::TZ)->format('m/d'))
            ->map(fn($g) => ['total' => $g->count(), 'connected' => $g->whereNotNull('answered_at')->count()]);

        return response()->json(['success' => true, 'data' => [
            'total' => $total,
            'connected' => $connected,
            'connect_rate' => $total ? round($connected / $total * 100) : 0,
            'unanswered' => (int) (($reasons['no_answer'] ?? 0) + ($reasons['cancelled'] ?? 0) + ($reasons['stale'] ?? 0)),
            'declined' => (int) ($reasons['declined'] ?? 0),
            'offline' => (int) ($reasons['offline'] ?? 0),
            'busy' => (int) ($reasons['busy'] ?? 0),
            'failed' => (int) ($reasons['failed'] ?? 0),
            'avg_duration' => (int) $q()->where('duration', '>', 0)->avg('duration'),
            'avg_rtt_ms' => (int) $q()->whereNotNull('rtt_ms')->avg('rtt_ms'),
            'direct' => (int) ($conn['direct'] ?? 0),
            'relay' => (int) ($conn['relay'] ?? 0),
            'fail_notes' => $failNotes,
            'daily' => $daily,
        ]]);
    }

    /** User-Agent 를 "iPhone · Safari" 처럼 짧게 */
    private static function device(?string $ua): ?string
    {
        if (!$ua) return null;
        $os = match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'iOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/Macintosh|Mac OS/i', $ua) => 'Mac',
            (bool) preg_match('/Linux/i', $ua) => 'Linux',
            default => '기타',
        };
        $br = match (true) {
            (bool) preg_match('/Edg\//i', $ua) => 'Edge',
            (bool) preg_match('/SamsungBrowser/i', $ua) => 'Samsung',
            (bool) preg_match('/CriOS|Chrome\//i', $ua) => 'Chrome',
            (bool) preg_match('/FxiOS|Firefox/i', $ua) => 'Firefox',
            (bool) preg_match('/Safari/i', $ua) => 'Safari',
            default => '',
        };
        return trim($os . ($br ? ' · ' . $br : ''));
    }
}
