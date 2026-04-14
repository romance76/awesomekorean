<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PokerTournament;
use App\Models\PokerTournamentEntry;
use App\Models\PokerWallet;
use App\Events\PokerLobbyUpdate;
use App\Events\PokerTournamentUpdate;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PokerTournamentController extends Controller
{
    // 토너먼트 목록 (예정 + 진행중)
    public function index(Request $request)
    {
        $userId = $request->user()?->id;

        $upcoming = PokerTournament::upcoming()
            ->where('is_template', false)
            ->withCount(['entries as registered_count', 'entries as online_count' => function ($q) {
                $q->where('is_online', true);
            }])
            ->get()
            ->map(function ($t) use ($userId) {
                $t->is_registered = $userId ? $t->entries()->where('user_id', $userId)->exists() : false;
                return $t;
            });

        $running = PokerTournament::running()
            ->where('is_template', false)
            ->withCount(['entries as registered_count', 'entries as online_count' => function ($q) {
                $q->where('is_online', true);
            }, 'entries as remaining_count' => function ($q) {
                $q->whereIn('status', ['seated', 'playing']);
            }])
            ->get()
            ->map(function ($t) use ($userId) {
                $t->is_registered = $userId ? $t->entries()->where('user_id', $userId)->exists() : false;
                return $t;
            });

        return response()->json([
            'success' => true,
            'data' => [
                'upcoming' => $upcoming,
                'running' => $running,
            ],
        ]);
    }

    // 토너먼트 상세 + 대기실 명단
    public function show(Request $request, $id)
    {
        $tournament = PokerTournament::withCount(['entries as registered_count', 'entries as online_count' => function ($q) {
            $q->where('is_online', true);
        }])->findOrFail($id);

        $userId = $request->user()?->id;
        $tournament->is_registered = $userId ? $tournament->entries()->where('user_id', $userId)->exists() : false;

        $entries = PokerTournamentEntry::where('tournament_id', $id)
            ->with('user:id,name,nickname,avatar')
            ->orderByDesc('is_online')
            ->orderBy('created_at')
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'user_id' => $e->user_id,
                'user_name' => $e->user->nickname ?? $e->user->name ?? '?',
                'avatar' => $e->user->avatar,
                'status' => $e->status,
                'is_online' => $e->is_online,
                'chips' => $e->chips,
                'finish_position' => $e->finish_position,
                'registered_at' => $e->created_at->format('H:i'),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'tournament' => $tournament,
                'entries' => $entries,
            ],
        ]);
    }

    // 토너먼트 참가 신청
    public function register(Request $request, $id)
    {
        $user = $request->user();
        $tournament = PokerTournament::findOrFail($id);

        if (!in_array($tournament->status, ['scheduled', 'registering'])) {
            return response()->json(['success' => false, 'message' => '참가 신청이 마감되었습니다.'], 422);
        }

        if ($tournament->entries()->count() >= $tournament->max_players) {
            return response()->json(['success' => false, 'message' => '정원이 초과되었습니다.'], 422);
        }

        // 이미 참가 중인지 체크
        if ($tournament->entries()->where('user_id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => '이미 참가 신청되어 있습니다.'], 422);
        }

        // 바이인 차감 (프리롤 제외)
        if ($tournament->buy_in > 0) {
            $wallet = PokerWallet::where('user_id', $user->id)->first();
            if (!$wallet || $wallet->chips_balance < $tournament->buy_in) {
                return response()->json(['success' => false, 'message' => "칩이 부족합니다. (필요: {$tournament->buy_in})"], 422);
            }
            $wallet->withdraw($tournament->buy_in, "토너먼트 참가: {$tournament->title}");
        }

        $entry = PokerTournamentEntry::create([
            'tournament_id' => $id,
            'user_id' => $user->id,
            'status' => 'registered',
            'is_online' => true,
            'last_seen_at' => now(),
            'chips' => $tournament->starting_chips,
        ]);

        // 실시간 알림
        broadcast(new PokerTournamentUpdate($tournament->fresh()->loadCount([
            'entries as registered_count',
            'entries as online_count' => fn($q) => $q->where('is_online', true),
        ])))->toOthers();

        return response()->json([
            'success' => true,
            'data' => $entry,
            'message' => '참가 신청 완료!',
        ]);
    }

    // 참가 취소
    public function unregister(Request $request, $id)
    {
        $user = $request->user();
        $entry = PokerTournamentEntry::where('tournament_id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'registered')
            ->first();

        if (!$entry) {
            return response()->json(['success' => false, 'message' => '참가 신청을 찾을 수 없습니다.'], 404);
        }

        $tournament = PokerTournament::findOrFail($id);

        // 바이인 환불
        if ($tournament->buy_in > 0) {
            $wallet = PokerWallet::firstOrCreate(
                ['user_id' => $user->id],
                ['chips_balance' => 0, 'total_deposited' => 0, 'total_withdrawn' => 0]
            );
            $wallet->deposit($tournament->buy_in, "토너먼트 취소 환불: {$tournament->title}");
        }

        $entry->delete();

        broadcast(new PokerTournamentUpdate($tournament->fresh()->loadCount([
            'entries as registered_count',
            'entries as online_count' => fn($q) => $q->where('is_online', true),
        ])))->toOthers();

        return response()->json(['success' => true, 'message' => '참가가 취소되었습니다.']);
    }

    // 온라인 상태 업데이트 (heartbeat)
    public function heartbeat(Request $request, $id)
    {
        $entry = PokerTournamentEntry::where('tournament_id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($entry) {
            $entry->update(['is_online' => true, 'last_seen_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    // ─── Admin: 토너먼트 생성 (일회성 or 반복 스케줄 템플릿) ───
    public function adminCreate(Request $request)
    {
        $isSchedule = $request->boolean('is_schedule');

        if ($isSchedule) {
            // 반복 스케줄 템플릿 생성
            $request->validate([
                'title' => 'required|string|max:100',
                'type' => 'in:freeroll,micro,regular,high_roller',
                'buy_in' => 'required|integer|min:0',
                'starting_chips' => 'required|integer|min:1000',
                'max_players' => 'required|integer|min:9|max:1000',
                'schedule_time' => 'required|string', // "18:00"
                'schedule_days' => 'required|array', // ["mon","tue",...]
            ]);

            // 템플릿만 저장 (토너먼트 목록에 안 보이도록 status=template)
            $template = PokerTournament::create([
                'title' => $request->title,
                'type' => $request->type ?? 'regular',
                'status' => 'template',
                'buy_in' => $request->buy_in,
                'starting_chips' => $request->starting_chips,
                'max_players' => $request->max_players,
                'min_players' => $request->min_players ?? 9,
                'scheduled_at' => Carbon::parse(Carbon::now('America/New_York')->format('Y-m-d') . ' ' . $request->schedule_time, 'America/New_York')->utc(),
                'registration_opens_at' => now(),
                'late_reg_levels' => $request->late_reg_levels ?? 3,
                'bounty_pct' => $request->bounty_pct ?? 10,
                'is_template' => true,
                'schedule_pattern' => [
                    'recurring' => true,
                    'days' => $request->schedule_days,
                    'time' => $request->schedule_time,
                ],
            ]);

            // 오늘/내일 토너먼트 즉시 생성
            $this->generateFromTemplate($template);

            return response()->json(['success' => true, 'data' => $template, 'message' => '반복 스케줄 등록 완료! 매일 자동 생성됩니다.']);
        }

        // 일회성 토너먼트 생성
        $request->validate([
            'title' => 'required|string|max:100',
            'type' => 'in:freeroll,micro,regular,high_roller',
            'buy_in' => 'required|integer|min:0',
            'starting_chips' => 'required|integer|min:1000',
            'max_players' => 'required|integer|min:9|max:1000',
            'min_players' => 'integer|min:2',
            'scheduled_at' => 'required|date',
        ]);

        // datetime-local은 시간대 없이 오므로 NY로 해석 후 UTC 변환
        $scheduledAt = Carbon::parse($request->scheduled_at, 'America/New_York')->utc();

        $tournament = PokerTournament::create([
            'title' => $request->title,
            'type' => $request->type ?? 'regular',
            'status' => 'scheduled',
            'buy_in' => $request->buy_in,
            'starting_chips' => $request->starting_chips,
            'max_players' => $request->max_players,
            'min_players' => $request->min_players ?? 9,
            'scheduled_at' => $scheduledAt,
            'registration_opens_at' => now(),
            'late_reg_levels' => $request->late_reg_levels ?? 3,
            'bounty_pct' => $request->bounty_pct ?? 10,
        ]);

        broadcast(new PokerLobbyUpdate())->toOthers();

        return response()->json(['success' => true, 'data' => $tournament, 'message' => '토너먼트가 생성되었습니다.']);
    }

    // 템플릿에서 오늘/내일 토너먼트 생성
    private function generateFromTemplate(PokerTournament $template)
    {
        $pattern = $template->schedule_pattern;
        if (!$pattern) return;

        $time = $pattern['time'] ?? '18:00';
        $days = $pattern['days'] ?? [];

        foreach ([Carbon::today('America/New_York'), Carbon::tomorrow('America/New_York')] as $date) {
            $dayShort = strtolower(substr($date->format('D'), 0, 3));
            if (!in_array($dayShort, $days)) continue;

            $scheduledAt = Carbon::parse($date->format('Y-m-d') . ' ' . $time, 'America/New_York')->utc();
            if ($scheduledAt->isPast()) continue;

            // 중복 체크 (UTC 기준)
            $exists = PokerTournament::where('title', $template->title)
                ->where('scheduled_at', $scheduledAt->format('Y-m-d H:i:s'))
                ->where('is_template', false)
                ->exists();

            if ($exists) continue;

            PokerTournament::create([
                'title' => $template->title,
                'type' => $template->type,
                'status' => 'scheduled',
                'buy_in' => $template->buy_in,
                'starting_chips' => $template->starting_chips,
                'max_players' => $template->max_players,
                'min_players' => $template->min_players,
                'scheduled_at' => $scheduledAt,
                'registration_opens_at' => now(),
                'late_reg_levels' => $template->late_reg_levels,
                'bounty_pct' => $template->bounty_pct,
                'is_template' => false,
            ]);
        }

        broadcast(new PokerLobbyUpdate())->toOthers();
    }

    // Admin: 토너먼트 목록 (템플릿 + 일반 분리)
    public function adminList()
    {
        $templates = PokerTournament::where('is_template', true)
            ->orderByDesc('created_at')
            ->get();

        $tournaments = PokerTournament::where('is_template', false)
            ->withCount('entries')
            ->orderByDesc('scheduled_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'templates' => $templates,
                'tournaments' => $tournaments,
            ],
        ]);
    }

    // Admin: 토너먼트 취소
    public function adminCancel($id)
    {
        $tournament = PokerTournament::findOrFail($id);
        if ($tournament->status === 'running') {
            return response()->json(['success' => false, 'message' => '진행 중인 토너먼트는 취소할 수 없습니다.'], 422);
        }

        // 참가자 전원 환불
        foreach ($tournament->entries as $entry) {
            if ($tournament->buy_in > 0) {
                $wallet = PokerWallet::firstOrCreate(
                    ['user_id' => $entry->user_id],
                    ['chips_balance' => 0, 'total_deposited' => 0, 'total_withdrawn' => 0]
                );
                $wallet->deposit($tournament->buy_in, "토너먼트 취소 환불: {$tournament->title}");
            }
        }

        $tournament->update(['status' => 'cancelled']);
        $tournament->entries()->delete();

        return response()->json(['success' => true, 'message' => '토너먼트가 취소되었습니다.']);
    }
}
