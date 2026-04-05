<?php

namespace App\Http\Controllers\API;

use App\Events\WebRTCSignal;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CallController extends Controller
{
    // 통화 요청 / SDP 전달 / ICE 전달 / 통화 종료 모두 이 엔드포인트로 처리
    public function signal(Request $request)
    {
        $request->validate([
            'type'        => 'required|in:call-request,call-end,offer,answer,ice-candidate',
            'to_user_id'  => 'required|integer',
            'call_id'     => 'nullable|string',
            'payload'     => 'nullable|array',
        ]);

        $callId = $request->call_id ?? Str::uuid()->toString();

        broadcast(new WebRTCSignal(
            type:       $request->type,
            payload:    $request->payload ?? [],
            fromUserId: Auth::id(),
            toUserId:   $request->to_user_id,
            callId:     $callId,
        ));

        return response()->json(['call_id' => $callId]);
    }

    // 통화 요청 (알림만 발송, WebRTC 준비 전 임시)
    public function requestCall(Request $request)
    {
        $request->validate(['receiver_id' => 'required|exists:users,id']);

        $user = Auth::user();

        DB::table('notifications')->insert([
            'user_id'    => $request->receiver_id,
            'type'       => 'call_request',
            'title'      => '전화가 왔습니다',
            'content'       => $user->name . '님이 전화를 걸었습니다.',
            'url'        => '/chat',
            'is_read'    => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => '통화 기능은 준비 중입니다. 채팅을 이용해주세요.',
            'status'  => 'pending',
        ]);
    }
}
