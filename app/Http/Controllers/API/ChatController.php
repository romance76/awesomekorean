<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{ChatRoom, ChatRoomUser, ChatMessage};
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function rooms() {
        $roomIds = ChatRoomUser::where('user_id', auth()->id())->pluck('chat_room_id');
        $rooms = ChatRoom::whereIn('id', $roomIds)->with(['messages' => fn($q) => $q->latest()->limit(1)])->get();
        return response()->json(['success'=>true,'data'=>$rooms]);
    }

    public function createRoom(Request $request) {
        $room = ChatRoom::create(['name'=>$request->name,'type'=>$request->type??'dm','created_by'=>auth()->id()]);
        ChatRoomUser::create(['chat_room_id'=>$room->id,'user_id'=>auth()->id()]);
        if ($request->user_id) ChatRoomUser::create(['chat_room_id'=>$room->id,'user_id'=>$request->user_id]);
        return response()->json(['success'=>true,'data'=>$room],201);
    }

    public function messages($id) {
        $messages = ChatMessage::with('user:id,name,nickname,avatar')->where('chat_room_id',$id)->orderByDesc('created_at')->paginate(50);
        return response()->json(['success'=>true,'data'=>$messages]);
    }

    public function sendMessage(Request $request, $id) {
        $request->validate(['content' => 'required']);
        $msg = ChatMessage::create(['chat_room_id'=>$id,'user_id'=>auth()->id(),'content'=>$request->content,'type'=>$request->type??'text']);
        return response()->json(['success'=>true,'data'=>$msg->load('user:id,name,nickname,avatar')],201);
    }
}
