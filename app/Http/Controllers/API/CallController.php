<?php

namespace App\Http\Controllers\API;

use App\Events\CallInitiated;
use App\Events\CommWebRtcSignal;
use App\Events\NewNotification;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;

class CallController extends Controller
{
    private const END_REASONS = ['completed', 'declined', 'cancelled', 'no_answer', 'failed'];

    private function ua(Request $request): string
    {
        return mb_substr((string) $request->userAgent(), 0, 160);
    }

    /** 이 사람이 이 통화의 참여자인지 + 상대방 id */
    private function otherParty(Call $call, int $userId): ?int
    {
        if ($call->caller_id === $userId) return $call->callee_id;
        if ($call->callee_id === $userId) return $call->caller_id;
        return null;
    }

    /** 통화 중이라 새 전화를 받을 수 없는 상태인지 (응답 후 끝나지 않은 통화만. 벨 울리는 중은 제외) */
    private function inActiveCall(int $userId): bool
    {
        return Call::where('status', 'answered')
            ->where('answered_at', '>=', now()->subHours(4))
            ->where(fn($q) => $q->where('caller_id', $userId)->orWhere('callee_id', $userId))
            ->exists();
    }

    /**
     * Initiate a new call.
     * 상대가 지금 접속 중이 아니고(푸시 알림 등록도 없고) / 이미 통화 중이면 바로 알려주고 기록만 남긴다.
     */
    public function initiate(Request $request)
    {
        $request->validate(['callee_id' => 'required|exists:users,id', 'device_id' => 'nullable|string|max:16']);
        $calleeId = (int) $request->callee_id;
        $callerId = $request->user()->id;
        $callType = $request->call_type ?? 'friend'; // friend 또는 elder

        if ($calleeId === $callerId) {
            return response()->json(['error' => '자기 자신에게는 전화할 수 없어요.'], 422);
        }

        // 상대가 나를 차단한 경우만 확인하고 내가 상대를 차단한 경우는 걸러지지
        // 않던 문제 수정 — 쪽지/대화(Conversation)와 동일하게 양방향으로 확인.
        if (UserBlock::isBlocked($calleeId, $callerId) || UserBlock::isBlocked($callerId, $calleeId)) {
            return response()->json(['error' => '통화할 수 없는 사용자입니다.'], 403);
        }

        $callee = User::find($calleeId);
        $roomId = 'sk-' . uniqid('', true);
        $base = [
            'room_id' => $roomId, 'caller_id' => $callerId, 'callee_id' => $calleeId, 'call_type' => $callType,
            'caller_device' => $request->device_id, 'caller_ua' => $this->ua($request),
        ];

        // 걸려는 사람이 이미 통화 중 / 받는 사람이 통화 중 → 기록만 남기고 거절
        foreach ([[$callerId, 'caller_busy', '이미 다른 통화 중이에요.'], [$calleeId, 'busy', '상대가 지금 통화 중이에요.']] as [$uid, $why, $msg]) {
            if ($this->inActiveCall($uid)) {
                $c = Call::create($base + ['status' => 'missed', 'end_reason' => 'busy', 'ended_at' => now(), 'ended_by' => $callerId, 'failure_note' => $why]);
                return response()->json(['error' => $msg, 'code' => 'busy', 'call_id' => $c->id], 409);
            }
        }

        // 상대가 사이트를 열어 두지 않았고(접속 신호 없음) 알림 받을 기기도 없으면 → 바로 알려준다
        $online = \Illuminate\Support\Facades\Cache::has('user-online-' . $calleeId);
        if (!$online && !$callee?->fcm_token) {
            $c = Call::create($base + ['status' => 'missed', 'end_reason' => 'offline', 'ended_at' => now(), 'ended_by' => $callerId]);
            try {
                Notification::create([
                    'user_id' => $calleeId, 'type' => 'call_missed', 'title' => '부재중 전화',
                    'content' => ($request->user()->nickname ?? $request->user()->name ?? '상대방') . '님에게서 전화가 왔었습니다.',
                    'data' => ['call_id' => $c->id, 'caller_id' => $callerId],
                ]);
            } catch (\Throwable $e) {}
            return response()->json(['error' => '상대가 지금 접속 중이 아니에요. 부재중 알림을 남겼어요.', 'code' => 'offline', 'call_id' => $c->id], 409);
        }

        $call = Call::create($base + ['status' => 'ringing']);

        try { broadcast(new CallInitiated($call)); } catch (\Throwable $e) {
            \Log::warning('[CALL] broadcast CallInitiated failed: ' . $e->getMessage());
        }

        // FCM push (사이트가 닫혀 있어도 알림)
        if ($callee?->fcm_token) {
            app(PushNotificationService::class)->sendIncomingCall(
                fcmToken:     $callee->fcm_token,
                callId:       $call->id,
                roomId:       $roomId,
                callerId:     $callerId,
                callerName:   $request->user()->name,
                callerAvatar: $request->user()->avatar ?? '',
            );
        }

        return response()->json(['room_id' => $roomId, 'call_id' => $call->id, 'callee_online' => $online]);
    }

    /**
     * Answer an incoming call. 먼저 받은 기기 하나에만 연결된다 (나머지 기기는 벨이 멈춘다).
     */
    public function answer(Request $request, Call $call)
    {
        $request->validate(['device_id' => 'nullable|string|max:16']);
        abort_unless($call->callee_id === $request->user()->id, 403);

        if ($call->status !== 'ringing') {
            $msg = $call->status === 'answered' ? '다른 기기에서 이미 받은 전화예요.' : '이미 끝난 전화예요.';
            return response()->json(['error' => $msg, 'code' => $call->status], 409);
        }
        $call->answer($request->device_id, $this->ua($request));
        try {
            // 거는 쪽에는 "누가(어느 기기가) 받았는지" 알려주고, 받는 사람의 다른 기기는 벨을 멈춘다
            broadcast(new CommWebRtcSignal($call->caller_id, $call->room_id, 'call-answered', ['device_id' => $request->device_id]));
            broadcast(new CommWebRtcSignal($call->callee_id, $call->room_id, 'call-answered-elsewhere', ['call_id' => $call->id, 'device_id' => $request->device_id]));
        } catch (\Throwable $e) {
            \Log::warning('[CALL] broadcast answer failed: ' . $e->getMessage());
        }
        return response()->json(['status' => 'answered', 'caller_device' => $call->caller_device]);
    }

    /**
     * End a call. reason: completed | declined | cancelled | no_answer | failed
     */
    public function end(Request $request, Call $call)
    {
        $request->validate(['reason' => 'nullable|in:' . implode(',', self::END_REASONS), 'note' => 'nullable|string|max:255']);
        $me = $request->user()->id;
        $other = $this->otherParty($call, $me);
        abort_unless($other !== null, 403);

        if (!$call->isActive()) {   // 이미 끝난 통화 — 중복 호출은 그냥 성공 처리
            return response()->json(['status' => $call->status, 'duration' => $call->duration_formatted ?? '00:00']);
        }

        $wasMissed = !$call->answered_at;
        $reason = $request->reason;
        if (!$reason) $reason = $wasMissed ? ($me === $call->callee_id ? 'declined' : 'cancelled') : 'completed';
        // 받는 사람이 끊은 거면 "거절", 받기 전에 거는 사람이 끊은 거면 취소/응답 없음
        if ($wasMissed && $me === $call->callee_id && $reason !== 'failed') $reason = 'declined';
        $call->end($reason, $me, $request->note);

        try {
            broadcast(new CommWebRtcSignal($other, $call->room_id, 'call-ended', ['reason' => $reason]));
            if ($me === $call->callee_id) {
                // 받는 사람이 거절/종료한 경우, 받는 사람의 다른 기기들도 벨을 멈추도록
                broadcast(new CommWebRtcSignal($call->callee_id, $call->room_id, 'call-ended', ['reason' => $reason]));
            }
        } catch (\Throwable $e) {
            \Log::warning('[CALL] broadcast end failed: ' . $e->getMessage());
        }

        // 부재중 알림: 받기 전에 거는 쪽이 끊었거나(취소/응답 없음) 시간이 지난 경우 (거절한 경우는 제외)
        if ($wasMissed && $me === $call->caller_id && in_array($reason, ['cancelled', 'no_answer'], true) && $call->call_type !== 'elder') {
            try {
                $caller = User::find($call->caller_id);
                Notification::create([
                    'user_id' => $call->callee_id,
                    'type' => 'call_missed',
                    'title' => '부재중 전화',
                    'content' => ($caller->nickname ?? $caller->name ?? '상대방') . '님에게서 전화가 왔었습니다.',
                    'data' => ['call_id' => $call->id, 'caller_id' => $call->caller_id],
                ]);
                $unread = Notification::where('user_id', $call->callee_id)->whereNull('read_at')->count();
                broadcast(new NewNotification($call->callee_id, $unread, '부재중 전화'))->toOthers();
            } catch (\Exception $e) {}
        }

        return response()->json(['status' => $call->status, 'duration' => $call->duration_formatted ?? '00:00']);
    }

    /**
     * Forward a WebRTC signaling message (offer/answer/ice-candidate).
     * 이 통화의 참여자끼리만 주고받을 수 있다 (예전엔 로그인한 누구나 아무에게나 신호를 보낼 수 있었음).
     */
    public function signal(Request $request)
    {
        $request->validate([
            'target_user_id' => 'required|integer',
            'room_id'        => 'required|string|max:64',
            'type'           => 'required|in:offer,answer,ice-candidate',
            'payload'        => 'present|array',
        ]);
        $me = $request->user()->id;
        $call = Call::where('room_id', $request->room_id)->first();
        if (!$call || !$call->isActive() || $this->otherParty($call, $me) !== (int) $request->target_user_id) {
            return response()->json(['ok' => false, 'error' => '유효하지 않은 통화예요.'], 403);
        }
        try {
            broadcast(new CommWebRtcSignal(
                (int) $request->target_user_id,
                $request->room_id,
                $request->type,
                $request->payload ?? []
            ));
        } catch (\Throwable $e) {
            \Log::warning('[SIGNAL] broadcast failed: ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => '신호 전달 실패'], 503);
        }
        return response()->json(['ok' => true]);
    }

    /** POST /calls/{call}/report — 통화가 연결된 뒤 클라이언트가 측정한 연결 방식/지연을 남긴다 */
    public function report(Request $request, Call $call)
    {
        $request->validate(['conn_type' => 'nullable|in:direct,relay', 'rtt_ms' => 'nullable|integer|min:0|max:60000', 'note' => 'nullable|string|max:255']);
        abort_unless($this->otherParty($call, $request->user()->id) !== null, 403);
        $upd = array_filter([
            'conn_type' => $request->conn_type, 'rtt_ms' => $request->rtt_ms,
        ], fn($v) => $v !== null);
        if ($request->note) $upd['failure_note'] = mb_substr($request->note, 0, 255);
        if ($upd) $call->update($upd);
        return response()->json(['ok' => true]);
    }

    /**
     * GET /comms/calls/ringing — 지금 나에게 벨이 울리고 있는 전화.
     * 휴대폰 화면이 꺼져 있거나 알림을 눌러 사이트를 막 연 경우처럼 실시간 신호(벨)를 놓쳤을 때 확인하는 용도.
     */
    public function ringing(Request $request)
    {
        $call = Call::with('caller:id,name,avatar')->where('callee_id', $request->user()->id)->where('status', 'ringing')
            ->where('created_at', '>=', now()->subSeconds(60))->orderByDesc('id')->first();
        if (!$call) return response()->json(['call' => null]);
        return response()->json(['call' => [
            'call_id' => $call->id, 'room_id' => $call->room_id, 'caller_id' => $call->caller_id,
            'caller_name' => $call->call_type === 'elder' ? '안심서비스' : ($call->caller->name ?? '알 수 없음'),
            'caller_avatar' => $call->call_type === 'elder' ? '' : ($call->caller->avatar ?? ''),
            'call_type' => $call->call_type ?? 'friend',
        ]]);
    }

    /** GET /comms/ice-servers — STUN/TURN 설정 (서버에서 내려주므로 바꿀 때 사이트를 다시 만들 필요가 없음) */
    public function iceServers()
    {
        $servers = [['urls' => 'stun:stun.l.google.com:19302']];
        $turn = config('services.turn');
        if (!empty($turn['host']) && !empty($turn['password'])) {   // 비밀번호는 서버 .env(TURN_PASSWORD)에만 둔다
            $servers[] = ['urls' => "turn:{$turn['host']}", 'username' => $turn['username'], 'credential' => $turn['password']];
            $servers[] = ['urls' => "turn:{$turn['host']}?transport=tcp", 'username' => $turn['username'], 'credential' => $turn['password']];
        }
        return response()->json(['iceServers' => $servers]);
    }

    /**
     * Debug log from client (temporary).
     */
    public function clientLog(Request $request)
    {
        \Log::info('[CLIENT] ' . ($request->message ?? ''), $request->only(['data']));
        return response()->json(['ok' => true]);
    }

    /** 통화 상태 조회 (참여자만) — 이벤트를 놓쳤을 때를 대비한 폴링용 */
    public function status(Request $request, Call $call)
    {
        abort_unless($this->otherParty($call, $request->user()->id) !== null, 403);
        return response()->json([
            'status' => $call->status, 'duration' => $call->duration, 'end_reason' => $call->end_reason,
            'callee_device' => $call->callee_device, 'caller_device' => $call->caller_device,
        ]);
    }

    public function history(Request $request)
    {
        $userId = $request->user()->id;
        $calls = Call::with(['caller', 'callee'])
            ->where('caller_id', $userId)
            ->orWhere('callee_id', $userId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($call) use ($userId) {
                $isCaller = $call->caller_id === $userId;
                $partner  = $isCaller ? $call->callee : $call->caller;
                return [
                    'id'             => $call->id,
                    'direction'      => $isCaller ? 'outgoing' : 'incoming',
                    'call_type'      => $call->call_type ?? 'friend',
                    'status'         => $call->status,
                    'end_reason'     => $call->end_reason,
                    'partner_name'   => $partner->name ?? '알 수 없음',
                    'partner_avatar' => $partner->avatar,
                    'duration'       => $call->duration_formatted,
                    'duration_sec'   => $call->duration ?? 0,
                    'created_at'     => $call->created_at->toISOString(),
                ];
            });
        return response()->json($calls);
    }
}
