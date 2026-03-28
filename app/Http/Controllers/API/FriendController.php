<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Friend;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FriendController extends Controller
{
    /**
     * Return the authenticated user's accepted friends with basic profile info.
     */
    public function myFriends()
    {
        $userId = Auth::id();

        $friends = Friend::where('status', 'accepted')
            ->where(function ($q) use ($userId) {
                $q->where('requester_id', $userId)
                  ->orWhere('recipient_id', $userId);
            })
            ->with([
                'requester:id,name,username,avatar,region,level',
                'recipient:id,name,username,avatar,region,level',
            ])
            ->get()
            ->map(function ($friend) use ($userId) {
                // Return the other person's info, not the auth user's
                return $friend->requester_id === $userId
                    ? $friend->recipient
                    : $friend->requester;
            });

        return response()->json(['data' => $friends]);
    }

    /**
     * Send a friend request to another user.
     */
    public function sendRequest(int $userId)
    {
        $authId = Auth::id();

        if ($authId === $userId) {
            return response()->json(['message' => '자기 자신에게 친구 요청을 보낼 수 없습니다.'], 422);
        }

        $target = User::find($userId);
        if (!$target) {
            return response()->json(['message' => '사용자를 찾을 수 없습니다.'], 404);
        }

        // Check if any relationship already exists in either direction
        $exists = Friend::where(function ($q) use ($authId, $userId) {
                $q->where('requester_id', $authId)->where('recipient_id', $userId);
            })
            ->orWhere(function ($q) use ($authId, $userId) {
                $q->where('requester_id', $userId)->where('recipient_id', $authId);
            })
            ->exists();

        if ($exists) {
            return response()->json(['message' => '이미 친구 관계이거나 요청이 존재합니다.'], 422);
        }

        Friend::create([
            'requester_id' => $authId,
            'recipient_id' => $userId,
            'status'       => 'pending',
        ]);

        return response()->json(['message' => '친구 요청을 보냈습니다.'], 201);
    }

    /**
     * Accept an incoming friend request from $userId.
     */
    public function acceptRequest(int $userId)
    {
        $authId = Auth::id();

        $friend = Friend::where('requester_id', $userId)
            ->where('recipient_id', $authId)
            ->where('status', 'pending')
            ->firstOrFail();

        $friend->update(['status' => 'accepted']);

        return response()->json(['message' => '친구 요청을 수락했습니다.']);
    }

    /**
     * Reject an incoming friend request from $userId.
     */
    public function rejectRequest(int $userId)
    {
        $authId = Auth::id();

        $friend = Friend::where('requester_id', $userId)
            ->where('recipient_id', $authId)
            ->where('status', 'pending')
            ->firstOrFail();

        $friend->update(['status' => 'rejected']);

        return response()->json(['message' => '친구 요청을 거절했습니다.']);
    }

    /**
     * Remove an accepted friendship (either direction).
     */
    public function removeFriend(int $userId)
    {
        $authId = Auth::id();

        $deleted = Friend::where(function ($q) use ($authId, $userId) {
                $q->where('requester_id', $authId)->where('recipient_id', $userId);
            })
            ->orWhere(function ($q) use ($authId, $userId) {
                $q->where('requester_id', $userId)->where('recipient_id', $authId);
            })
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => '친구 관계를 찾을 수 없습니다.'], 404);
        }

        return response()->json(['message' => '친구를 삭제했습니다.']);
    }

    /**
     * Return incoming pending friend requests for the authenticated user.
     */
    public function pendingRequests()
    {
        $authId = Auth::id();

        $requests = Friend::where('recipient_id', $authId)
            ->where('status', 'pending')
            ->with('requester:id,name,username,avatar,region,level')
            ->get()
            ->map(function ($friend) {
                return array_merge(
                    $friend->requester->toArray(),
                    ['friend_id' => $friend->id, 'requested_at' => $friend->created_at]
                );
            });

        return response()->json(['data' => $requests]);
    }

    /**
     * Search users by name or username, excluding existing friends and self.
     */
    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string|min:1|max:50']);

        $authId = Auth::id();
        $query  = $request->input('q');

        // Gather IDs of users already in any friend relationship with auth user
        $friendIds = Friend::where('requester_id', $authId)
            ->orWhere('recipient_id', $authId)
            ->get()
            ->flatMap(fn($f) => [$f->requester_id, $f->recipient_id])
            ->unique()
            ->push($authId) // exclude self
            ->values();

        $users = User::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('username', 'like', "%{$query}%");
            })
            ->whereNotIn('id', $friendIds)
            ->select('id', 'name', 'username', 'avatar', 'region', 'level')
            ->limit(10)
            ->get();

        return response()->json(['data' => $users]);
    }
}
