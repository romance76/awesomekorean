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
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:10240', // 각 파일 10MB
        ]);

        $hasContent = $request->filled('content');
        $hasImage = $request->hasFile('image');
        $hasFiles = $request->hasFile('files');

        if (!$hasContent && !$hasImage && !$hasFiles) {
            return response()->json(['success'=>false,'message'=>'내용 또는 파일이 필요합니다'], 422);
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

        $created = [];

        // 1) 텍스트 메시지 (단독 content 가 있을 때만 별도 메시지 하나)
        if ($hasContent && !$hasImage && !$hasFiles) {
            $msg = ChatMessage::create([
                'chat_room_id' => $id,
                'user_id' => auth()->id(),
                'content' => $request->content,
                'type' => 'text',
            ]);
            $created[] = $msg;
        }

        // 2) 단일 image 필드 (기존 호환)
        if ($hasImage) {
            $path = $request->file('image')->store('chat-images', 'public');
            $msg = ChatMessage::create([
                'chat_room_id' => $id,
                'user_id' => auth()->id(),
                'content' => $request->content ?: '',
                'type' => 'image',
                'file_url' => '/storage/' . $path,
            ]);
            $created[] = $msg;
        }

        // 3) 다중 files 배열 (이미지 또는 압축 파일)
        if ($hasFiles) {
            $allowedArchiveMimes = [
                'application/zip','application/x-zip-compressed','application/x-zip',
                'application/x-rar-compressed','application/vnd.rar',
                'application/x-7z-compressed','application/x-7zip','application/octet-stream',
                'application/x-tar','application/gzip','application/x-gzip',
            ];
            $allowedArchiveExts = ['zip','rar','7z','tar','gz','tgz'];
            $firstContentUsed = !$hasImage && $hasContent; // 첫 파일에 content 붙일지

            foreach ($request->file('files') as $idx => $file) {
                $mime = $file->getMimeType();
                $ext = strtolower($file->getClientOriginalExtension());
                $isImage = str_starts_with($mime ?: '', 'image/');
                $isArchive = in_array($mime, $allowedArchiveMimes) || in_array($ext, $allowedArchiveExts);

                if (!$isImage && !$isArchive) {
                    // 문서 등은 거부
                    continue;
                }

                if ($isImage) {
                    $path = $file->store('chat-images', 'public');
                    $type = 'image';
                } else {
                    $path = $file->store('chat-files', 'public');
                    $type = 'file';
                }

                $msg = ChatMessage::create([
                    'chat_room_id' => $id,
                    'user_id' => auth()->id(),
                    'content' => ($firstContentUsed && $idx === 0) ? '' : (($idx === 0 && $hasContent) ? $request->content : $file->getClientOriginalName()),
                    'type' => $type,
                    'file_url' => '/storage/' . $path,
                ]);
                $created[] = $msg;

                // content 는 첫 번째 파일 메시지에만 붙임
                if ($idx === 0 && $hasContent) $firstContentUsed = true;
            }

            if (empty($created)) {
                return response()->json(['success'=>false,'message'=>'허용된 파일이 없습니다 (이미지 또는 압축파일만 가능)'], 422);
            }
        }

        // 실시간 브로드캐스트 (각 메시지마다)
        foreach ($created as $m) {
            try { event(new \App\Events\MessageSent($m->load('user:id,name,nickname,avatar,role'))); } catch (\Exception $e) {}
        }

        // 마지막 메시지를 대표로 반환 (기존 호환) + 전체 배열도 제공
        $last = end($created);
        return response()->json([
            'success' => true,
            'data' => $last->load('user:id,name,nickname,avatar,role'),
            'messages' => collect($created)->map(fn($m) => $m->load('user:id,name,nickname,avatar,role')),
        ], 201);
    }
}
