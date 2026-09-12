<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\Request;

class UserBlockController extends Controller
{
    /**
     * 내가 차단한 사용자 목록 — 이 엔드포인트가 없어 "차단" UI가 실제
     * UserBlock 대신 아무도 확인하지 않는 Friend.status='blocked'를
     * 대신 읽고 있던 문제(3갈래로 흩어진 차단 기능 중 하나)의 근본 원인.
     */
    public function index(Request $request)
    {
        $blocked = UserBlock::where('blocker_id', $request->user()->id)
            ->with('blocked:id,name,nickname,avatar')
            ->orderByDesc('created_at')
            ->get()
            ->pluck('blocked')
            ->filter()
            ->values();

        return response()->json(['success' => true, 'data' => $blocked]);
    }

    /**
     * Block a user.
     */
    public function block(Request $request, User $user)
    {
        UserBlock::firstOrCreate([
            'blocker_id' => $request->user()->id,
            'blocked_id' => $user->id,
        ]);
        return response()->json(['blocked' => true]);
    }

    /**
     * Unblock a user.
     */
    public function unblock(Request $request, User $user)
    {
        UserBlock::where('blocker_id', $request->user()->id)
            ->where('blocked_id', $user->id)
            ->delete();
        return response()->json(['blocked' => false]);
    }
}
