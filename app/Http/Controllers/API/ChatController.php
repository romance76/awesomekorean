<?php

namespace App\Http\Controllers\API;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function rooms()
    {
        $rooms = ChatRoom::where('is_open', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return response()->json($rooms);
    }

    public function room($slug)
    {
        $room = ChatRoom::where('slug', $slug)->firstOrFail();
        $messages = ChatMessage::where('chat_room_id', $room->id)
            ->with('user:id,name,username,avatar')
            ->latest()
            ->limit(60)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'room'     => $room,
            'messages' => $messages,
        ]);
    }

    public function messages($slug, Request $request)
    {
        $room = ChatRoom::where('slug', $slug)->firstOrFail();
        $messages = ChatMessage::where('chat_room_id', $room->id)
            ->with('user:id,name,username,avatar')
            ->orderBy('id', 'desc')
            ->paginate(50);

        return response()->json($messages);
    }

    public function send(Request $request, $slug)
    {
        $request->validate(['message' => 'required|string|max:1000']);

        $room = ChatRoom::where('slug', $slug)->firstOrFail();

        $msg = ChatMessage::create([
            'chat_room_id' => $room->id,
            'user_id'      => Auth::id(),
            'message'      => $request->message,
            'type'         => 'text',
        ]);

        $msg->load('user:id,name,username,avatar');

        broadcast(new MessageSent($msg))->toOthers();

        return response()->json($msg, 201);
    }
}
