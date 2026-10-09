<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{ChatRoom, ChatRoomUser, ChatMessage};
use App\Services\BadWordFilter;
use App\Traits\CompressesUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    use CompressesUploads;

    public function rooms() {
        $this->sweepExpiredRooms();

        $userId = auth()->id();
        $freeRoomId = auth()->user()->free_public_room_id;
        $isStaff = in_array(auth()->user()->role, ['admin', 'super_admin', 'moderator']);

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

        // 내가 글을 쓴 적 있는 방 — "참가중인 채팅방" 섹션(공개방 전용) 판정용
        $postedRoomIds = ChatMessage::where('user_id', $userId)
            ->whereIn('chat_room_id', $allRoomIds)
            ->distinct()
            ->pluck('chat_room_id');

        // 각 방 미읽음 수 집계
        $rooms = $rooms->map(function ($r) use ($reads, $userId, $postedRoomIds, $freeRoomId, $isStaff) {
            $cru = $reads->get($r->id);
            $hasEntered = (bool) $cru;
            $lastRead = $cru?->last_read_at;
            // 공개방 중 내가 글을 쓴 적 있고, "나가기"를 누르지 않은 방
            $r->is_participating = $r->type === 'public'
                && $postedRoomIds->contains($r->id)
                && !($cru?->left_at);

            // 공개방 입장권(24시간) 보유 여부 — 방장 본인 / 마이페이지에서 지정한
            // 무료 채팅방 / 유효기간이 남은 입장권 중 하나라도 있으면 블러 없이 열람 가능.
            // DM/그룹은 입장료 대상이 아니므로 항상 true.
            $r->has_access = $r->type !== 'public'
                || $isStaff
                || $r->created_by === $userId
                || $r->id === $freeRoomId
                || ($cru?->access_expires_at && $cru->access_expires_at->isFuture());

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
            // 잠금/삭제 여부는 저장된 locked_at이 아니라 마지막 활동 시각으로 그때그때 계산
            // (스케줄러가 안 돌아도 항상 정확하도록)
            $r->is_locked = \App\Support\ChatLockHelper::isLocked($r);
            $r->delete_in_days = \App\Support\ChatLockHelper::daysUntilDelete($r);
            return $r;
        });

        return response()->json(['success'=>true,'data'=>$rooms]);
    }

    // 채팅방 목록 검색 — 방 이름, DM 상대방 이름, 최근 메시지 내용으로 매치
    public function searchRooms(Request $request) {
        $q = trim((string) $request->q);
        if ($q === '') return response()->json(['success' => true, 'data' => []]);

        $userId = auth()->id();

        // 접근 가능한 방 범위는 rooms()와 동일: 본인이 멤버인 방 + 모든 공개 방, 차단된 방 제외
        $memberRoomIds = ChatRoomUser::where('user_id', $userId)->pluck('chat_room_id');
        $publicRoomIds = ChatRoom::where('type', 'public')->pluck('id');
        $allRoomIds = $memberRoomIds->merge($publicRoomIds)->unique();
        $bannedRoomIds = DB::table('chat_room_bans')->where('user_id', $userId)->pluck('chat_room_id');
        $allRoomIds = $allRoomIds->diff($bannedRoomIds);

        // 1) 방 이름(그룹/공개방)으로 매치
        $nameMatchIds = ChatRoom::whereIn('id', $allRoomIds)
            ->where('name', 'like', '%' . $q . '%')
            ->pluck('id');

        // 2) DM 방은 이름이 없으니 상대방의 닉네임/이름으로 매치
        $dmMatchIds = ChatRoom::whereIn('id', $allRoomIds)
            ->whereIn('type', ['dm', 'private'])
            ->whereHas('participants', function ($qq) use ($q, $userId) {
                $qq->where('users.id', '!=', $userId)
                   ->where(function ($w) use ($q) {
                       $w->where('nickname', 'like', '%' . $q . '%')
                         ->orWhere('name', 'like', '%' . $q . '%');
                   });
            })
            ->pluck('id');

        // 3) 최근 메시지 내용으로 매치 — 방마다 가장 최근에 일치한 메시지 1개를 스니펫으로 사용
        $msgMatches = ChatMessage::whereIn('chat_room_id', $allRoomIds)
            ->where('content', 'like', '%' . $q . '%')
            ->orderByDesc('created_at')
            ->get(['id', 'chat_room_id', 'content', 'created_at'])
            ->unique('chat_room_id')
            ->values();

        $matchedRoomIds = $nameMatchIds->merge($dmMatchIds)->merge($msgMatches->pluck('chat_room_id'))->unique();
        if ($matchedRoomIds->isEmpty()) return response()->json(['success' => true, 'data' => []]);

        $rooms = ChatRoom::whereIn('id', $matchedRoomIds)
            ->withCount('users')
            ->with(['participants:id,name,nickname,avatar,role'])
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get();

        $snippetByRoom = $msgMatches->keyBy('chat_room_id');
        $rooms = $rooms->map(function ($r) use ($snippetByRoom) {
            $hit = $snippetByRoom->get($r->id);
            $r->match_snippet = $hit?->content;
            $r->match_message_id = $hit?->id;
            $r->is_locked = \App\Support\ChatLockHelper::isLocked($r);
            return $r;
        });

        return response()->json(['success' => true, 'data' => $rooms]);
    }

    // 오래 방치된 개인/그룹 방을 실제로 삭제 (크론 없이도 트래픽 발생 시 자동 정리, 5분에 한 번만 실행)
    private function sweepExpiredRooms(): void {
        if (!\Illuminate\Support\Facades\Cache::add('chat_expire_sweep_lock', 1, 300)) return;

        $lockDays = \App\Support\ChatRules::get('inactive_lock_days', 7);
        $deleteDays = \App\Support\ChatRules::get('lock_delete_days', 3);
        $cutoff = now()->subDays($lockDays + $deleteDays);

        $toDelete = ChatRoom::whereIn('type', ['dm', 'group'])
            ->where(function ($q) use ($cutoff) {
                $q->where('last_message_at', '<', $cutoff)
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->whereNull('last_message_at')->where('created_at', '<', $cutoff);
                  });
            })
            ->get();

        foreach ($toDelete as $room) {
            DB::table('chat_room_bans')->where('chat_room_id', $room->id)->delete();
            $room->delete(); // chat_room_users/chat_messages 는 FK cascadeOnDelete
        }
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

        $room->is_locked = \App\Support\ChatLockHelper::isLocked($room);
        $room->delete_in_days = \App\Support\ChatLockHelper::daysUntilDelete($room);

        return response()->json(['success'=>true,'data'=>$room]);
    }

    // 채팅 잠금/삭제 기준일 설정 조회 (프론트에서 잠금 안내/삭제 카운트다운 표시용)
    public function settings() {
        return response()->json(['success'=>true,'data'=>[
            'inactive_lock_days' => \App\Support\ChatRules::get('inactive_lock_days', 7),
            'lock_delete_days' => \App\Support\ChatRules::get('lock_delete_days', 3),
            'create_cost_dm' => \App\Support\ChatRules::get('create_cost_dm', 50),
            'create_cost_group' => \App\Support\ChatRules::get('create_cost_group', 200),
            'create_cost_public' => \App\Support\ChatRules::get('create_cost_public', 500),
            'entry_cost_public' => \App\Support\ChatRules::get('entry_cost_public', 10),
        ]]);
    }

    // 채팅방 삭제 — 방을 만든 사람 본인만 가능(다른 멤버가 있어도 무관).
    // 멤버/메시지는 chat_room_users/chat_messages의 FK cascadeOnDelete로 함께 정리됨.
    public function deleteRoom($id) {
        $room = ChatRoom::findOrFail($id);
        if ($room->created_by !== auth()->id()) {
            return response()->json(['success' => false, 'message' => '본인이 만든 채팅방만 삭제할 수 있습니다'], 403);
        }
        DB::table('chat_room_bans')->where('chat_room_id', $room->id)->delete();
        $room->delete();
        return response()->json(['success' => true]);
    }

    // 채팅방 읽음 표시 (last_read_at = now)
    public function markRead($id) {
        ChatRoom::findOrFail($id); // 그 사이 삭제된 방이면 500 대신 404
        $userId = auth()->id();
        ChatRoomUser::updateOrCreate(
            ['chat_room_id' => $id, 'user_id' => $userId],
            ['last_read_at' => now()]
        );
        return response()->json(['success' => true]);
    }

    // 사이드바 "참가중인 채팅방"(공개방) 목록에서 나가기 — 메시지 기록은
    // 남고 방도 삭제되지 않음. 이후 이 방에 다시 글을 쓰면 sendMessage()가
    // 자동으로 재참가 처리해 다시 "참가중"에 노출됨.
    public function leaveRoom($id) {
        ChatRoom::findOrFail($id);
        ChatRoomUser::updateOrCreate(
            ['chat_room_id' => $id, 'user_id' => auth()->id()],
            ['left_at' => now()]
        );
        return response()->json(['success' => true]);
    }

    // 공개 채팅방 입장 — 포인트를 내고 24시간 이용권을 받음. 방장 본인과
    // 마이페이지에서 지정한 "무료 채팅방"은 차감 없이 항상 입장 가능.
    // 이미 유효한 이용권이 남아있으면 재차감 없이 그대로 입장.
    public function enterRoom($id) {
        $room = ChatRoom::findOrFail($id);
        $user = auth()->user();

        if ($room->type !== 'public') {
            return response()->json(['success' => true, 'points_spent' => 0]);
        }
        if ($room->created_by === $user->id || $room->id === $user->free_public_room_id) {
            return response()->json(['success' => true, 'points_spent' => 0]);
        }

        $cru = ChatRoomUser::where('chat_room_id', $id)->where('user_id', $user->id)->first();
        if ($cru?->access_expires_at && $cru->access_expires_at->isFuture()) {
            return response()->json(['success' => true, 'points_spent' => 0, 'access_expires_at' => $cru->access_expires_at]);
        }

        $cost = \App\Support\ChatRules::get('entry_cost_public', 10);
        if ($cost > 0 && $user->points < $cost) {
            return response()->json(['success' => false, 'message' => "포인트가 부족합니다. 공개 채팅방 입장에 {$cost}P가 필요합니다(보유: {$user->points}P)"], 422);
        }

        $expiresAt = now()->addDay();
        ChatRoomUser::updateOrCreate(
            ['chat_room_id' => $id, 'user_id' => $user->id],
            ['access_expires_at' => $expiresAt, 'left_at' => null]
        );
        if ($cost > 0) {
            $user->addPoints(-$cost, '공개 채팅방 입장(24시간)', 'chat_room_entry', ['type' => ChatRoom::class, 'id' => $room->id]);
        }

        return response()->json(['success' => true, 'points_spent' => $cost, 'access_expires_at' => $expiresAt]);
    }

    // 메시지 검색
    public function searchMessages(Request $request, $id) {
        $q = trim((string) $request->q);
        if (strlen($q) < 1) return response()->json(['success' => true, 'data' => []]);

        $room = ChatRoom::findOrFail($id);
        if (!\App\Support\ChatAccess::hasPublicAccess($room, auth()->user())) return \App\Support\ChatAccess::denied();

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
        $user = auth()->user();

        // DM: 상대 지정 필수, 기존 1:1 방 있으면 재사용
        if ($data['type'] === 'dm') {
            $otherId = $data['user_id'] ?? ($data['user_ids'][0] ?? null);
            if (!$otherId || $otherId == $meId) {
                return response()->json(['success'=>false,'message'=>'상대를 선택하세요'], 422);
            }
            // 새 대화(Conversation)/쪽지/통화는 차단을 확인하는데 구 채팅방
            // 생성 경로만 빠져있어 차단해도 1:1 채팅방은 새로 만들 수 있던 문제 수정.
            if (\App\Models\UserBlock::isBlocked($otherId, $meId) || \App\Models\UserBlock::isBlocked($meId, $otherId)) {
                return response()->json(['success'=>false,'message'=>'채팅방을 만들 수 없는 사용자입니다'], 403);
            }
            // 기존 DM 방 재사용 (두 유저가 모두 멤버인 dm 방) — 재사용은 새로 만드는 게
            // 아니므로 포인트 차감 없음
            $existingId = ChatRoom::where('type','dm')
                ->whereHas('users', fn($q)=>$q->where('chat_room_users.user_id',$meId))
                ->whereHas('users', fn($q)=>$q->where('chat_room_users.user_id',$otherId))
                ->pluck('id')->first();
            if ($existingId) {
                $room = ChatRoom::findOrFail($existingId);
                return response()->json(['success'=>true,'data'=>$room]);
            }

            // 무분별한 채팅방 생성을 막기 위해 새로 만들 때마다 포인트 차감
            $cost = \App\Support\ChatRules::get('create_cost_dm', 50);
            if ($cost > 0 && $user->points < $cost) {
                return response()->json(['success'=>false,'message'=>"포인트가 부족합니다. 1:1 채팅방 개설에 {$cost}P가 필요합니다(보유: {$user->points}P)"], 422);
            }

            $room = ChatRoom::create([
                'name' => null,       // DM 이름은 프론트에서 상대 이름으로 렌더
                'type' => 'dm',
                'created_by' => $meId,
            ]);
            ChatRoomUser::create(['chat_room_id'=>$room->id,'user_id'=>$meId]);
            ChatRoomUser::create(['chat_room_id'=>$room->id,'user_id'=>$otherId]);
            if ($cost > 0) {
                $user->addPoints(-$cost, '1:1 채팅방 개설', 'chat_room_create', ['type'=>ChatRoom::class,'id'=>$room->id]);
            }
            return response()->json(['success'=>true,'data'=>$room,'points_spent'=>$cost], 201);
        }

        // 그룹: 이름 + 초대 멤버
        if ($data['type'] === 'group') {
            if (empty($data['name'])) {
                return response()->json(['success'=>false,'message'=>'그룹 이름을 입력하세요'], 422);
            }
            $cost = \App\Support\ChatRules::get('create_cost_group', 200);
            if ($cost > 0 && $user->points < $cost) {
                return response()->json(['success'=>false,'message'=>"포인트가 부족합니다. 그룹 채팅방 개설에 {$cost}P가 필요합니다(보유: {$user->points}P)"], 422);
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
            if ($cost > 0) {
                $user->addPoints(-$cost, '그룹 채팅방 개설', 'chat_room_create', ['type'=>ChatRoom::class,'id'=>$room->id]);
            }
            return response()->json(['success'=>true,'data'=>$room,'points_spent'=>$cost], 201);
        }

        // 공개방
        if (!$user->email_verified_at && !in_array($user->role, ['admin', 'super_admin', 'moderator'])) {
            return response()->json(['success'=>false,'message'=>'이메일 인증 후 공개 채팅방을 만들 수 있습니다. 마이페이지에서 인증 메일을 다시 받을 수 있어요.'], 403);
        }
        if (empty($data['name'])) {
            return response()->json(['success'=>false,'message'=>'방 이름을 입력하세요'], 422);
        }
        $cost = \App\Support\ChatRules::get('create_cost_public', 500);
        if ($cost > 0 && $user->points < $cost) {
            return response()->json(['success'=>false,'message'=>"포인트가 부족합니다. 공개 채팅방 개설에 {$cost}P가 필요합니다(보유: {$user->points}P)"], 422);
        }
        $room = ChatRoom::create([
            'name' => $data['name'],
            'type' => 'public',
            'created_by' => $meId,
        ]);
        ChatRoomUser::create(['chat_room_id'=>$room->id,'user_id'=>$meId]);
        if ($cost > 0) {
            $user->addPoints(-$cost, '공개 채팅방 개설', 'chat_room_create', ['type'=>ChatRoom::class,'id'=>$room->id]);
        }
        return response()->json(['success'=>true,'data'=>$room,'points_spent'=>$cost], 201);
    }

    // 지금 이 채팅방에 접속 중인 유저만 (온라인 목록).
    // 클라이언트가 방을 열어 둔 동안 1분마다 보내는 신호(chat_presence.room_id, last_seen_at)를 기준으로,
    // 신호가 2분 30초 안에 있었던 사람만 포함한다. 나(요청자)는 방금 들어와 신호가 아직 없어도 항상 포함.
    public function participants($id) {
        $room = ChatRoom::findOrFail($id);
        $me = auth()->id();

        $onlineIds = \Illuminate\Support\Facades\DB::table('chat_presence')
            ->where('room_id', (int) $id)
            ->where('last_seen_at', '>=', now()->subSeconds(150))
            ->pluck('user_id');

        $allIds = $onlineIds->push($me)->unique()->filter()->values();
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

        // 공개방은 24시간 입장권이 있어야 내용을 내려준다(입장료 우회 방지)
        $room = ChatRoom::findOrFail($id);
        if (!\App\Support\ChatAccess::hasPublicAccess($room, auth()->user())) return \App\Support\ChatAccess::denied();

        // 검색 결과로 들어온 경우(around=찾은 메시지 id): 그 메시지 앞 25개 + 뒤 25개를 내려준다.
        // 일반 조회와 같이 "최신 → 오래된" 순서로 내려 화면 쪽 처리(reverse)를 그대로 쓴다.
        $aroundId = (int) request('around');
        $center = $aroundId ? ChatMessage::where('chat_room_id', $id)->find($aroundId) : null;
        if ($center) {
            $older = ChatMessage::with('user:id,name,nickname,avatar,role')
                ->where('chat_room_id', $id)->where('id', '<=', $center->id)
                ->orderByDesc('id')->limit(26)->get();
            $newer = ChatMessage::with('user:id,name,nickname,avatar,role')
                ->where('chat_room_id', $id)->where('id', '>', $center->id)
                ->orderBy('id')->limit(25)->get();
            $messages = $newer->reverse()->values()->concat($older)->values();
        } else {
            $messages = ChatMessage::with('user:id,name,nickname,avatar,role')
                ->where('chat_room_id',$id)
                ->orderByDesc('created_at')
                ->paginate(50);
        }

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

        // 금칙어/욕설 필터
        if ($hasContent) {
            $bad = BadWordFilter::firstMatch((string) $request->content);
            if ($bad !== null) {
                return response()->json([
                    'success' => false,
                    'message' => '부적절한 표현이 포함되어 있어 전송할 수 없습니다.',
                ], 422);
            }
        }

        // 영구제명된 유저 차단
        if (auth()->user()->is_banned) {
            return response()->json(['success'=>false,'message'=>'영구제명된 계정입니다: '.auth()->user()->ban_reason], 403);
        }

        // 차단된 유저는 메시지 전송 불가
        $banned = DB::table('chat_room_bans')->where('chat_room_id', $id)->where('user_id', auth()->id())->exists();
        if ($banned) return response()->json(['success'=>false,'message'=>'이 채팅방에서 차단되었습니다.'], 403);

        $room = ChatRoom::find($id);
        if (!$room) {
            return response()->json(['success'=>false,'message'=>'채팅방을 찾을 수 없습니다'], 404);
        }

        // 비활성으로 잠긴 방은 더 이상 메시지 전송 불가 (공개방 제외)
        // 저장된 locked_at이 아니라 마지막 활동 시각으로 그때그때 계산 (스케줄러 여부와 무관하게 항상 정확)
        if (\App\Support\ChatLockHelper::isLocked($room)) {
            return response()->json(['success'=>false,'message'=>'비활성 채팅방입니다. 더 이상 메시지를 보낼 수 없습니다.'], 423);
        }

        // 공개방은 누구나 보는 공간이라 게시판 글쓰기와 동일하게 이메일 인증 필요
        // (1:1/그룹방은 서로 아는 멤버끼리의 대화라 제한하지 않음)
        $me = auth()->user();
        $isStaff = in_array($me->role, ['admin', 'super_admin', 'moderator']);
        if ($room->type === 'public' && !$me->email_verified_at && !$isStaff) {
            return response()->json(['success'=>false,'message'=>'이메일 인증 후 공개 채팅방에 글을 쓸 수 있습니다. 마이페이지에서 인증 메일을 다시 받을 수 있어요.'], 403);
        }

        // 공개방 입장권(24시간) 확인 — 화면의 블러/입장 확인창만으로는 API를 직접
        // 호출하면 입장료 없이 글을 쓸 수 있었음. 방장/무료방/운영진은 예외.
        if ($room->type === 'public' && !$isStaff
            && $room->created_by !== $me->id && $room->id !== $me->free_public_room_id) {
            $pass = ChatRoomUser::where('chat_room_id', $id)->where('user_id', $me->id)->value('access_expires_at');
            if (!$pass || now()->greaterThanOrEqualTo($pass)) {
                return response()->json(['success'=>false,'message'=>'채팅방에 입장한 뒤 글을 쓸 수 있습니다.', 'needs_entry'=>true], 403);
            }
        }

        // 공개 방이면 자동 참가 (최초 1회) — 이전에 "나가기" 했던 방이면
        // 다시 글을 쓴 것이므로 재참가 처리(사이드바 "참가중"에 다시 노출)
        if ($room->type === 'public') {
            $cru = ChatRoomUser::firstOrCreate(
                ['chat_room_id' => $id, 'user_id' => auth()->id()],
                ['last_read_at' => now()]
            );
            if ($cru->left_at) {
                $cru->update(['left_at' => null]);
            }
        } else {
            // 그룹/DM 방은 멤버가 아니면 전송 불가 — 이전에는 멤버십을 전혀
            // 확인하지 않아 강퇴(chatKickMember)당한 유저가 chat_room_users에서
            // 삭제된 뒤에도 계속 메시지를 보낼 수 있었음(실측 확인, 강퇴 무력화).
            $isMember = ChatRoomUser::where('chat_room_id', $id)->where('user_id', auth()->id())->exists();
            if (!$isMember) {
                return response()->json(['success'=>false,'message'=>'이 채팅방의 멤버가 아닙니다.'], 403);
            }
        }

        // 공개방 입장권(24시간)이 1시간 이내로 만료되는 상태에서 계속 활동(글쓰기)
        // 중이면 자동 연장 + 포인트 재차감. 방장 본인 / 무료 채팅방은 차감 대상 아님.
        // 포인트가 부족하면 전송은 막지 않고 조용히 연장만 건너뜀(만료되면 다음 열람 시 재입장 요구).
        $autoExtended = false;
        $autoExtendCost = 0;
        if ($room->type === 'public') {
            $isFreeAccess = $room->created_by === auth()->id() || $room->id === auth()->user()->free_public_room_id;
            if (!$isFreeAccess && $cru->access_expires_at && $cru->access_expires_at->isFuture()
                && now()->diffInMinutes($cru->access_expires_at) <= 60) {
                $autoExtendCost = \App\Support\ChatRules::get('entry_cost_public', 10);
                $me = auth()->user();
                if ($autoExtendCost <= 0 || $me->points >= $autoExtendCost) {
                    $cru->update(['access_expires_at' => now()->addDay()]);
                    if ($autoExtendCost > 0) {
                        $me->addPoints(-$autoExtendCost, '공개 채팅방 이용권 자동연장(24시간)', 'chat_room_entry', ['type' => ChatRoom::class, 'id' => $room->id]);
                    }
                    $autoExtended = true;
                }
            }
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
                $isArchive = in_array($mime, $allowedArchiveMimes) && in_array($ext, $allowedArchiveExts);   // 내용과 확장자가 둘 다 압축 파일이어야 함 (이름만 .zip 인 HTML 등 차단)

                if (!$isImage && !$isArchive) {
                    // 문서 등은 거부
                    continue;
                }

                if ($isImage) {
                    $fileUrlForMsg = $this->storeCompressedImage($file, 'chat-images', 1200, 78);
                    $path = preg_replace('#^/storage/#', '', $fileUrlForMsg);
                    $type = 'image';
                } else {
                    $path = $file->storeAs('chat-files', \Illuminate\Support\Str::random(40) . '.' . $ext, 'public');   // 이름·확장자는 우리가 정한다
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

        // 보낸 사람 정보는 한 번만 불러와 브로드캐스트·응답에 같이 쓴다
        foreach ($created as $m) {
            $m->load('user:id,name,nickname,avatar,role');
        }

        // 마지막 메시지 시각 갱신 (자동 잠금 판단 기준)
        $room->update(['last_message_at' => now()]);

        // 실시간 브로드캐스트·포인트 지급은 응답을 먼저 보낸 뒤 처리 (전송 반응 속도)
        $meUser = auth()->user();
        $meId = auth()->id();
        $roomType = $room->type;
        $createdForDefer = $created;
        defer(function () use ($createdForDefer, $meUser, $meId, $roomType) {
            // 실시간 브로드캐스트 (각 메시지마다)
            foreach ($createdForDefer as $m) {
                try { event(new \App\Events\MessageSent($m)); } catch (\Throwable $e) {
                    \Log::warning('[채팅] 실시간 전송 실패: ' . $e->getMessage());
                }
            }

            // 이벤트 #127 (오픈 채팅방 참여 포인트) 기간에만, 공개방 메시지에 한해 자동 지급
            try {
                if ($roomType === 'public' && !empty($createdForDefer)) {
                    $eventActive = \App\Models\Event::where('id', 127)
                        ->where('start_date', '<=', now())->where('end_date', '>=', now())
                        ->exists();
                    if ($eventActive) {
                        $firstNewId = $createdForDefer[0]->id;
                        $hadEarlierPublicMsg = ChatMessage::where('user_id', $meId)
                            ->where('id', '<', $firstNewId)
                            ->whereHas('room', fn($q) => $q->where('type', 'public'))
                            ->exists();
                        if (!$hadEarlierPublicMsg) {
                            $bonus = (int) (DB::table('chat_settings')->where('key', 'chat_first_join_bonus')->value('value') ?? 20);
                            if ($bonus > 0) $meUser->addPoints($bonus, '오픈 채팅방 첫 참여 포인트', 'earn');
                        } else {
                            $hadTodayPublicMsg = ChatMessage::where('user_id', $meId)
                                ->where('id', '<', $firstNewId)
                                ->whereDate('created_at', today())
                                ->whereHas('room', fn($q) => $q->where('type', 'public'))
                                ->exists();
                            if (!$hadTodayPublicMsg) {
                                $daily = (int) (DB::table('chat_settings')->where('key', 'chat_daily_bonus')->value('value') ?? 5);
                                if ($daily > 0) $meUser->addPoints($daily, '오픈 채팅방 오늘 첫 참여 포인트', 'earn');
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('[채팅] 참여 포인트 지급 실패: ' . $e->getMessage());
            }
        });

        // 마지막 메시지를 대표로 반환 (기존 호환) + 전체 배열도 제공
        $last = end($created);
        return response()->json([
            'success' => true,
            'data' => $last,
            'messages' => $created,
            'auto_extended' => $autoExtended,
            'auto_extend_cost' => $autoExtendCost,
        ], 201);
    }
}
