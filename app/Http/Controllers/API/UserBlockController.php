<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\Request;

class UserBlockController extends Controller
{
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
