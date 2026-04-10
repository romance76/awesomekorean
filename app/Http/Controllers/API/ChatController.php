<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{ChatRoom, ChatRoomUser, ChatMessage};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function rooms() {
        $userId = auth()->id();

        // 본인이 멤버인 방
        $memberRoomIds = ChatRoomUser::where('user_id', $userId)->pluck('chat_room_id');

        // 공개 방은 멤버십 없이 모두 표시
        $publicRoomIds = ChatRoom::where('type', 'public')->pluck('id');

        $allRoomIds = $memberRoomIds->merge($publicRoomIds)->unique();

        // 차단된 방 제외
        $bannedRoomIds = DB::table('chat_room_bans')->where('user_id', $userId)->pluck('chat_room_id');
        $allRoomIds = $allRoomIds->diff($bannedRoomIds);

        $rooms = ChatRoom::whereIn('id', $allRoomIds)
            ->withCount('users')
            ->with(['messages' => fn($q) => $q->latest()->limit(1)->with('user:id,name,nickname,avatar,role')])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['success'=>true,'data'=>$rooms]);
    }

    public function createRoom(Request $request) {
        $room = ChatRoom::create(['name'=>$request->name,'type'=>$request->type??'dm','created_by'=>auth()->id()]);
        ChatRoomUser::create(['chat_room_id'=>$room->id,'user_id'=>auth()->id()]);
        if ($request->user_id) ChatRoomUser::create(['chat_room_id'=>$room->id,'user_id'=>$request->user_id]);
        return response()->json(['success'=>true,'data'=>$room],201);
    }

    public function messages($id) {
        // 차단된 유저는 메시지 조회 불가
        $banned = DB::table('chat_room_bans')->where('chat_room_id', $id)->where('user_id', auth()->id())->exists();
        if ($banned) return response()->json(['success'=>false,'message'=>'이 채팅방에서 차단되었습니다.'], 403);

        $messages = ChatMessage::with('user:id,name,nickname,avatar,role')
            ->where('chat_room_id',$id)
            ->orderByDesc('created_at')
            ->paginate(50);

        // 활성 공지 (pinned_until > now)
        $pinned = ChatMessage::with('user:id,name,nickname,avatar,role')
            ->where('chat_room_id', $id)
            ->where('type', 'system')
            ->where('pinned_until', '>', now())
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success'=>true,'data'=>$messages,'pinned'=>$pinned]);
    }

    public function sendMessage(Request $request, $id) {
        $request->validate([
            'content' => 'nullable|string|max:2000',
            'image' => 'nullable|image|max:10240',
        ]);

        if (!$request->filled('content') && !$request->hasFile('image')) {
            return response()->json(['success'=>false,'message'=>'내용 또는 이미지가 필요합니다'], 422);
        }

        // 영구제명된 유저 차단
        if (auth()->user()->is_banned) {
            return response()->json(['success'=>false,'message'=>'영구제명된 계정입니다: '.auth()->user()->ban_reason], 403);
        }

        // 차단된 유저는 메시지 전송 불가
        $banned = DB::table('chat_room_bans')->where('chat_room_id', $id)->where('user_id', auth()->id())->exists();
        if ($banned) return response()->json(['success'=>false,'message'=>'이 채팅방에서 차단되었습니다.'], 403);

        // 공개 방이면 자동 참가 (최초 1회)
        $room = ChatRoom::find($id);
        if ($room && $room->type === 'public') {
            ChatRoomUser::firstOrCreate(
                ['chat_room_id' => $id, 'user_id' => auth()->id()],
                ['last_read_at' => now()]
            );
        }

        $data = [
            'chat_room_id' => $id,
            'user_id' => auth()->id(),
            'content' => $request->content ?: '',
            'type' => $request->type ?? 'text',
        ];

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('chat-images', 'public');
            $data['file_url'] = '/storage/' . $path;
            $data['type'] = 'image';
        }

        $msg = ChatMessage::create($data);

        // 실시간 브로드캐스트
        try { event(new \App\Events\MessageSent($msg->load('user:id,name,nickname,avatar,role'))); } catch (\Exception $e) {}
        return response()->json(['success'=>true,'data'=>$msg->load('user:id,name,nickname,avatar,role')],201);
    }
}
