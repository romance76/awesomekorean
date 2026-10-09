<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubBoard;
use App\Models\ClubPost;
use App\Models\ChatRoom;
use App\Models\ChatRoomUser;
use App\Models\Notification;
use App\Events\NewNotification;
use App\Traits\CompressesUploads;
use App\Traits\HasAdjacent;
use App\Traits\HasPromotions;
use Illuminate\Http\Request;

class ClubController extends Controller
{
    use CompressesUploads, HasAdjacent, HasPromotions;

    /** HasPromotions 설정 */
    protected string $promoResource = 'clubs';
    protected string $promoModel = \App\Models\Club::class;
    protected string $promoCategoryColumn = 'category';

    /** 소유자만 상위노출 가능 */
    public function promote(Request $request, $id)
    {
        $club = Club::findOrFail($id);
        if ($club->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => '동호회 소유자만 상위노출할 수 있습니다'], 403);
        }
        return $this->handlePromote($club, $request);
    }

    /** 슬롯 현황 */
    public function promotionSlots(Request $request)
    {
        return $this->handlePromotionSlots($request);
    }

    private function getMemberGrade($clubId, $userId)
    {
        return ClubMember::where('club_id', $clubId)->where('user_id', $userId)->where('status', 'approved')->value('grade');
    }

    // 가입 신청/승인/거절 전 과정에 알림이 전혀 없어 신청자·운영자 모두
    // 페이지를 직접 열어봐야만 알 수 있던 문제 수정 — MessageController와 동일한 패턴.
    private function notify(int $userId, string $type, string $title, string $content, array $data = []): void
    {
        Notification::create(['user_id' => $userId, 'type' => $type, 'title' => $title, 'content' => $content, 'data' => $data]);
        $unread = Notification::where('user_id', $userId)->whereNull('read_at')->count();
        try { broadcast(new NewNotification($userId, $unread, $title))->toOthers(); } catch (\Exception $e) {}
    }

    /**
     * 동호회 운영진(owner/admin) 또는 사이트 관리자인지.
     * 이전엔 super_admin이라도 그 동호회의 멤버가 아니면 무조건 403이라, 전체
     * 동호회 가입신청·멤버 관리를 조회/처리할 방법이 구조적으로 없었음(실측 확인).
     */
    private function isClubManager($clubId, $userId): bool
    {
        if (in_array($this->getMemberGrade($clubId, $userId), ['owner', 'admin'], true)) {
            return true;
        }
        $role = \App\Models\User::find($userId)?->role;
        return in_array($role, ['admin', 'super_admin', 'moderator'], true);
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

        return response()->json(['success' => true, 'data' => $query->paginate($request->per_page ?? 20)]);
    }

    public function show($id)
    {
        $club = Club::with('user:id,name,nickname')->findOrFail($id);
        // 삭제(보관)된 동호회는 일반 접근 불가 — 사이트 관리자만 볼 수 있음
        if (!$club->is_active) {
            $role = auth()->check() ? auth()->user()->role : null;
            if (!in_array($role, ['admin', 'super_admin'], true)) {
                return response()->json(['success' => false, 'message' => '삭제된 동호회예요'], 404);
            }
        }
        $membership = auth()->check()
            ? ClubMember::where('club_id', $id)->where('user_id', auth()->id())->first()
            : null;
        $grade = $membership && $membership->status === 'approved' ? $membership->grade : null;
        $boards = ClubBoard::where('club_id', $id)->where('is_active', true)->orderBy('sort_order')->get();
        $memberCount = ClubMember::where('club_id', $id)->where('status', 'approved')->count();
        $pendingCount = ClubMember::where('club_id', $id)->where('status', 'pending')->count();

        $adj = $this->adjacentPair(Club::class, $id, 'name', ['category' => $club->category]);
        return response()->json([
            'success' => true,
            'data' => $club,
            'is_member' => !!$grade,
            'my_grade' => $grade,
            'my_status' => $membership?->status,
            'boards' => $boards,
            'member_count' => $memberCount,
            'pending_count' => $pendingCount,
            'chat_room_id' => $club->chat_room_id,
            'prev' => $adj['prev'],
            'next' => $adj['next'],
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
            $data['image'] = $this->storeCompressedImageRaw($request->file('image'), 'clubs', 800, 80);
        }
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $this->storeCompressedImageRaw($request->file('cover_image'), 'clubs', 1600, 80);
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

        \App\Support\WritePoints::award(auth()->user(), Club::class, $club->id, '동호회 개설');

        return response()->json(['success' => true, 'data' => $club], 201);
    }

    public function update(Request $request, $id)
    {
        $club = Club::findOrFail($id);

        // 운영진(방장/관리자) 또는 사이트 관리자만 수정 가능
        if (!$this->isClubManager($id, auth()->id())) {
            return response()->json(['success' => false, 'message' => '동호회를 수정할 권한이 없어요'], 403);
        }

        // 빈 문자열은 null 로 들어오므로 필수 항목은 sometimes|required 로 막는다
        // (이전엔 name/category 가 null 로 저장돼 DB 오류(500)가 날 수 있었음)
        $request->validate([
            'name' => 'sometimes|required|max:100',
            'description' => 'nullable|max:2000',
            'rules' => 'nullable|max:2000',
            'category' => 'sometimes|required|max:30',
            'type' => 'sometimes|required|in:online,local',
            'city' => 'nullable|max:80',
            'state' => 'nullable|max:10',
            'zipcode' => 'nullable|max:10',
            'max_members' => 'nullable|integer|min:0',
            'is_public' => 'nullable|boolean',
            'image' => 'nullable|image|max:5120',
            'cover_image' => 'nullable|image|max:5120',
        ], [
            'name.required' => '동호회 이름을 입력해주세요',
            'name.max' => '동호회 이름은 100자 이내로 입력해주세요',
            'category.required' => '카테고리를 선택해주세요',
            'description.max' => '소개는 2000자 이내로 입력해주세요',
            'rules.max' => '규칙은 2000자 이내로 입력해주세요',
            'zipcode.max' => '우편번호는 10자 이내로 입력해주세요',
            'image.image' => '대표 이미지는 사진 파일만 올릴 수 있어요',
            'image.max' => '대표 이미지는 5MB 이하만 올릴 수 있어요',
            'cover_image.image' => '커버 이미지는 사진 파일만 올릴 수 있어요',
            'cover_image.max' => '커버 이미지는 5MB 이하만 올릴 수 있어요',
        ]);

        $data = $request->only('name', 'description', 'rules', 'category', 'type', 'city', 'state', 'zipcode', 'lat', 'lng');
        // 요청에 실제로 담긴 항목만 반영 (없는 컬럼은 건드리지 않음)
        $data = array_intersect_key($data, $request->all());

        if ($request->has('max_members')) {
            $data['max_members'] = (int) ($request->input('max_members') ?: 0); // NOT NULL 컬럼
        }
        if ($request->has('is_public')) {
            $data['is_public'] = filter_var($request->input('is_public'), FILTER_VALIDATE_BOOLEAN);
        }
        if ($request->hasFile('image')) {
            $data['image'] = $this->storeCompressedImageRaw($request->file('image'), 'clubs', 800, 80);
        } elseif (filter_var($request->input('remove_image', false), FILTER_VALIDATE_BOOLEAN)) {
            $data['image'] = null; // 파일 자체는 지우지 않고 연결만 해제
        }
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $this->storeCompressedImageRaw($request->file('cover_image'), 'clubs', 1600, 80);
        } elseif (filter_var($request->input('remove_cover_image', false), FILTER_VALIDATE_BOOLEAN)) {
            $data['cover_image'] = null;
        }

        $club->update($data);

        return response()->json(['success' => true, 'data' => $club->fresh()]);
    }

    /**
     * 동호회 삭제 — 방장(또는 사이트 관리자)만. 데이터를 지우지 않고 비활성화(보관)한다.
     * 목록/내 동호회에서 사라지고 상세 접근도 막히지만 글·멤버 기록은 DB 에 남는다.
     */
    public function destroy($id)
    {
        $club = Club::findOrFail($id);

        if (!$this->isClubOwner($club, auth()->id())) {
            return response()->json(['success' => false, 'message' => '방장만 동호회를 삭제할 수 있어요'], 403);
        }

        $club->update(['is_active' => false]);

        return response()->json(['success' => true, 'message' => '동호회가 삭제되었어요']);
    }

    /** 방장 본인(또는 사이트 관리자)인지 */
    private function isClubOwner(Club $club, $userId): bool
    {
        if ((int) $club->user_id === (int) $userId || $this->getMemberGrade($club->id, $userId) === 'owner') {
            return true;
        }
        return in_array(\App\Models\User::find($userId)?->role, ['admin', 'super_admin'], true);
    }

    /**
     * 방장 양도 — POST /clubs/{id}/transfer-owner {user_id}
     * 현재 방장(또는 사이트 관리자)만 호출 가능, 대상은 승인된 멤버여야 한다.
     * 기존 방장은 관리자(admin)로 남는다.
     */
    public function transferOwner(Request $request, $id)
    {
        $club = Club::findOrFail($id);
        $me = auth()->id();

        if (!$this->isClubOwner($club, $me)) {
            return response()->json(['success' => false, 'message' => '방장만 방장을 양도할 수 있어요'], 403);
        }

        $request->validate(['user_id' => 'required|integer'], [
            'user_id.required' => '방장을 넘겨받을 회원을 선택해주세요',
            'user_id.integer' => '방장을 넘겨받을 회원을 선택해주세요',
        ]);
        $targetId = (int) $request->input('user_id');
        $oldOwnerId = (int) $club->user_id;

        if ($targetId === $oldOwnerId || $targetId === (int) $me) {
            return response()->json(['success' => false, 'message' => '본인에게는 양도할 수 없어요'], 422);
        }

        $target = ClubMember::where('club_id', $id)->where('user_id', $targetId)->where('status', 'approved')->first();
        if (!$target) {
            return response()->json(['success' => false, 'message' => '승인된 동호회 멤버에게만 방장을 양도할 수 있어요'], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($club, $id, $target, $targetId, $oldOwnerId) {
            $target->update(['grade' => 'owner', 'role' => 'admin']);
            if ($oldOwnerId !== $targetId) {
                ClubMember::where('club_id', $id)->where('user_id', $oldOwnerId)->where('status', 'approved')
                    ->update(['grade' => 'admin', 'role' => 'admin']);
            }
            // 혹시 남아있는 다른 'owner' 등급이 있으면 정리 (방장은 항상 1명)
            ClubMember::where('club_id', $id)->where('grade', 'owner')->where('user_id', '!=', $targetId)
                ->update(['grade' => 'admin', 'role' => 'admin']);
            $club->update(['user_id' => $targetId]);
        });

        $this->notify($targetId, 'club_owner_transfer', '👑 동호회 방장이 되었어요', "{$club->name} 방장 권한이 양도되었습니다", ['club_id' => (int) $id]);
        if ($oldOwnerId && $oldOwnerId !== (int) $me) {
            $this->notify($oldOwnerId, 'club_owner_transfer', '동호회 방장이 변경되었어요', "{$club->name} 방장 권한이 다른 회원에게 양도되었습니다. 관리자로 남아요.", ['club_id' => (int) $id]);
        }

        return response()->json(['success' => true, 'message' => '방장을 양도했어요', 'data' => $club->fresh()->load('user:id,name,nickname')]);
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

        $managerIds = ClubMember::where('club_id', $id)->where('status', 'approved')
            ->whereIn('grade', ['owner', 'admin'])->pluck('user_id');
        foreach ($managerIds as $managerId) {
            $this->notify($managerId, 'club_join_request', '새 가입 신청이 도착했습니다', auth()->user()->name . "님이 '{$club->name}' 가입을 신청했습니다.", ['club_id' => $id]);
        }

        return response()->json(['success' => true, 'message' => '가입 신청이 완료되었습니다. 운영자 승인을 기다려주세요.', 'status' => 'pending']);
    }

    // 운영자: 가입 승인
    public function approveMember($id, $userId)
    {
        if (!$this->isClubManager($id, auth()->id())) return response()->json(['success' => false, 'message' => '권한이 없습니다'], 403);

        $member = ClubMember::where('club_id', $id)->where('user_id', $userId)->firstOrFail();
        $member->update(['status' => 'approved']);
        $club = Club::find($id);
        $club->increment('member_count');
        $this->notify($userId, 'club_join_approved', '동호회 가입이 승인되었습니다', "'{$club->name}' 가입이 승인되었습니다.", ['club_id' => $id]);

        // 신규 회원 가입 보상은 개설자에게 지급
        $owner = \App\Models\User::find($club->user_id);
        if ($owner) {
            \App\Support\MilestonePoints::award($owner, 'club_member_join', Club::class, $club->id, "동호회 신규가입: {$club->name}", 'club_member_join_daily_max');
        }

        return response()->json(['success' => true, 'message' => '승인되었습니다']);
    }

    // 운영자: 가입 거절
    public function rejectMember($id, $userId)
    {
        if (!$this->isClubManager($id, auth()->id())) return response()->json(['success' => false, 'message' => '권한이 없습니다'], 403);

        $member = ClubMember::where('club_id', $id)->where('user_id', $userId)->firstOrFail();
        $member->update(['status' => 'rejected']);
        $club = Club::find($id);
        $this->notify($userId, 'club_join_rejected', '동호회 가입이 거절되었습니다', "'{$club->name}' 가입 신청이 거절되었습니다.", ['club_id' => $id]);

        return response()->json(['success' => true, 'message' => '거절되었습니다']);
    }

    // 가입 대기 목록
    public function pendingMembers($id)
    {
        if (!$this->isClubManager($id, auth()->id())) return response()->json(['success' => false, 'message' => '권한이 없습니다'], 403);

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
            $others = ClubMember::where('club_id', $id)->where('status', 'approved')->where('user_id', '!=', auth()->id())->count();
            $msg = $others > 0
                ? '방장은 먼저 다른 회원에게 방장을 양도해야 나갈 수 있어요'
                : '혼자 남은 방장은 나갈 수 없어요. 동호회를 삭제해주세요';
            return response()->json(['success' => false, 'message' => $msg], 422);
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
                $imagesPaths[] = $this->storeCompressedImage($img, 'club_posts', 1200, 80);
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
                $imagesPaths[] = $this->storeCompressedImageRaw($img, 'club_posts', 1200, 80);
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

        // 이미 채팅방이 있으면 반환
        if ($club->chat_room_id) {
            $existing = ChatRoom::find($club->chat_room_id);
            if ($existing) return response()->json(['success' => true, 'data' => $existing]);
        }

        $request->validate(['name' => 'nullable|max:100']);

        $room = ChatRoom::create([
            'name' => $request->input('name', $club->name . ' 채팅방'),
            'type' => 'club',
            'created_by' => auth()->id(),
        ]);

        // club에 chat_room_id 저장
        $club->update(['chat_room_id' => $room->id]);

        // 승인된 멤버 전원 추가
        $memberIds = ClubMember::where('club_id', $id)->where('status', 'approved')->pluck('user_id');
        foreach ($memberIds as $uid) {
            ChatRoomUser::firstOrCreate(['chat_room_id' => $room->id, 'user_id' => $uid]);
        }

        return response()->json(['success' => true, 'data' => $room], 201);
    }
}
