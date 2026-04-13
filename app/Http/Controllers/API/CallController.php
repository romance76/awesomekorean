<?php

namespace App\Http\Controllers\API;

use App\Events\CallInitiated;
use App\Events\CommWebRtcSignal;
use App\Http\Controllers\Controller;
use App\Models\Call;
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

        if (UserBlock::isBlocked($calleeId, $callerId)) {
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
        $call->end();
        $otherId = $call->caller_id === $request->user()->id
            ? $call->callee_id
            : $call->caller_id;
        try {
            broadcast(new CommWebRtcSignal($otherId, $call->room_id, 'call-ended', []));
        } catch (\Throwable $e) {
            \Log::warning('[CALL] broadcast end failed: ' . $e->getMessage());
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
