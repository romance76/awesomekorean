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
     * GET /api/friends
     * List friends with status filter
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        $status = $request->input('status', 'accepted');

        $query = Friend::where('status', $status)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhere('friend_id', $userId);
            })
            ->with([
                'user:id,name,nickname,avatar',
                'friend:id,name,nickname,avatar',
            ]);

        $friends = $query->get()->map(function ($friend) use ($userId) {
            $other = $friend->user_id === $userId
                ? $friend->friend
                : $friend->user;
            if ($other && $other->avatar) {
                $other->avatar = asset('storage/' . $other->avatar);
            }
            return $other;
        })->filter();

        return response()->json(['success' => true, 'data' => $friends->values()]);
    }

    /**
     * POST /api/friends/request/{userId}
     * Send friend request
     */
    public function sendRequest(int $userId)
    {
        $authId = Auth::id();

        if ($authId === $userId) {
            return response()->json(['success' => false, 'message' => '자기 자신에게 친구 요청을 보낼 수 없습니다.'], 422);
        }

        $target = User::find($userId);
        if (!$target) {
            return response()->json(['success' => false, 'message' => '사용자를 찾을 수 없습니다.'], 404);
        }

        // Check existing relationship in either direction
        $exists = Friend::where(function ($q) use ($authId, $userId) {
                $q->where('user_id', $authId)->where('friend_id', $userId);
            })
            ->orWhere(function ($q) use ($authId, $userId) {
                $q->where('user_id', $userId)->where('friend_id', $authId);
            })
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => '이미 친구 관계이거나 요청이 존재합니다.'], 422);
        }

        Friend::create([
            'user_id' => $authId,
            'friend_id' => $userId,
            'status'       => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => '친구 요청을 보냈습니다.',
        ], 201);
    }

    /**
     * POST /api/friends/accept/{userId}
     * Accept friend request
     */
    public function accept(int $userId)
    {
        $friend = Friend::where('user_id', $userId)
            ->where('friend_id', Auth::id())
            ->where('status', 'pending')
            ->firstOrFail();

        $friend->update(['status' => 'accepted']);

        return response()->json(['success' => true, 'message' => '친구 요청을 수락했습니다.']);
    }

    /**
     * POST /api/friends/reject/{userId}
     * Reject friend request
     */
    public function reject(int $userId)
    {
        $friend = Friend::where('user_id', $userId)
            ->where('friend_id', Auth::id())
            ->where('status', 'pending')
            ->firstOrFail();

        $friend->delete();

        return response()->json(['success' => true, 'message' => '친구 요청을 거절했습니다.']);
    }

    /**
     * POST /api/friends/block/{userId}
     * Block user
     */
    public function block(int $userId)
    {
        $authId = Auth::id();

        // Remove existing friendship
        Friend::where(function ($q) use ($authId, $userId) {
                $q->where('user_id', $authId)->where('friend_id', $userId);
            })
            ->orWhere(function ($q) use ($authId, $userId) {
                $q->where('user_id', $userId)->where('friend_id', $authId);
            })
            ->delete();

        // Create blocked relationship
        Friend::create([
            'user_id' => $authId,
            'friend_id' => $userId,
            'status'       => 'blocked',
        ]);

        return response()->json(['success' => true, 'message' => '사용자를 차단했습니다.']);
    }

    /**
     * DELETE /api/friends/{userId}
     * Remove friend
     */
    public function remove(int $userId)
    {
        $authId = Auth::id();

        $deleted = Friend::where(function ($q) use ($authId, $userId) {
                $q->where('user_id', $authId)->where('friend_id', $userId);
            })
            ->orWhere(function ($q) use ($authId, $userId) {
                $q->where('user_id', $userId)->where('friend_id', $authId);
            })
            ->delete();

        if (!$deleted) {
            return response()->json(['success' => false, 'message' => '친구 관계를 찾을 수 없습니다.'], 404);
        }

        return response()->json(['success' => true, 'message' => '친구를 삭제했습니다.']);
    }

    /**
     * GET /api/friends/pending
     * Incoming pending requests
     */
    public function pendingRequests()
    {
        $requests = Friend::where('friend_id', Auth::id())
            ->where('status', 'pending')
            ->with('user:id,name,nickname,avatar')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($friend) {
                $user = $friend->user;
                if ($user && $user->avatar) {
                    $user->avatar = asset('storage/' . $user->avatar);
                }
                return [
                    'id'           => $friend->id,
                    'user_id' => $friend->user_id,
                    'requester'    => $user,
                    'requested_at' => $friend->created_at,
                ];
            });

        return response()->json(['success' => true, 'data' => $requests]);
    }

    /**
     * GET /api/friends/sent
     * Outgoing pending requests
     */
    public function sentRequests()
    {
        $requests = Friend::where('user_id', Auth::id())
            ->where('status', 'pending')
            ->with('friend:id,name,nickname,avatar')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($friend) {
                $user = $friend->friend;
                if ($user && $user->avatar) {
                    $user->avatar = asset('storage/' . $user->avatar);
                }
                return [
                    'id'           => $friend->id,
                    'friend_id' => $friend->friend_id,
                    'recipient'    => $user,
                    'requested_at' => $friend->created_at,
                ];
            });

        return response()->json(['success' => true, 'data' => $requests]);
    }

    /**
     * GET /api/friends/check/{userId}
     * Check friendship status
     */
    public function checkFriendship(int $userId)
    {
        $authId = Auth::id();

        if ($authId === $userId) {
            return response()->json(['success' => true, 'data' => ['status' => 'self']]);
        }

        $sent = Friend::where('user_id', $authId)
            ->where('friend_id', $userId)
            ->first();

        if ($sent) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'status'    => $sent->status === 'accepted' ? 'friends' : ($sent->status === 'blocked' ? 'blocked' : 'request_sent'),
                    'friend_id' => $sent->id,
                ],
            ]);
        }

        $received = Friend::where('user_id', $userId)
            ->where('friend_id', $authId)
            ->first();

        if ($received) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'status'    => $received->status === 'accepted' ? 'friends' : 'request_received',
                    'friend_id' => $received->id,
                ],
            ]);
        }

        return response()->json(['success' => true, 'data' => ['status' => 'none']]);
    }

    /**
     * GET /api/friends/search
     * Search users for friend requests
     */
    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string|min:1|max:50']);

        $authId = Auth::id();
        $query = $request->input('q');

        // Gather existing relationship IDs
        $friendIds = Friend::where('user_id', $authId)
            ->orWhere('friend_id', $authId)
            ->get()
            ->flatMap(fn($f) => [$f->user_id, $f->friend_id])
            ->unique()
            ->push($authId)
            ->values();

        $users = User::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('nickname', 'like', "%{$query}%");
            })
            ->whereNotIn('id', $friendIds)
            ->select('id', 'name', 'nickname', 'avatar')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                if ($user->avatar) {
                    $user->avatar = asset('storage/' . $user->avatar);
                }
                return $user;
            });

        return response()->json(['success' => true, 'data' => $users]);
    }
}
