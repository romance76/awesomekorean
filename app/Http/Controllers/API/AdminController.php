<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{User, Post, JobPost, MarketItem, Business, BusinessClaim, Event, News, Report, Board, Banner, IpBan, Payment, ChatRoom, ChatRoomUser, ChatMessage, ElderCheckinLog, ElderSosLog};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function overview() {
        return response()->json(['success'=>true,'data'=>[
            'total_users' => User::count(),
            'total_posts' => Post::count(),
            'posts_today' => Post::whereDate('created_at', today())->count(),
            'new_users_week' => User::where('created_at', '>=', now()->subWeek())->count(),
            'pending_reports' => Report::where('status','pending')->count(),
        ]]);
    }

    public function users(Request $request) {
        $query = User::query()
            ->when($request->search, fn($q,$v) => $q->where('name','like',"%{$v}%")->orWhere('email','like',"%{$v}%"))
            ->when($request->role, fn($q,$v) => $q->where('role', $v))
            ->orderByDesc('created_at');
        return response()->json(['success'=>true,'data'=>$query->paginate(20)]);
    }

    public function banUser(Request $request, $id) {
        User::findOrFail($id)->update(['is_banned'=>true,'ban_reason'=>$request->reason]);
        return response()->json(['success'=>true]);
    }

    public function unbanUser($id) {
        User::findOrFail($id)->update(['is_banned'=>false,'ban_reason'=>null]);
        return response()->json(['success'=>true]);
    }

    public function posts(Request $request) {
        $posts = Post::with('user:id,name','board:id,name')
            ->when($request->board_id, fn($q,$v) => $q->where('board_id',$v))
            ->orderByDesc('created_at')->paginate(20);
        return response()->json(['success'=>true,'data'=>$posts]);
    }

    public function hidePost($id) { Post::findOrFail($id)->update(['is_hidden'=>!Post::find($id)->is_hidden]); return response()->json(['success'=>true]); }
    public function pinPost($id) { Post::findOrFail($id)->update(['is_pinned'=>!Post::find($id)->is_pinned]); return response()->json(['success'=>true]); }
    public function deletePost($id) { Post::findOrFail($id)->delete(); return response()->json(['success'=>true]); }

    public function boards() { return response()->json(['success'=>true,'data'=>Board::orderBy('sort_order')->get()]); }
    public function createBoard(Request $request) { return response()->json(['success'=>true,'data'=>Board::create($request->only('name','slug','description','sort_order'))]); }
    public function updateBoard(Request $request, $id) { Board::findOrFail($id)->update($request->only('name','slug','description','sort_order','is_active')); return response()->json(['success'=>true]); }
    public function deleteBoard($id) { Board::findOrFail($id)->delete(); return response()->json(['success'=>true]); }

    public function reports(Request $request) { return response()->json(['success'=>true,'data'=>Report::orderByDesc('created_at')->paginate(20)]); }
    public function updateReport(Request $request, $id) { Report::findOrFail($id)->update($request->only('status','admin_note')); return response()->json(['success'=>true]); }

    public function banners() { return response()->json(['success'=>true,'data'=>Banner::orderBy('sort_order')->get()]); }
    public function createBanner(Request $request) { return response()->json(['success'=>true,'data'=>Banner::create($request->all())]); }
    public function deleteBanner($id) { Banner::findOrFail($id)->delete(); return response()->json(['success'=>true]); }

    public function ipBans() { return response()->json(['success'=>true,'data'=>IpBan::orderByDesc('created_at')->get()]); }
    public function createIpBan(Request $request) { IpBan::create(['ip_address'=>$request->ip_address,'reason'=>$request->reason,'banned_by'=>auth()->id()]); return response()->json(['success'=>true]); }
    public function deleteIpBan($id) { IpBan::findOrFail($id)->delete(); return response()->json(['success'=>true]); }

    public function payments(Request $request) { return response()->json(['success'=>true,'data'=>Payment::with('user:id,name')->orderByDesc('created_at')->paginate(20)]); }

    // 회원 상세 (관리자용 - 전체 정보)
    public function userDetail($id) {
        $user = User::findOrFail($id);
        $posts = Post::where('user_id',$id)->orderByDesc('created_at')->limit(20)->get();
        $payments = Payment::where('user_id',$id)->orderByDesc('created_at')->limit(20)->get();
        $points = \App\Models\PointHistory::where('user_id',$id)->orderByDesc('created_at')->limit(30)->get();
        $comments = \App\Models\Comment::where('user_id',$id)->orderByDesc('created_at')->limit(20)->get();
        $jobs = JobPost::where('user_id',$id)->orderByDesc('created_at')->limit(10)->get();
        $market = MarketItem::where('user_id',$id)->orderByDesc('created_at')->limit(10)->get();
        return response()->json(['success'=>true,'data'=>[
            'user'=>$user, 'posts'=>$posts, 'payments'=>$payments,
            'points'=>$points, 'comments'=>$comments, 'jobs'=>$jobs, 'market'=>$market,
        ]]);
    }

    // 회원 정보 수정 (관리자)
    public function updateUser(Request $request, $id) {
        $user = User::findOrFail($id);
        $user->update($request->only('name','nickname','email','role','points','game_points','city','state','phone','bio','is_banned','ban_reason'));
        return response()->json(['success'=>true,'data'=>$user->fresh(),'message'=>'회원 정보가 수정되었습니다']);
    }

    // 게시글 상세 (관리자 인라인 뷰용)
    public function postDetail($id) {
        $post = Post::with('user:id,name,email','board:id,name,slug','comments.user:id,name')->findOrFail($id);
        return response()->json(['success'=>true,'data'=>$post]);
    }

    // ─── 업소 클레임 관리 ───
    public function claims() {
        $claims = BusinessClaim::with('business:id,name,category,city', 'user:id,name,email,phone')
            ->orderByDesc('created_at')
            ->paginate(20);
        return response()->json(['success'=>true,'data'=>$claims]);
    }

    public function approveClaim($id) {
        $claim = BusinessClaim::findOrFail($id);
        $claim->update(['status' => 'approved']);
        $claim->business->update(['is_claimed' => true, 'owner_id' => $claim->user_id]);
        return response()->json(['success'=>true,'message'=>'클레임이 승인되었습니다']);
    }

    public function rejectClaim(Request $request, $id) {
        $claim = BusinessClaim::findOrFail($id);
        $claim->update(['status' => 'rejected', 'notes' => $request->notes]);
        $claim->business->update(['is_claimed' => false, 'owner_id' => null]);
        return response()->json(['success'=>true,'message'=>'클레임이 거절/취소되었습니다']);
    }

    // ═════════════════════════════════════
    //   안심서비스 관리
    // ═════════════════════════════════════

    public function elderOverview() {
        return response()->json(['success'=>true,'data'=>[
            'active_guardians' => DB::table('elder_guardians')->where('status', 'active')->count(),
            'pending_guardians' => DB::table('elder_guardians')->where('status', 'pending')->count(),
            'calls_today' => DB::table('elder_call_logs')->whereDate('called_at', today())->count(),
            'checkins_today' => ElderCheckinLog::whereDate('checked_in_at', today())->count(),
            'sos_unresolved' => ElderSosLog::whereNull('resolved_at')->count(),
            'total_schedules' => DB::table('elder_schedules')->where('is_active', true)->count(),
        ]]);
    }

    public function elderGuardians(Request $request) {
        $page = (int)($request->page ?? 1);
        $perPage = 20;

        $query = DB::table('elder_guardians as eg')
            ->leftJoin('users as guardian', 'eg.guardian_user_id', '=', 'guardian.id')
            ->leftJoin('users as ward', 'eg.ward_user_id', '=', 'ward.id')
            ->leftJoin('elder_schedules as es', 'es.elder_guardian_id', '=', 'eg.id')
            ->select(
                'eg.id', 'eg.status', 'eg.created_at',
                'guardian.id as guardian_id', 'guardian.name as guardian_name', 'guardian.email as guardian_email',
                'ward.id as ward_id', 'ward.name as ward_name', 'ward.email as ward_email', 'ward.phone as ward_phone', 'ward.city as ward_city', 'ward.state as ward_state',
                'es.type as schedule_type', 'es.time_start', 'es.time_end', 'es.calls_per_day', 'es.days', 'es.scheduled_times', 'es.is_active'
            );

        if ($request->search) {
            $s = "%{$request->search}%";
            $query->where(function($q) use ($s) {
                $q->where('guardian.name','like',$s)->orWhere('guardian.email','like',$s)
                  ->orWhere('ward.name','like',$s)->orWhere('ward.email','like',$s);
            });
        }
        if ($request->status) {
            $query->where('eg.status', $request->status);
        }

        $total = $query->count();
        $items = $query->orderByDesc('eg.created_at')->offset(($page-1)*$perPage)->limit($perPage)->get();

        return response()->json(['success'=>true,'data'=>[
            'data'=>$items,'total'=>$total,'per_page'=>$perPage,'current_page'=>$page,
            'last_page'=>max(1, ceil($total/$perPage)),
        ]]);
    }

    public function elderGuardianDetail($id) {
        $guardian = DB::table('elder_guardians')->where('id', $id)->first();
        if (!$guardian) return response()->json(['success'=>false,'message'=>'매칭을 찾을 수 없습니다'], 404);

        $guardianUser = User::select('id','name','nickname','email','phone','city','state','avatar')->find($guardian->guardian_user_id);
        $wardUser = User::select('id','name','nickname','email','phone','city','state','address','avatar','allow_elder_service')->find($guardian->ward_user_id);

        $schedule = DB::table('elder_schedules')->where('elder_guardian_id', $id)->first();
        if ($schedule) {
            $schedule->days = json_decode($schedule->days, true);
            $schedule->scheduled_times = json_decode($schedule->scheduled_times, true);
        }

        // 서비스 타입: random = 무료, scheduled = 유료
        $serviceType = $schedule ? ($schedule->type === 'scheduled' ? 'paid' : 'free') : 'none';

        // 통화 로그 (최근 30일)
        $callLogs = DB::table('elder_call_logs')
            ->where('elder_guardian_id', $id)
            ->orderByDesc('called_at')
            ->limit(100)
            ->get();

        // 통화 통계
        $callStats = [
            'total' => $callLogs->count(),
            'answered' => $callLogs->where('answered', true)->count(),
            'unanswered' => $callLogs->where('answered', false)->count(),
            'guardian_notified' => $callLogs->where('guardian_notified', true)->count(),
            'total_attempts' => $callLogs->sum('attempts'),
            'avg_attempts_to_answer' => $callLogs->where('answered', true)->count() > 0
                ? round($callLogs->where('answered', true)->avg('attempts'), 1) : 0,
            'last_call' => $callLogs->first()?->called_at,
            'today_calls' => $callLogs->filter(fn($l) => \Carbon\Carbon::parse($l->called_at)->isToday())->count(),
            'week_calls' => $callLogs->filter(fn($l) => \Carbon\Carbon::parse($l->called_at)->gte(now()->subWeek()))->count(),
        ];

        // 체크인 (ward의 것)
        $recentCheckins = ElderCheckinLog::where('user_id', $guardian->ward_user_id)
            ->orderByDesc('checked_in_at')
            ->limit(20)
            ->get();

        // SOS (ward의 것)
        $recentSos = ElderSosLog::where('user_id', $guardian->ward_user_id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // 체크인 통계
        $checkinStats = [
            'total' => ElderCheckinLog::where('user_id', $guardian->ward_user_id)->count(),
            'ok' => ElderCheckinLog::where('user_id', $guardian->ward_user_id)->where('status','ok')->count(),
            'missed' => ElderCheckinLog::where('user_id', $guardian->ward_user_id)->where('status','missed')->count(),
            'sos' => ElderCheckinLog::where('user_id', $guardian->ward_user_id)->where('status','sos')->count(),
        ];

        return response()->json(['success'=>true,'data'=>[
            'id' => $guardian->id,
            'status' => $guardian->status,
            'created_at' => $guardian->created_at,
            'guardian' => $guardianUser,
            'ward' => $wardUser,
            'schedule' => $schedule,
            'service_type' => $serviceType,  // free / paid / none
            'call_logs' => $callLogs,
            'call_stats' => $callStats,
            'recent_checkins' => $recentCheckins,
            'checkin_stats' => $checkinStats,
            'recent_sos' => $recentSos,
        ]]);
    }

    public function elderDeleteGuardian($id) {
        DB::table('elder_schedules')->where('elder_guardian_id', $id)->delete();
        DB::table('elder_call_logs')->where('elder_guardian_id', $id)->delete();
        DB::table('elder_guardians')->where('id', $id)->delete();
        return response()->json(['success'=>true,'message'=>'매칭이 해제되었습니다']);
    }

    public function elderCallLogs(Request $request) {
        $page = (int)($request->page ?? 1);
        $perPage = 20;

        $query = DB::table('elder_call_logs as cl')
            ->leftJoin('elder_guardians as eg', 'cl.elder_guardian_id', '=', 'eg.id')
            ->leftJoin('users as guardian', 'eg.guardian_user_id', '=', 'guardian.id')
            ->leftJoin('users as ward', 'cl.ward_user_id', '=', 'ward.id')
            ->select(
                'cl.id', 'cl.called_at', 'cl.answered', 'cl.attempts', 'cl.guardian_notified', 'cl.notes',
                'guardian.name as guardian_name', 'guardian.email as guardian_email',
                'ward.name as ward_name', 'ward.email as ward_email'
            );

        $total = $query->count();
        $items = $query->orderByDesc('cl.called_at')->offset(($page-1)*$perPage)->limit($perPage)->get();

        return response()->json(['success'=>true,'data'=>[
            'data'=>$items,'total'=>$total,'per_page'=>$perPage,'current_page'=>$page,
            'last_page'=>max(1, ceil($total/$perPage)),
        ]]);
    }

    public function elderCheckins(Request $request) {
        $checkins = ElderCheckinLog::with('user:id,name,email')
            ->orderByDesc('checked_in_at')->paginate(20);
        return response()->json(['success'=>true,'data'=>$checkins]);
    }

    public function elderSosLogs(Request $request) {
        $sos = ElderSosLog::with('user:id,name,email')
            ->orderByDesc('created_at')->paginate(20);
        return response()->json(['success'=>true,'data'=>$sos]);
    }

    // ═════════════════════════════════════
    //   채팅 관리
    // ═════════════════════════════════════

    public function chatRooms(Request $request) {
        $query = ChatRoom::withCount('users')
            ->with(['messages' => fn($q) => $q->latest()->limit(1)->with('user:id,name,nickname')])
            ->when($request->search, fn($q, $v) => $q->where('name', 'like', "%{$v}%"));

        $rooms = $query->orderByDesc('updated_at')->paginate(20);
        return response()->json(['success'=>true,'data'=>$rooms]);
    }

    public function chatCreateRoom(Request $request) {
        $request->validate(['name' => 'required|string|max:100']);
        $room = ChatRoom::create([
            'name' => $request->name,
            'type' => $request->type ?? 'public',
            'created_by' => auth()->id(),
        ]);
        ChatRoomUser::create(['chat_room_id' => $room->id, 'user_id' => auth()->id()]);
        return response()->json(['success'=>true,'data'=>$room],201);
    }

    public function chatDeleteRoom($id) {
        DB::table('chat_room_bans')->where('chat_room_id', $id)->delete();
        ChatRoom::findOrFail($id)->delete();
        return response()->json(['success'=>true,'message'=>'방이 삭제되었습니다']);
    }

    public function chatRoomDetail($id) {
        $room = ChatRoom::withCount('users')->findOrFail($id);

        $members = DB::table('chat_room_users as cru')
            ->join('users as u', 'cru.user_id', '=', 'u.id')
            ->where('cru.chat_room_id', $id)
            ->select('u.id', 'u.name', 'u.nickname', 'u.email', 'u.avatar', 'u.created_at as user_joined_at', 'u.is_banned', 'cru.created_at as joined_at')
            ->orderBy('cru.created_at')
            ->get();

        $messages = ChatMessage::with('user:id,name,nickname,avatar')
            ->where('chat_room_id', $id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $bans = DB::table('chat_room_bans as crb')
            ->join('users as u', 'crb.user_id', '=', 'u.id')
            ->where('crb.chat_room_id', $id)
            ->select('u.id', 'u.name', 'u.nickname', 'u.email', 'u.avatar', 'crb.reason', 'crb.created_at as banned_at')
            ->get();

        // ─── 신고 (이 방의 메시지에 대한 것) ───
        $messageIds = ChatMessage::where('chat_room_id', $id)->pluck('id');
        $reports = DB::table('reports as r')
            ->leftJoin('users as reporter', 'r.reporter_id', '=', 'reporter.id')
            ->leftJoin('chat_messages as cm', 'cm.id', '=', 'r.reportable_id')
            ->leftJoin('users as target', 'cm.user_id', '=', 'target.id')
            ->where('r.reportable_type', 'chat_message')
            ->whereIn('r.reportable_id', $messageIds)
            ->select(
                'r.id', 'r.reason', 'r.content as report_content', 'r.status', 'r.created_at',
                'r.reportable_id as message_id',
                'reporter.id as reporter_id', 'reporter.name as reporter_name', 'reporter.email as reporter_email',
                'target.id as target_user_id', 'target.name as target_name', 'target.email as target_email',
                'cm.content as message_content'
            )
            ->orderByDesc('r.created_at')
            ->get();

        // ─── 공지 (system 메시지) ───
        $announcements = ChatMessage::with('user:id,name,nickname,avatar')
            ->where('chat_room_id', $id)
            ->where('type', 'system')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        // ─── 영구제명 리스트 (사이트 전체) ───
        $permanentBanned = User::where('is_banned', true)
            ->select('id','name','nickname','email','avatar','ban_reason','updated_at')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        // ─── 유저별 신고 누적 카운트 (이 방 멤버 기준) ───
        $memberIds = $members->pluck('id')->toArray();
        $userReportCounts = [];
        if (!empty($memberIds)) {
            $counts = DB::table('reports as r')
                ->join('chat_messages as cm', function($j) {
                    $j->on('cm.id', '=', 'r.reportable_id');
                })
                ->where('r.reportable_type', 'chat_message')
                ->whereIn('cm.user_id', $memberIds)
                ->select('cm.user_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('cm.user_id')
                ->pluck('cnt', 'cm.user_id');
            $userReportCounts = $counts->toArray();
        }

        // 각 메시지에 user_report_count 주입
        $messages = $messages->map(function($m) use ($userReportCounts) {
            $m->user_report_count = $userReportCounts[$m->user_id] ?? 0;
            return $m;
        });

        return response()->json(['success'=>true,'data'=>[
            'room' => $room,
            'members' => $members,
            'messages' => $messages,
            'bans' => $bans,
            'reports' => $reports,
            'announcements' => $announcements,
            'permanent_banned' => $permanentBanned,
            'user_report_counts' => $userReportCounts,
        ]]);
    }

    public function chatRoomMessages($id, Request $request) {
        $messages = ChatMessage::with('user:id,name,nickname,avatar')
            ->where('chat_room_id', $id)
            ->orderByDesc('created_at')
            ->paginate(50);
        return response()->json(['success'=>true,'data'=>$messages]);
    }

    public function chatAnnounce(Request $request, $id) {
        $request->validate(['content' => 'required|string']);
        ChatRoom::findOrFail($id);

        $msg = ChatMessage::create([
            'chat_room_id' => $id,
            'user_id' => auth()->id(),
            'content' => '📢 [공지] ' . $request->content,
            'type' => 'system',
        ]);

        try { event(new \App\Events\MessageSent($msg->load('user:id,name,nickname,avatar'))); } catch (\Exception $e) {}
        return response()->json(['success'=>true,'data'=>$msg->load('user:id,name,nickname,avatar'),'message'=>'공지가 발송되었습니다']);
    }

    public function chatKickMember($id, $userId) {
        ChatRoomUser::where('chat_room_id', $id)->where('user_id', $userId)->delete();

        // 시스템 메시지로 기록
        try {
            $user = User::find($userId);
            ChatMessage::create([
                'chat_room_id' => $id,
                'user_id' => auth()->id(),
                'content' => '🚪 ' . ($user->nickname ?? $user->name ?? "유저#{$userId}") . ' 님이 강퇴되었습니다.',
                'type' => 'system',
            ]);
        } catch (\Exception $e) {}

        return response()->json(['success'=>true,'message'=>'강퇴되었습니다']);
    }

    public function chatBanMember(Request $request, $id, $userId) {
        ChatRoomUser::where('chat_room_id', $id)->where('user_id', $userId)->delete();

        DB::table('chat_room_bans')->updateOrInsert(
            ['chat_room_id' => $id, 'user_id' => $userId],
            ['banned_by' => auth()->id(), 'reason' => $request->reason, 'created_at' => now(), 'updated_at' => now()]
        );

        // 시스템 메시지로 기록
        try {
            $user = User::find($userId);
            ChatMessage::create([
                'chat_room_id' => $id,
                'user_id' => auth()->id(),
                'content' => '🚫 ' . ($user->nickname ?? $user->name ?? "유저#{$userId}") . ' 님이 차단되었습니다.',
                'type' => 'system',
            ]);
        } catch (\Exception $e) {}

        return response()->json(['success'=>true,'message'=>'차단되었습니다']);
    }

    public function chatUnbanMember($id, $userId) {
        DB::table('chat_room_bans')->where('chat_room_id', $id)->where('user_id', $userId)->delete();
        return response()->json(['success'=>true,'message'=>'차단이 해제되었습니다']);
    }

    public function chatDeleteMessage($id, $messageId) {
        ChatMessage::where('chat_room_id', $id)->where('id', $messageId)->delete();
        return response()->json(['success'=>true,'message'=>'메시지가 삭제되었습니다']);
    }

    // ─── 신고 해결 ───
    public function chatResolveReport($id, $reportId) {
        Report::findOrFail($reportId)->update(['status' => 'resolved', 'admin_note' => '채팅 관리에서 해결 처리']);
        return response()->json(['success'=>true,'message'=>'신고가 해결 처리되었습니다']);
    }

    // ─── 영구제명 (사이트 전체) ───
    public function chatPermaBan(Request $request, $userId) {
        $user = User::findOrFail($userId);
        if ($user->role === 'admin' || $user->role === 'super_admin') {
            return response()->json(['success'=>false,'message'=>'관리자는 영구제명할 수 없습니다'], 403);
        }
        $user->update([
            'is_banned' => true,
            'ban_reason' => $request->reason ?: '채팅 관리자 영구제명',
        ]);
        // 모든 채팅방에서 즉시 제거
        ChatRoomUser::where('user_id', $userId)->delete();
        return response()->json(['success'=>true,'message'=>'영구제명되었습니다']);
    }

    // ─── 영구제명 리스트 ───
    public function chatPermaBanList() {
        $users = User::where('is_banned', true)
            ->select('id','name','nickname','email','avatar','ban_reason','updated_at')
            ->orderByDesc('updated_at')
            ->get();
        return response()->json(['success'=>true,'data'=>$users]);
    }
}
