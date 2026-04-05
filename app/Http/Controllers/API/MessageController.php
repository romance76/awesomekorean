<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    /**
     * GET /api/messages
     * List DMs grouped by conversation
     */
    public function index(Request $request)
    {
        $userId = Auth::id();

        // Get all conversations grouped by the other user
        $conversations = Message::where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                  ->orWhere('receiver_id', $userId);
            })
            ->where(function ($q) use ($userId) {
                // Exclude messages deleted by this user
                $q->where(function ($q2) use ($userId) {
                    $q2->where('sender_id', $userId)->whereNotIn('status', ['deleted_by_sender']);
                })->orWhere(function ($q2) use ($userId) {
                    $q2->where('receiver_id', $userId)->whereNotIn('status', ['deleted_by_receiver']);
                });
            })
            ->with(['sender:id,name,nickname,avatar', 'receiver:id,name,nickname,avatar'])
            ->orderByDesc('created_at')
            ->get();

        // Group by conversation partner
        $grouped = $conversations->groupBy(function ($msg) use ($userId) {
            return $msg->sender_id === $userId ? $msg->receiver_id : $msg->sender_id;
        })->map(function ($messages, $partnerId) use ($userId) {
            $lastMessage = $messages->first();
            $partner = $lastMessage->sender_id === $userId
                ? $lastMessage->receiver
                : $lastMessage->sender;

            $unreadCount = $messages->where('receiver_id', $userId)
                ->where('is_read', false)
                ->count();

            return [
                'partner'       => $partner,
                'last_message'  => $lastMessage,
                'unread_count'  => $unreadCount,
                'total_count'   => $messages->count(),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $grouped]);
    }

    /**
     * GET /api/messages/inbox
     * Simple inbox view (received messages)
     */
    public function inbox()
    {
        $messages = Message::where('receiver_id', Auth::id())
            ->whereIn('status', ['active', 'deleted_by_sender'])
            ->with('sender:id,name,nickname,avatar')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $messages]);
    }

    /**
     * POST /api/messages
     * Send DM
     */
    public function store(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'content'     => 'required|string|max:2000',
        ]);

        if ((int) $request->receiver_id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => '자신에게는 메시지를 보낼 수 없습니다.',
            ], 400);
        }

        $message = Message::create([
            'sender_id'   => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'content'     => $request->content,
        ]);

        return response()->json([
            'success' => true,
            'message' => '메시지를 보냈습니다.',
            'data'    => $message->load(['sender:id,name,nickname,avatar', 'receiver:id,name,nickname,avatar']),
        ], 201);
    }

    /**
     * GET /api/messages/{id}
     * Show single message and mark as read
     */
    public function show($id)
    {
        $message = Message::with(['sender:id,name,nickname,avatar', 'receiver:id,name,nickname,avatar'])
            ->findOrFail($id);

        if ($message->receiver_id !== Auth::id() && $message->sender_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다.'], 403);
        }

        // Mark as read if receiver views it
        if ($message->receiver_id === Auth::id() && !$message->is_read) {
            $message->update(['is_read' => true, 'read_at' => now()]);
        }

        return response()->json(['success' => true, 'data' => $message]);
    }

    /**
     * GET /api/messages/conversation/{userId}
     * Get conversation with a specific user
     */
    public function conversation($userId)
    {
        $authId = Auth::id();

        $messages = Message::where(function ($q) use ($authId, $userId) {
                $q->where('sender_id', $authId)->where('receiver_id', $userId);
            })
            ->orWhere(function ($q) use ($authId, $userId) {
                $q->where('sender_id', $userId)->where('receiver_id', $authId);
            })
            ->with(['sender:id,name,nickname,avatar', 'receiver:id,name,nickname,avatar'])
            ->orderByDesc('created_at')
            ->paginate(20);

        // Mark unread messages as read
        Message::where('sender_id', $userId)
            ->where('receiver_id', $authId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true, 'data' => $messages]);
    }

    /**
     * GET /api/messages/unread-count
     */
    public function unreadCount()
    {
        $count = Message::where('receiver_id', Auth::id())
            ->where('is_read', false)
            ->count();

        return response()->json(['success' => true, 'data' => ['unread_count' => $count]]);
    }
}
