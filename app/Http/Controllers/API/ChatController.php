<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{ChatRoom, ChatRoomUser, ChatMessage};
use App\Traits\CompressesUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    use CompressesUploads;

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
            ->with([
                'messages' => fn($q) => $q->latest()->limit(1)->with('user:id,name,nickname,avatar,role'),
                // DM 방 이름 매핑(Issue #22)용 — 실제 User 엔티티 로드
                'participants:id,name,nickname,avatar,role',
            ])
            ->orderByDesc('updated_at')
            ->get();

        // 유저의 last_read_at 맵
        $reads = ChatRoomUser::where('user_id', $userId)
            ->whereIn('chat_room_id', $allRoomIds)
            ->get()
            ->keyBy('chat_room_id');

        // 각 방 미읽음 수 집계
        $rooms = $rooms->map(function ($r) use ($reads, $userId) {
            $cru = $reads->get($r->id);
            $hasEntered = (bool) $cru;
            $lastRead = $cru?->last_read_at;

            if (!$hasEntered) {
                // 한번도 들어간 적 없음 = new
                $r->has_entered = false;
                $r->unread_count = 0;
                $r->is_new = true;
            } else {
                $q = ChatMessage::where('chat_room_id', $r->id)
                    ->where('user_id', '!=', $userId);
                if ($lastRead) $q->where('created_at', '>', $lastRead);
                $cnt = $q->count();
                $r->has_entered = true;
                $r->unread_count = $cnt > 300 ? 301 : $cnt; // 300+ 처리
                $r->is_new = false;
            }
            return $r;
        });

        return response()->json(['success'=>true,'data'=>$rooms]);
    }

    // 단일 방 조회 (URL /chat/:id 직접 진입·새로고침 복원용)
    public function showRoom($id) {
        $userId = auth()->id();
        $room = ChatRoom::with(['participants:id,name,nickname,avatar,role'])
            ->withCount('users')
            ->findOrFail($id);

        // 공개방은 모두 허용, 그 외에는 멤버만
        if ($room->type !== 'public') {
            $isMember = ChatRoomUser::where('chat_room_id', $id)
                ->where('user_id', $userId)->exists();
            if (!$isMember) {
                return response()->json(['success'=>false,'message'=>'접근 권한이 없습니다'], 403);
            }
        }

        // 차단된 유저 차단
        $banned = DB::table('chat_room_bans')
            ->where('chat_room_id', $id)->where('user_id', $userId)->exists();
        if ($banned) return response()->json(['success'=>false,'message'=>'차단된 방입니다'], 403);

        return response()->json(['success'=>true,'data'=>$room]);
    }

    // 채팅방 읽음 표시 (last_read_at = now)
    public function markRead($id) {
        $userId = auth()->id();
        ChatRoomUser::updateOrCreate(
            ['chat_room_id' => $id, 'user_id' => $userId],
            ['last_read_at' => now()]
        );
        return response()->json(['success' => true]);
    }

    // 메시지 검색
    public function searchMessages(Request $request, $id) {
        $q = trim((string) $request->q);
        if (strlen($q) < 1) return response()->json(['success' => true, 'data' => []]);

        $msgs = ChatMessage::with('user:id,name,nickname,avatar')
            ->where('chat_room_id', $id)
            ->where('content', 'like', '%' . $q . '%')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => $msgs]);
    }

    public function createRoom(Request $request) {
        $data = $request->validate([
            'name' => 'nullable|string|max:100',
            'type' => 'required|in:dm,group,public',
            'user_id' => 'nullable|integer|exists:users,id',     // 구버전 호환 (DM 1명)
            'user_ids' => 'nullable|array',                        // 신규: 그룹 다중 선택
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $meId = auth()->id();

        // DM: 상대 지정 필수, 기존 1:1 방 있으면 재사용
        if ($data['type'] === 'dm') {
            $otherId = $data['user_id'] ?? ($data['user_ids'][0] ?? null);
            if (!$otherId || $otherId == $meId) {
                return response()->json(['success'=>false,'message'=>'상대를 선택하세요'], 422);
            }
            // 기존 DM 방 재사용 (두 유저가 모두 멤버인 dm 방)
            $existingId = ChatRoom::where('type','dm')
                ->whereHas('users', fn($q)=>$q->where('chat_room_users.user_id',$meId))
                ->whereHas('users', fn($q)=>$q->where('chat_room_users.user_id',$otherId))
                ->pluck('id')->first();
            if ($existingId) {
                $room = ChatRoom::findOrFail($existingId);
                return response()->json(['success'=>true,'data'=>$room]);
            }

            $room = ChatRoom::create([
                'name' => null,       // DM 이름은 프론트에서 상대 이름으로 렌더
                'type' => 'dm',
                'created_by' => $meId,
            ]);
            ChatRoomUser::create(['chat_room_id'=>$room->id,'user_id'=>$meId]);
            ChatRoomUser::create(['chat_room_id'=>$room->id,'user_id'=>$otherId]);
            return response()->json(['success'=>true,'data'=>$room], 201);
        }

        // 그룹: 이름 + 초대 멤버
        if ($data['type'] === 'group') {
            if (empty($data['name'])) {
                return response()->json(['success'=>false,'message'=>'그룹 이름을 입력하세요'], 422);
            }
            $memberIds = collect($data['user_ids'] ?? [])->push($meId)->unique()->values();
            $room = ChatRoom::create([
                'name' => $data['name'],
                'type' => 'group',
                'created_by' => $meId,
            ]);
            foreach ($memberIds as $uid) {
                ChatRoomUser::create(['chat_room_id'=>$room->id,'user_id'=>$uid]);
            }
            return response()->json(['success'=>true,'data'=>$room], 201);
        }

        // 공개방
        if (empty($data['name'])) {
            return response()->json(['success'=>false,'message'=>'방 이름을 입력하세요'], 422);
        }
        $room = ChatRoom::create([
            'name' => $data['name'],
            'type' => 'public',
            'created_by' => $meId,
        ]);
        ChatRoomUser::create(['chat_room_id'=>$room->id,'user_id'=>$meId]);
        return response()->json(['success'=>true,'data'=>$room], 201);
    }

    // 채팅방에 실제로 글을 남긴 유저들 (참가자) + 멤버 등록된 유저 합친 목록
    public function participants($id) {
        $room = ChatRoom::findOrFail($id);
        $me = auth()->id();

        $memberIds = ChatRoomUser::where('chat_room_id', $id)->pluck('user_id');
        $activeUserIds = ChatMessage::where('chat_room_id', $id)
            ->whereNotNull('user_id')
            ->orderByDesc('created_at')->limit(100)
            ->pluck('user_id')->unique();

        $allIds = $memberIds->merge($activeUserIds)->unique()->filter()->values();
        if ($allIds->isEmpty()) return response()->json(['success' => true, 'data' => []]);

        $users = \App\Models\User::whereIn('id', $allIds)
            ->where('is_banned', false)
            ->select('id','name','nickname','avatar','role','city','state','bio','allow_friend_request','allow_messages','last_active_at')
            ->orderByDesc('last_active_at')
            ->get();

        $msgCounts = ChatMessage::where('chat_room_id', $id)
            ->whereIn('user_id', $users->pluck('id'))
            ->selectRaw('user_id, count(*) as c')
            ->groupBy('user_id')->pluck('c', 'user_id');

        // 내가 신고한 유저 ID 목록
        $reportedIds = \App\Models\Report::where('reporter_id', $me)
            ->where('reportable_type', 'App\\Models\\User')
            ->whereIn('reportable_id', $users->pluck('id'))
            ->pluck('reportable_id')->toArray();

        $users = $users->map(function ($u) use ($msgCounts, $reportedIds) {
            $u->message_count = (int) ($msgCounts[$u->id] ?? 0);
            $u->is_reported = in_array($u->id, $reportedIds);
            return $u;
        });

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function messages($id) {
        $userId = auth()->id();

        // 차단된 유저는 메시지 조회 불가
        $banned = DB::table('chat_room_bans')->where('chat_room_id', $id)->where('user_id', $userId)->exists();
        if ($banned) return response()->json(['success'=>false,'message'=>'이 채팅방에서 차단되었습니다.'], 403);

        $messages = ChatMessage::with('user:id,name,nickname,avatar,role')
            ->where('chat_room_id',$id)
            ->orderByDesc('created_at')
            ->paginate(50);

        $pinned = ChatMessage::with('user:id,name,nickname,avatar,role')
            ->where('chat_room_id', $id)
            ->where('type', 'system')
            ->where('pinned_until', '>', now())
            ->orderByDesc('created_at')->get();

        // 유저의 마지막 읽음 시각 (읽음 표시 전에 조회)
        $cru = ChatRoomUser::where('chat_room_id', $id)->where('user_id', $userId)->first();
        $lastReadAt = $cru?->last_read_at;

        return response()->json([
            'success' => true,
            'data' => $messages,
            'pinned' => $pinned,
            'last_read_at' => $lastReadAt,
        ]);
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
            $fileUrl = $this->storeCompressedImage($request->file('image'), 'chat-images', 1200, 78);
            $msg = ChatMessage::create([
                'chat_room_id' => $id,
                'user_id' => auth()->id(),
                'content' => $request->content ?: '',
                'type' => 'image',
                'file_url' => $fileUrl,
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
                    $fileUrlForMsg = $this->storeCompressedImage($file, 'chat-images', 1200, 78);
                    $path = preg_replace('#^/storage/#', '', $fileUrlForMsg);
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
