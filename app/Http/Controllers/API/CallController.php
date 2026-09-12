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
    /**
     * Initiate a new call.
     */
    public function initiate(Request $request)
    {
        $request->validate(['callee_id' => 'required|exists:users,id']);
        $calleeId = $request->callee_id;
        $callerId = $request->user()->id;

        // 상대가 나를 차단한 경우만 확인하고 내가 상대를 차단한 경우는 걸러지지
        // 않던 문제 수정 — 쪽지/대화(Conversation)와 동일하게 양방향으로 확인.
        if (UserBlock::isBlocked($calleeId, $callerId) || UserBlock::isBlocked($callerId, $calleeId)) {
            return response()->json(['error' => '통화할 수 없는 사용자입니다.'], 403);
        }

        $roomId = 'sk-' . uniqid('', true);
        $callType = $request->call_type ?? 'friend'; // friend 또는 elder
        $call = Call::create([
            'room_id'   => $roomId,
            'caller_id' => $callerId,
            'callee_id' => $calleeId,
            'call_type' => $callType,
            'status'    => 'ringing',
        ]);

        try { broadcast(new CallInitiated($call)); } catch (\Throwable $e) {
            \Log::warning('[CALL] broadcast CallInitiated failed: ' . $e->getMessage());
        }

        // FCM push (stub — logs warning until Firebase is installed)
        $callee = User::find($calleeId);
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

        return response()->json(['room_id' => $roomId, 'call_id' => $call->id]);
    }

    /**
     * Answer an incoming call.
     */
    public function answer(Request $request, Call $call)
    {
        \Log::info('[CALL] Answer API called', ['call_id' => $call->id, 'user_id' => $request->user()->id, 'callee_id' => $call->callee_id]);
        abort_unless($call->callee_id === $request->user()->id, 403);
        $call->answer();
        \Log::info('[CALL] Call answered OK', ['call_id' => $call->id]);
        try {
            broadcast(new CommWebRtcSignal($call->caller_id, $call->room_id, 'call-answered', []));
            broadcast(new CommWebRtcSignal($call->callee_id, $call->room_id, 'call-answered-elsewhere', ['call_id' => $call->id]));
        } catch (\Throwable $e) {
            \Log::warning('[CALL] broadcast answer failed: ' . $e->getMessage());
        }
        return response()->json(['status' => 'answered']);
    }

    /**
     * End a call.
     */
    public function end(Request $request, Call $call)
    {
        abort_unless(
            in_array($request->user()->id, [$call->caller_id, $call->callee_id]),
            403
        );
        $wasMissed = !$call->answered_at;
        $call->end();
        $otherId = $call->caller_id === $request->user()->id
            ? $call->callee_id
            : $call->caller_id;
        try {
            broadcast(new CommWebRtcSignal($otherId, $call->room_id, 'call-ended', []));
        } catch (\Throwable $e) {
            \Log::warning('[CALL] broadcast end failed: ' . $e->getMessage());
        }

        // 부재중 후속알림이 전혀 없어 FCM 토큰이 없으면 놓친 전화를 전혀 알
        // 수 없던 문제 수정 — 응답 없이 종료되면 받는 사람에게 인앱 알림 발송.
        // (status 컬럼 값 자체는 폴링 로직이 'ended'를 종료 신호로 쓰고 있어 그대로 둠)
        if ($wasMissed) {
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

        return response()->json(['status' => 'ended', 'duration' => $call->duration_formatted ?? '00:00']);
    }

    /**
     * Forward a WebRTC signaling message (offer/answer/ice-candidate).
     */
    public function signal(Request $request)
    {
        $request->validate([
            'target_user_id' => 'required|integer',
            'room_id'        => 'required|string',
            'type'           => 'required|in:offer,answer,ice-candidate,call-ended',
            'payload'        => 'present|array',
        ]);
        \Log::info('[SIGNAL] ' . $request->type, [
            'from' => $request->user()->id,
            'to' => $request->target_user_id,
            'room' => $request->room_id,
        ]);
        try {
            broadcast(new CommWebRtcSignal(
                $request->target_user_id,
                $request->room_id,
                $request->type,
                $request->payload ?? []
            ));
        } catch (\Throwable $e) {
            \Log::warning('[SIGNAL] broadcast failed: ' . $e->getMessage());
        }
        return response()->json(['ok' => true]);
    }

    /**
     * Debug log from client (temporary).
     */
    public function clientLog(Request $request)
    {
        \Log::info('[CLIENT] ' . ($request->message ?? ''), $request->only(['data']));
        return response()->json(['ok' => true]);
    }

    /**
     * Get call history for the authenticated user.
     */
    public function status(Call $call)
    {
        return response()->json(['status' => $call->status, 'duration' => $call->duration]);
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
