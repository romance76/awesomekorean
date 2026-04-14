<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubBoard;
use App\Models\ClubPost;
use App\Models\ChatRoom;
use App\Models\ChatRoomUser;
use Illuminate\Http\Request;

class ClubController extends Controller
{
    private function getMemberGrade($clubId, $userId)
    {
        return ClubMember::where('club_id', $clubId)->where('user_id', $userId)->where('status', 'approved')->value('grade');
    }

    public function myClubs()
    {
        $clubIds = ClubMember::where('user_id', auth()->id())->where('status', 'approved')->pluck('club_id');
        $clubs = Club::whereIn('id', $clubIds)->where('is_active', true)->select('id', 'name', 'category', 'image')->get();
        return response()->json(['success' => true, 'data' => $clubs]);
    }

    public function index(Request $request)
    {
        $query = Club::with('user:id,name,nickname')
            ->where('is_active', true)
            ->when($request->type, fn($q, $v) => $q->where('type', $v))
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->when($request->search, fn($q, $v) => $q->where('name', 'like', "%{$v}%"));

        if ($request->lat && $request->lng) {
            // 위치 있는 동호회는 거리순, 없는 동호회(온라인 등)도 포함
            $lat = $request->lat;
            $lng = $request->lng;
            $radius = $request->radius ?? 50;
            $query->where(function ($q) use ($lat, $lng, $radius) {
                $q->whereNull('lat')
                  ->orWhereNull('lng')
                  ->orWhereRaw("(3959*acos(cos(radians(?))*cos(radians(lat))*cos(radians(lng)-radians(?))+sin(radians(?))*sin(radians(lat)))) < ?", [$lat, $lng, $lat, $radius]);
            })->orderByDesc('member_count');
        } else {
            $query->orderByDesc('member_count');
        }

        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    public function show($id)
    {
        $club = Club::with('user:id,name,nickname')->findOrFail($id);
        $membership = auth()->check()
            ? ClubMember::where('club_id', $id)->where('user_id', auth()->id())->first()
            : null;
        $grade = $membership && $membership->status === 'approved' ? $membership->grade : null;
        $boards = ClubBoard::where('club_id', $id)->where('is_active', true)->orderBy('sort_order')->get();
        $memberCount = ClubMember::where('club_id', $id)->where('status', 'approved')->count();
        $pendingCount = ClubMember::where('club_id', $id)->where('status', 'pending')->count();

        return response()->json([
            'success' => true,
            'data' => $club,
            'is_member' => !!$grade,
            'my_grade' => $grade,
            'my_status' => $membership?->status, // approved, pending, rejected, null
            'boards' => $boards,
            'member_count' => $memberCount,
            'pending_count' => $pendingCount,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:100',
            'category' => 'required',
            'type' => 'required|in:online,local',
            'description' => 'nullable|max:2000',
            'rules' => 'nullable|max:2000',
            'zipcode' => 'nullable|max:20',
            'max_members' => 'nullable|integer|min:0',
            'is_public' => 'nullable|boolean',
            'image' => 'nullable|image|max:5120',
            'cover_image' => 'nullable|image|max:5120',
        ]);

        $data = $request->only('name', 'description', 'rules', 'category', 'type', 'zipcode', 'lat', 'lng', 'max_members');
        $data['user_id'] = auth()->id();
        $data['member_count'] = 1;
        // lat/lng 없으면 유저 프로필에서 가져오기
        if (empty($data['lat']) && empty($data['lng'])) {
            $user = auth()->user();
            if ($user->latitude && $user->longitude) {
                $data['lat'] = $user->latitude;
                $data['lng'] = $user->longitude;
            }
        }
        $data['is_public'] = filter_var($request->input('is_public', true), FILTER_VALIDATE_BOOLEAN);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('clubs', 'public');
        }
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('clubs', 'public');
        }

        $club = Club::create($data);

        ClubMember::create([
            'club_id' => $club->id,
            'user_id' => auth()->id(),
            'role' => 'admin',
            'grade' => 'owner',
            'joined_at' => now(),
        ]);

        ClubBoard::create([
            'club_id' => $club->id,
            'name' => '자유게시판',
            'description' => '자유롭게 글을 올려보세요',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return response()->json(['success' => true, 'data' => $club], 201);
    }

    public function update(Request $request, $id)
    {
        $club = Club::findOrFail($id);
        $grade = $this->getMemberGrade($id, auth()->id());

        if (!in_array($grade, ['owner', 'admin'])) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다'], 403);
        }

        $request->validate([
            'name' => 'sometimes|max:100',
            'description' => 'nullable|max:2000',
            'rules' => 'nullable|max:2000',
            'category' => 'sometimes',
            'type' => 'sometimes|in:online,local',
            'zipcode' => 'nullable|max:20',
            'max_members' => 'nullable|integer|min:0',
            'is_public' => 'nullable|boolean',
            'image' => 'nullable|image|max:5120',
            'cover_image' => 'nullable|image|max:5120',
        ]);

        $data = $request->only('name', 'description', 'rules', 'category', 'type', 'zipcode', 'lat', 'lng', 'max_members');

        if ($request->has('is_public')) {
            $data['is_public'] = filter_var($request->input('is_public'), FILTER_VALIDATE_BOOLEAN);
        }
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('clubs', 'public');
        }
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('clubs', 'public');
        }

        $club->update($data);

        return response()->json(['success' => true, 'data' => $club->fresh()]);
    }

    public function destroy($id)
    {
        $club = Club::findOrFail($id);

        if ($this->getMemberGrade($id, auth()->id()) !== 'owner') {
            return response()->json(['success' => false, 'message' => '모임장만 삭제할 수 있습니다'], 403);
        }

        ClubPost::where('club_id', $id)->delete();
        ClubBoard::where('club_id', $id)->delete();
        ClubMember::where('club_id', $id)->delete();
        $club->delete();

        return response()->json(['success' => true]);
    }

    public function join($id)
    {
        $club = Club::findOrFail($id);

        $existing = ClubMember::where('club_id', $id)->where('user_id', auth()->id())->first();
        if ($existing) {
            if ($existing->status === 'pending') return response()->json(['success' => false, 'message' => '가입 승인 대기 중입니다'], 400);
            if ($existing->status === 'rejected') return response()->json(['success' => false, 'message' => '가입이 거절되었습니다. 운영자에게 문의하세요'], 400);
            return response()->json(['success' => false, 'message' => '이미 가입됨'], 400);
        }

        if ($club->max_members && $club->member_count >= $club->max_members) {
            return response()->json(['success' => false, 'message' => '정원이 초과되었습니다'], 400);
        }

        ClubMember::create([
            'club_id' => $id,
            'user_id' => auth()->id(),
            'role' => 'member',
            'grade' => 'member',
            'status' => 'pending', // 운영자 승인 대기
            'joined_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => '가입 신청이 완료되었습니다. 운영자 승인을 기다려주세요.', 'status' => 'pending']);
    }

    // 운영자: 가입 승인
    public function approveMember($id, $userId)
    {
        $myGrade = $this->getMemberGrade($id, auth()->id());
        if (!in_array($myGrade, ['owner', 'admin'])) return response()->json(['success' => false, 'message' => '권한이 없습니다'], 403);

        $member = ClubMember::where('club_id', $id)->where('user_id', $userId)->firstOrFail();
        $member->update(['status' => 'approved']);
        Club::find($id)->increment('member_count');

        return response()->json(['success' => true, 'message' => '승인되었습니다']);
    }

    // 운영자: 가입 거절
    public function rejectMember($id, $userId)
    {
        $myGrade = $this->getMemberGrade($id, auth()->id());
        if (!in_array($myGrade, ['owner', 'admin'])) return response()->json(['success' => false, 'message' => '권한이 없습니다'], 403);

        $member = ClubMember::where('club_id', $id)->where('user_id', $userId)->firstOrFail();
        $member->update(['status' => 'rejected']);

        return response()->json(['success' => true, 'message' => '거절되었습니다']);
    }

    // 가입 대기 목록
    public function pendingMembers($id)
    {
        $myGrade = $this->getMemberGrade($id, auth()->id());
        if (!in_array($myGrade, ['owner', 'admin'])) return response()->json(['success' => false, 'message' => '권한이 없습니다'], 403);

        $pending = ClubMember::with('user:id,name,nickname,avatar')
            ->where('club_id', $id)->where('status', 'pending')->get();

        return response()->json(['success' => true, 'data' => $pending]);
    }

    public function leave($id)
    {
        $grade = $this->getMemberGrade($id, auth()->id());

        if (!$grade) {
            return response()->json(['success' => false, 'message' => '회원이 아닙니다'], 400);
        }

        if ($grade === 'owner') {
            return response()->json(['success' => false, 'message' => '모임장은 탈퇴할 수 없습니다. 모임장을 위임한 후 탈퇴하세요.'], 400);
        }

        ClubMember::where('club_id', $id)->where('user_id', auth()->id())->delete();
        Club::find($id)?->decrement('member_count');

        return response()->json(['success' => true]);
    }

    public function members($id)
    {
        $members = ClubMember::with('user:id,name,nickname,avatar')
            ->where('club_id', $id)
            ->where('status', 'approved')
            ->orderByRaw("FIELD(grade, 'owner', 'admin', 'member', 'restricted')")
            ->get();

        return response()->json(['success' => true, 'data' => $members]);
    }

    public function updateMember(Request $request, $id, $userId)
    {
        $myGrade = $this->getMemberGrade($id, auth()->id());

        if (!in_array($myGrade, ['owner', 'admin'])) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다'], 403);
        }

        $target = ClubMember::where('club_id', $id)->where('user_id', $userId)->firstOrFail();

        if ($target->grade === 'owner') {
            return response()->json(['success' => false, 'message' => '모임장 등급은 변경할 수 없습니다'], 403);
        }

        if ($myGrade === 'admin' && $target->grade === 'admin') {
            return response()->json(['success' => false, 'message' => '같은 등급의 관리자는 변경할 수 없습니다'], 403);
        }

        $request->validate(['grade' => 'required|in:admin,member,restricted']);

        if ($request->grade === 'admin' && $myGrade !== 'owner') {
            return response()->json(['success' => false, 'message' => '모임장만 관리자를 지정할 수 있습니다'], 403);
        }

        $target->update(['grade' => $request->grade, 'role' => $request->grade === 'admin' ? 'admin' : 'member']);

        return response()->json(['success' => true, 'data' => $target->fresh()->load('user:id,name,nickname')]);
    }

    public function removeMember($id, $userId)
    {
        $myGrade = $this->getMemberGrade($id, auth()->id());

        if (!in_array($myGrade, ['owner', 'admin'])) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다'], 403);
        }

        $target = ClubMember::where('club_id', $id)->where('user_id', $userId)->firstOrFail();

        if ($target->grade === 'owner') {
            return response()->json(['success' => false, 'message' => '모임장은 강퇴할 수 없습니다'], 403);
        }

        if ($myGrade === 'admin' && $target->grade === 'admin') {
            return response()->json(['success' => false, 'message' => '관리자는 다른 관리자를 강퇴할 수 없습니다'], 403);
        }

        $target->delete();
        Club::find($id)?->decrement('member_count');

        return response()->json(['success' => true]);
    }

    public function boards($id)
    {
        $boards = ClubBoard::where('club_id', $id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['success' => true, 'data' => $boards]);
    }

    public function createBoard(Request $request, $id)
    {
        $grade = $this->getMemberGrade($id, auth()->id());

        if (!in_array($grade, ['owner', 'admin'])) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다'], 403);
        }

        $request->validate([
            'name' => 'required|max:50',
            'description' => 'nullable|max:500',
            'only_admin_post' => 'nullable|boolean',
        ]);

        $maxSort = ClubBoard::where('club_id', $id)->max('sort_order') ?? 0;

        $board = ClubBoard::create([
            'club_id' => $id,
            'name' => $request->name,
            'description' => $request->description,
            'sort_order' => $maxSort + 1,
            'only_admin_post' => filter_var($request->input('only_admin_post', false), FILTER_VALIDATE_BOOLEAN),
            'is_active' => true,
        ]);

        return response()->json(['success' => true, 'data' => $board], 201);
    }

    public function updateBoard(Request $request, $id, $boardId)
    {
        $grade = $this->getMemberGrade($id, auth()->id());

        if (!in_array($grade, ['owner', 'admin'])) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다'], 403);
        }

        $board = ClubBoard::where('club_id', $id)->where('id', $boardId)->firstOrFail();

        $request->validate([
            'name' => 'sometimes|max:50',
            'description' => 'nullable|max:500',
            'sort_order' => 'nullable|integer',
            'only_admin_post' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $board->update($request->only('name', 'description', 'sort_order', 'only_admin_post', 'is_active'));

        return response()->json(['success' => true, 'data' => $board->fresh()]);
    }

    public function deleteBoard($id, $boardId)
    {
        $grade = $this->getMemberGrade($id, auth()->id());

        if (!in_array($grade, ['owner', 'admin'])) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다'], 403);
        }

        $board = ClubBoard::where('club_id', $id)->where('id', $boardId)->firstOrFail();
        ClubPost::where('board_id', $boardId)->delete();
        $board->delete();

        return response()->json(['success' => true]);
    }

    public function posts($id)
    {
        $posts = ClubPost::with('user:id,name,nickname,avatar')
            ->where('club_id', $id)
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $posts]);
    }

    public function boardPosts($id, $boardId)
    {
        ClubBoard::where('club_id', $id)->where('id', $boardId)->firstOrFail();

        $posts = ClubPost::with('user:id,name,nickname,avatar')
            ->where('club_id', $id)
            ->where('board_id', $boardId)
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $posts]);
    }

    public function createPost(Request $request, $id)
    {
        $grade = $this->getMemberGrade($id, auth()->id());

        if (!$grade || $grade === 'restricted') {
            return response()->json(['success' => false, 'message' => '글 작성 권한이 없습니다'], 403);
        }

        $request->validate([
            'board_id' => 'required|exists:club_boards,id',
            'title' => 'required|max:200',
            'content' => 'required',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
        ]);

        $board = ClubBoard::where('club_id', $id)->where('id', $request->board_id)->firstOrFail();

        if ($board->only_admin_post && !in_array($grade, ['owner', 'admin'])) {
            return response()->json(['success' => false, 'message' => '관리자만 글을 작성할 수 있는 게시판입니다'], 403);
        }

        $imagesPaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $imagesPaths[] = '/storage/' . $img->store('club_posts', 'public');
            }
        }

        $post = ClubPost::create([
            'club_id' => $id,
            'board_id' => $request->board_id,
            'user_id' => auth()->id(),
            'title' => $request->title,
            'content' => $request->content,
            'images' => $imagesPaths ?: null,
        ]);

        return response()->json(['success' => true, 'data' => $post->load('user:id,name,nickname')], 201);
    }

    public function updatePost(Request $request, $postId)
    {
        $post = ClubPost::findOrFail($postId);
        $grade = $this->getMemberGrade($post->club_id, auth()->id());

        if ($post->user_id !== auth()->id() && !in_array($grade, ['owner', 'admin'])) {
            return response()->json(['success' => false, 'message' => '수정 권한이 없습니다'], 403);
        }

        $request->validate([
            'title' => 'sometimes|max:200',
            'content' => 'sometimes',
            'images' => 'nullable|array',
            'images.*' => 'image|max:5120',
            'is_pinned' => 'nullable|boolean',
        ]);

        $data = $request->only('title', 'content');

        if ($request->has('is_pinned') && in_array($grade, ['owner', 'admin'])) {
            $data['is_pinned'] = filter_var($request->input('is_pinned'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('images')) {
            $imagesPaths = [];
            foreach ($request->file('images') as $img) {
                $imagesPaths[] = $img->store('club_posts', 'public');
            }
            $data['images'] = $imagesPaths;
        }

        $post->update($data);

        return response()->json(['success' => true, 'data' => $post->fresh()->load('user:id,name,nickname')]);
    }

    public function deletePost($postId)
    {
        $post = ClubPost::findOrFail($postId);
        $grade = $this->getMemberGrade($post->club_id, auth()->id());

        if ($post->user_id !== auth()->id() && !in_array($grade, ['owner', 'admin'])) {
            return response()->json(['success' => false, 'message' => '삭제 권한이 없습니다'], 403);
        }

        $post->delete();

        return response()->json(['success' => true]);
    }

    public function createChatRoom(Request $request, $id)
    {
        $grade = $this->getMemberGrade($id, auth()->id());

        if (!in_array($grade, ['owner', 'admin'])) {
            return response()->json(['success' => false, 'message' => '권한이 없습니다'], 403);
        }

        $club = Club::findOrFail($id);

        $request->validate(['name' => 'nullable|max:100']);

        $room = ChatRoom::create([
            'name' => $request->input('name', $club->name . ' 채팅방'),
            'type' => 'club',
            'created_by' => auth()->id(),
        ]);

        $memberIds = ClubMember::where('club_id', $id)->pluck('user_id');
        foreach ($memberIds as $uid) {
            ChatRoomUser::create(['chat_room_id' => $room->id, 'user_id' => $uid]);
        }

        return response()->json(['success' => true, 'data' => $room], 201);
    }
}
