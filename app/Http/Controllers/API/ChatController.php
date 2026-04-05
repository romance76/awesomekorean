<?php

namespace App\Http\Controllers\API;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    /**
     * Banned words for chat profanity filter
     */
    private const BANNED_WORDS = [
        // Korean
        "\xEC\x94\xA8\xEB\xB0\x9C", "\xEC\x8B\x9C\xEB\xB0\x9C", "\xEA\xB0\x9C\xEC\x83\x88\xEB\x81\xBC",
        "\xEB\xB3\x91\xEC\x8B\xA0", "\xEC\xA7\x80\xEB\x9E\x84", "\xEB\xAF\xB8\xEC\xB9\x9C\xEB\x86\x88",
        "\xEB\xAF\xB8\xEC\xB9\x9C\xEB\x85\x84", "\xEC\xA2\x86", "\xEB\xB3\xB4\xEC\xA7\x80",
        "\xEC\xB0\xBD\xEB\x85\x80", "\xEA\xB1\xB8\xEB\xA0\x88", "\xEA\xBF\xA8\xEC\xA0\xB8",
        "\xEC\xA3\xBD\xEC\x96\xB4", "\xE3\x85\x85\xE3\x85\x82", "\xE3\x85\x82\xE3\x85\x85",
        "\xE3\x85\x88\xE3\x84\xB9",
        // English
        'fuck', 'shit', 'bitch', 'asshole', 'bastard', 'cunt', 'nigger', 'faggot', 'whore', 'slut',
    ];

    private function filterProfanity(string $text): string
    {
        foreach (self::BANNED_WORDS as $word) {
            $stars = str_repeat('*', mb_strlen($word));
            $text = str_ireplace($word, $stars, $text);
        }
        return $text;
    }

    /**
     * GET /api/chat/rooms
     * List user's chat rooms with last message
     */
    public function rooms()
    {
        $rooms = ChatRoom::where('is_open', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(function ($room) {
                $last = ChatMessage::where('chat_room_id', $room->id)
                    ->orderByDesc('id')
                    ->select('id', 'message', 'created_at')
                    ->first();

                $room->last_message_id      = $last?->id ?? 0;
                $room->last_message_preview  = $last ? mb_substr($last->message, 0, 30) : null;
                $room->last_message_at       = $last?->created_at;
                $room->unread_count          = 0;
                return $room;
            });

        return response()->json(['success' => true, 'data' => $rooms]);
    }

    /**
     * POST /api/chat/rooms
     * Create DM or group room
     */
    public function createRoom(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'nullable|in:public,friend,group',
        ]);

        $slug = Str::slug($request->name) . '-' . time();
        $room = ChatRoom::create([
            'name'       => $request->name,
            'slug'       => $slug,
            'type'       => $request->input('type', 'public'),
            'is_open'    => true,
            'created_by' => Auth::id(),
        ]);

        // Invite users
        if ($request->has('invite_users') && is_array($request->invite_users)) {
            foreach ($request->invite_users as $uid) {
                try {
                    DB::table('notifications')->insert([
                        'user_id'    => $uid,
                        'type'       => 'chat_invite',
                        'data'       => json_encode([
                            'room_id' => $room->id,
                            'room_name' => $room->name,
                            'slug' => $room->slug,
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    // Silently skip
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => '채팅방이 생성되었습니다.',
            'data'    => $room,
        ], 201);
    }

    /**
     * GET /api/chat/rooms/{slug}
     * Get room with recent messages
     */
    public function room($slug)
    {
        $room = ChatRoom::where('slug', $slug)->firstOrFail();

        $blockedIds = [];
        if (Auth::check()) {
            $blockedIds = DB::table('user_blocks')
                ->where('user_id', Auth::id())
                ->pluck('blocked_user_id')
                ->toArray();
        }

        $messages = ChatMessage::where('chat_room_id', $room->id)
            ->with('user:id,name,nickname,avatar')
            ->latest()
            ->limit(80)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'room'        => $room,
                'messages'    => $messages,
                'blocked_ids' => $blockedIds,
            ],
        ]);
    }

    /**
     * GET /api/chat/rooms/{slug}/messages
     * Paginated messages for a room
     */
    public function messages($slug, Request $request)
    {
        $room = ChatRoom::where('slug', $slug)->firstOrFail();

        $messages = ChatMessage::where('chat_room_id', $room->id)
            ->with('user:id,name,nickname,avatar')
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json(['success' => true, 'data' => $messages]);
    }

    /**
     * POST /api/chat/rooms/{slug}/messages
     * Send text/image message, mark as read
     */
    public function sendMessage(Request $request, $slug)
    {
        $room = ChatRoom::where('slug', $slug)->firstOrFail();

        // File upload
        if ($request->hasFile('file')) {
            $request->validate(['file' => 'required|file|max:10240']); // 10MB
            $file = $request->file('file');
            $path = $file->store('chat', 'public');
            $fileType = str_starts_with($file->getMimeType(), 'image/') ? 'image' : 'file';

            $msg = ChatMessage::create([
                'chat_room_id' => $room->id,
                'user_id'      => Auth::id(),
                'message'      => $file->getClientOriginalName(),
                'file_url'     => Storage::url($path),
                'file_name'    => $file->getClientOriginalName(),
                'file_type'    => $fileType,
                'type'         => $fileType,
            ]);
        } else {
            $request->validate(['message' => 'required|string|max:1000']);
            $text = $this->filterProfanity($request->message);

            $msg = ChatMessage::create([
                'chat_room_id' => $room->id,
                'user_id'      => Auth::id(),
                'message'      => $text,
                'type'         => 'text',
            ]);
        }

        $msg->load('user:id,name,nickname,avatar');

        try {
            broadcast(new MessageSent($msg))->toOthers();
        } catch (\Exception $e) {
            // Broadcasting may not be configured
        }

        return response()->json([
            'success' => true,
            'data'    => $msg,
        ], 201);
    }

    /**
     * GET /api/chat/rooms/{slug}/search
     */
    public function search(Request $request, $slug)
    {
        $room = ChatRoom::where('slug', $slug)->firstOrFail();
        $q = $request->query('q', '');
        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $messages = ChatMessage::where('chat_room_id', $room->id)
            ->where('message', 'like', "%{$q}%")
            ->with('user:id,name,nickname,avatar')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return response()->json(['success' => true, 'data' => $messages]);
    }

    /**
     * POST /api/chat/block/{userId}
     */
    public function blockUser($userId)
    {
        DB::table('user_blocks')->updateOrInsert(
            ['user_id' => Auth::id(), 'blocked_user_id' => $userId],
            ['created_at' => now(), 'updated_at' => now()]
        );
        return response()->json(['success' => true, 'data' => ['blocked' => true]]);
    }

    /**
     * POST /api/chat/unblock/{userId}
     */
    public function unblockUser($userId)
    {
        DB::table('user_blocks')
            ->where('user_id', Auth::id())
            ->where('blocked_user_id', $userId)
            ->delete();
        return response()->json(['success' => true, 'data' => ['blocked' => false]]);
    }

    /**
     * POST /api/chat/report/{messageId}
     */
    public function reportMessage(Request $request, $messageId)
    {
        $request->validate(['reason' => 'nullable|string|max:100']);
        DB::table('chat_reports')->updateOrInsert(
            ['reporter_id' => Auth::id(), 'message_id' => $messageId],
            ['reason' => $request->reason ?? 'spam', 'created_at' => now(), 'updated_at' => now()]
        );
        return response()->json(['success' => true, 'data' => ['reported' => true]]);
    }

    /**
     * PUT /api/chat/rooms/{slug}
     */
    public function updateRoom(Request $request, $slug)
    {
        $room = ChatRoom::where('slug', $slug)->firstOrFail();
        if ($room->created_by !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json(['success' => false, 'message' => '방장만 수정할 수 있습니다.'], 403);
        }
        $request->validate(['name' => 'required|string|max:100']);
        $room->update(['name' => $request->name]);
        return response()->json(['success' => true, 'data' => $room]);
    }

    /**
     * DELETE /api/chat/rooms/{slug}
     */
    public function deleteRoom($slug)
    {
        $room = ChatRoom::where('slug', $slug)->firstOrFail();
        if ($room->created_by !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json(['success' => false, 'message' => '방장만 삭제할 수 있습니다.'], 403);
        }
        DB::table('chat_messages')->where('chat_room_id', $room->id)->delete();
        $room->delete();
        return response()->json(['success' => true, 'message' => '채팅방이 삭제되었습니다.']);
    }
}
