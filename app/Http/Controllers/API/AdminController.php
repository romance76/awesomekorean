<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Post;
use App\Models\Board;
use App\Models\Banner;
use App\Models\JobPost;
use App\Models\MarketItem;
use App\Models\Business;
use App\Models\Report;
use App\Models\ChatRoom;
use App\Models\ChatMessage;
use App\Models\Club;
use App\Models\Event;
use App\Models\News;
use App\Models\Short;
use App\Models\ShoppingDeal;
use App\Models\GameSetting;
use App\Models\IpBan;
use App\Models\ElderSetting;
use App\Models\Payment;
use App\Models\RealEstateListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AdminController extends Controller
{
    // =========================================================================
    // 대시보드 / Dashboard
    // =========================================================================

    /**
     * GET /api/admin/overview
     * 대시보드 개요 통계
     */
    public function overview()
    {
        try {
            $data = [
                'total_users'        => User::count(),
                'new_signups_week'   => User::where('created_at', '>=', now()->subWeek())->count(),
                'new_signups_today'  => User::whereDate('created_at', today())->count(),
                'active_users'       => User::where('last_login_at', '>=', now()->subDays(7))->count(),
                'banned_users'       => User::where('is_banned', true)->count(),
                'posts_today'        => Post::whereDate('created_at', today())->count(),
                'total_posts'        => Post::count(),
                'total_jobs'         => JobPost::count(),
                'total_market'       => MarketItem::count(),
                'total_businesses'   => Business::count(),
                'total_events'       => Event::count(),
                'total_clubs'        => Club::count(),
                'pending_reports'    => Report::where('status', 'pending')->count(),
                'total_chat_rooms'   => ChatRoom::count(),
            ];

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/admin/stats
     * 상세 통계 (차트 데이터 포함)
     */
    public function stats()
    {
        try {
            // 월별 사용자 가입 (최근 6개월)
            $usersByMonth = [];
            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $usersByMonth[] = [
                    'month' => $month->format('Y-m'),
                    'label' => $month->format('m월'),
                    'count' => User::whereYear('created_at', $month->year)
                                   ->whereMonth('created_at', $month->month)
                                   ->count(),
                ];
            }

            // 게시판별 게시글 수
            $postsByBoard = Board::withCount('posts')
                ->orderByDesc('posts_count')
                ->get()
                ->map(fn($b) => ['board' => $b->name, 'count' => $b->posts_count]);

            // 일별 활동 (최근 7일)
            $dailyActivity = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->toDateString();
                $dailyActivity[] = [
                    'date'     => now()->subDays($i)->format('m/d'),
                    'users'    => User::whereDate('created_at', $date)->count(),
                    'posts'    => Post::whereDate('created_at', $date)->count(),
                    'comments' => DB::table('comments')->whereDate('created_at', $date)->count(),
                ];
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'users_by_month'  => $usersByMonth,
                    'posts_by_board'  => $postsByBoard,
                    'daily_activity'  => $dailyActivity,
                    'totals' => [
                        'users'      => User::count(),
                        'posts'      => Post::count(),
                        'jobs'       => JobPost::count(),
                        'market'     => MarketItem::count(),
                        'businesses' => Business::count(),
                        'events'     => Event::count(),
                        'clubs'      => Club::count(),
                        'reports'    => Report::where('status', 'pending')->count(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 회원 관리 / Users Management
    // =========================================================================

    /**
     * GET /api/admin/users
     * 회원 목록 (검색/역할/상태 필터)
     */
    public function users(Request $request)
    {
        try {
            $query = User::query();

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('nickname', 'like', "%{$search}%");
                });
            }

            if ($role = $request->input('role')) {
                $query->where('role', $role);
            }

            if ($request->has('is_banned')) {
                $query->where('is_banned', (bool) $request->input('is_banned'));
            }

            $users = $query->orderByDesc('created_at')->paginate($request->input('per_page', 25));

            return response()->json(['success' => true, 'data' => $users]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/admin/users/{id}
     * 회원 정보 수정 (역할/상태)
     */
    public function updateUser(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'name'     => 'sometimes|string|max:100',
                'nickname' => 'sometimes|string|max:100',
                'email'    => 'sometimes|email',
                'role'     => 'sometimes|string|in:user,admin,guardian',
                'phone'    => 'sometimes|nullable|string|max:30',
                'bio'      => 'sometimes|nullable|string|max:500',
            ]);

            $user->update($validated);

            return response()->json(['success' => true, 'data' => $user->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/users/{id}/ban
     * 회원 정지
     */
    public function banUser($id)
    {
        try {
            $user = User::findOrFail($id);

            if ($user->role === 'admin') {
                return response()->json(['success' => false, 'message' => '관리자는 정지할 수 없습니다.'], 400);
            }

            $user->update([
                'is_banned'  => true,
                'ban_reason' => request('reason', '관리자에 의한 정지'),
            ]);

            return response()->json(['success' => true, 'message' => "{$user->name} 계정이 정지되었습니다."]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/users/{id}/unban
     * 회원 정지 해제
     */
    public function unbanUser($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->update(['is_banned' => false, 'ban_reason' => null]);

            return response()->json(['success' => true, 'message' => "{$user->name} 계정 정지가 해제되었습니다."]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/users/{id}/make-admin
     * 관리자 권한 부여
     */
    public function makeAdmin($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->update(['role' => 'admin']);

            return response()->json(['success' => true, 'message' => "{$user->name}에게 관리자 권한이 부여되었습니다."]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 콘텐츠 관리 / Content Management (Posts)
    // =========================================================================

    /**
     * GET /api/admin/posts
     * 게시글 목록 (게시판/상태 필터)
     */
    public function posts(Request $request)
    {
        try {
            $query = Post::with('user:id,name,nickname', 'board:id,name');

            if ($search = $request->input('search')) {
                $query->where('title', 'like', "%{$search}%");
            }

            if ($boardId = $request->input('board_id')) {
                $query->where('board_id', $boardId);
            }

            if ($status = $request->input('status')) {
                if ($status === 'hidden') {
                    $query->where('is_hidden', true);
                } elseif ($status === 'active') {
                    $query->where('is_hidden', false);
                }
            }

            $posts = $query->orderByDesc('created_at')->paginate($request->input('per_page', 25));

            return response()->json(['success' => true, 'data' => $posts]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/admin/posts/{id}
     * 게시글 숨김 처리 (soft hide)
     */
    public function deletePost($id)
    {
        try {
            $post = Post::findOrFail($id);
            $post->update(['is_hidden' => true]);

            return response()->json(['success' => true, 'message' => '게시글이 숨김 처리되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/posts/{id}/pin
     * 게시글 고정 토글
     */
    public function pinPost($id)
    {
        try {
            $post = Post::findOrFail($id);
            $post->update(['is_pinned' => !$post->is_pinned]);

            $status = $post->is_pinned ? '고정' : '고정 해제';
            return response()->json(['success' => true, 'message' => "게시글이 {$status}되었습니다.", 'is_pinned' => $post->is_pinned]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/posts/{id}/hide
     * 게시글 숨김 토글
     */
    public function hidePost($id)
    {
        try {
            $post = Post::findOrFail($id);
            $newHidden = !$post->is_hidden;
            $post->update(['is_hidden' => $newHidden]);

            $label = $newHidden ? '숨김' : '공개';
            return response()->json(['success' => true, 'message' => "게시글이 {$label} 처리되었습니다.", 'is_hidden' => $newHidden]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 게시판 관리 / Board Management
    // =========================================================================

    /**
     * GET /api/admin/boards
     */
    public function boards()
    {
        try {
            $boards = Board::withCount('posts')->orderBy('sort_order')->get();
            return response()->json(['success' => true, 'data' => $boards]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/boards
     */
    public function createBoard(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'        => 'required|string|max:100',
                'slug'        => 'required|string|max:100|unique:boards,slug',
                'description' => 'nullable|string|max:500',
                'sort_order'  => 'nullable|integer',
                'is_active'   => 'nullable|boolean',
            ]);

            $board = Board::create($validated);

            return response()->json(['success' => true, 'data' => $board], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/admin/boards/{id}
     */
    public function updateBoard(Request $request, $id)
    {
        try {
            $board = Board::findOrFail($id);

            $validated = $request->validate([
                'name'        => 'sometimes|string|max:100',
                'slug'        => 'sometimes|string|max:100|unique:boards,slug,' . $id,
                'description' => 'nullable|string|max:500',
                'sort_order'  => 'nullable|integer',
                'is_active'   => 'nullable|boolean',
            ]);

            $board->update($validated);

            return response()->json(['success' => true, 'data' => $board->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/admin/boards/{id}
     */
    public function deleteBoard($id)
    {
        try {
            $board = Board::findOrFail($id);

            if ($board->posts()->count() > 0) {
                return response()->json(['success' => false, 'message' => '게시글이 있는 게시판은 삭제할 수 없습니다.'], 400);
            }

            $board->delete();

            return response()->json(['success' => true, 'message' => '게시판이 삭제되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 구인구직 / Jobs
    // =========================================================================

    /**
     * GET /api/admin/jobs
     */
    public function jobs(Request $request)
    {
        try {
            $query = JobPost::with('user:id,name,nickname');

            if ($search = $request->input('search')) {
                $query->where('title', 'like', "%{$search}%");
            }
            if ($status = $request->input('status')) {
                if ($status === 'active') {
                    $query->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $query->where('is_active', false);
                }
            }

            return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->paginate(25)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/admin/jobs/{id}
     */
    public function deleteJob($id)
    {
        try {
            JobPost::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => '구인구직 글이 삭제되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 중고장터 / Market
    // =========================================================================

    /**
     * GET /api/admin/market
     */
    public function marketItems(Request $request)
    {
        try {
            $query = MarketItem::with('user:id,name,nickname');

            if ($search = $request->input('search')) {
                $query->where('title', 'like', "%{$search}%");
            }
            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }

            return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->paginate(25)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/admin/market/{id}
     */
    public function deleteMarketItem($id)
    {
        try {
            MarketItem::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => '중고장터 글이 삭제되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 부동산 / Real Estate
    // =========================================================================

    /**
     * GET /api/admin/realestate
     */
    public function realestate(Request $request)
    {
        try {
            $query = RealEstateListing::with('user:id,name,nickname');

            if ($search = $request->input('search')) {
                $query->where('title', 'like', "%{$search}%");
            }

            return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->paginate(25)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/admin/realestate/{id}
     */
    public function deleteRealestate($id)
    {
        try {
            RealEstateListing::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => '부동산 글이 삭제되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 동호회 / Clubs
    // =========================================================================

    /**
     * GET /api/admin/clubs
     */
    public function clubs(Request $request)
    {
        try {
            $query = Club::withCount('members');

            if ($search = $request->input('search')) {
                $query->where('name', 'like', "%{$search}%");
            }

            return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->paginate(25)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/admin/clubs/{id}
     */
    public function deleteClub($id)
    {
        try {
            Club::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => '동호회가 삭제되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 뉴스 / News
    // =========================================================================

    /**
     * GET /api/admin/news
     */
    public function news(Request $request)
    {
        try {
            $query = News::query();

            if ($search = $request->input('search')) {
                $query->where('title', 'like', "%{$search}%");
            }
            if ($category = $request->input('category')) {
                $query->where('category', $category);
            }
            if ($source = $request->input('source')) {
                $query->where('source', $source);
            }

            return response()->json(['success' => true, 'data' => $query->orderByDesc('published_at')->paginate(25)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/admin/news/{id}
     */
    public function deleteNews($id)
    {
        try {
            News::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => '뉴스가 삭제되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 이벤트 / Events
    // =========================================================================

    /**
     * GET /api/admin/events
     */
    public function events(Request $request)
    {
        try {
            $query = Event::query();

            if ($search = $request->input('search')) {
                $query->where('title', 'like', "%{$search}%");
            }

            return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->paginate(25)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/admin/events/{id}
     */
    public function deleteEvent($id)
    {
        try {
            Event::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => '이벤트가 삭제되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 숏츠 / Shorts
    // =========================================================================

    /**
     * GET /api/admin/shorts
     */
    public function shorts(Request $request)
    {
        try {
            $query = Short::query();

            if ($search = $request->input('search')) {
                $query->where('title', 'like', "%{$search}%");
            }
            if ($platform = $request->input('platform')) {
                $query->where('platform', $platform);
            }
            if ($request->input('status') === 'active') {
                $query->where('is_active', true);
            } elseif ($request->input('status') === 'hidden') {
                $query->where('is_active', false);
            }

            return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->paginate(25)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/admin/shorts/{id}
     */
    public function deleteShort($id)
    {
        try {
            Short::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => '숏츠가 삭제되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 쇼핑 / Shopping
    // =========================================================================

    /**
     * GET /api/admin/shopping
     */
    public function shopping(Request $request)
    {
        try {
            $query = ShoppingDeal::query();

            if ($search = $request->input('search')) {
                $query->where('title', 'like', "%{$search}%");
            }

            return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->paginate(25)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'data' => ['data' => [], 'total' => 0]]);
        }
    }

    // =========================================================================
    // 신고 관리 / Reports
    // =========================================================================

    /**
     * GET /api/admin/reports
     */
    public function reports(Request $request)
    {
        try {
            $query = Report::with(['reporter:id,name,nickname']);

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            } else {
                $query->where('status', 'pending');
            }

            if ($type = $request->input('type')) {
                $query->where('reportable_type', 'like', "%{$type}%");
            }

            $reports = $query->orderByDesc('created_at')->paginate(25);

            $stats = [
                'total'   => Report::count(),
                'pending' => Report::where('status', 'pending')->count(),
                'today'   => Report::whereDate('created_at', today())->count(),
            ];

            return response()->json(['success' => true, 'data' => $reports, 'stats' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/admin/reports/{id}
     * 신고 상태/메모 업데이트
     */
    public function updateReport(Request $request, $id)
    {
        try {
            $report = Report::findOrFail($id);

            $validated = $request->validate([
                'status'     => 'sometimes|string|in:pending,resolved,dismissed',
                'admin_note' => 'sometimes|nullable|string|max:1000',
                'action'     => 'sometimes|nullable|string|in:dismiss,hide,delete,ban_user',
            ]);

            $updateData = [
                'reviewed_by' => auth()->id(),
                'updated_at'  => now(),
            ];

            if (isset($validated['status'])) {
                $updateData['status'] = $validated['status'];
            }
            if (isset($validated['admin_note'])) {
                $updateData['admin_note'] = $validated['admin_note'];
            }
            if (isset($validated['action'])) {
                $updateData['resolved_action'] = $validated['action'];
                $this->executeReportAction($validated['action'], $report);
            }

            $report->update($updateData);

            return response()->json(['success' => true, 'message' => '신고가 처리되었습니다.', 'data' => $report->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 신고 처리 액션 실행
     */
    private function executeReportAction(string $action, Report $report): void
    {
        $typeTableMap = [
            'App\\Models\\Post'       => 'posts',
            'App\\Models\\Comment'    => 'comments',
            'App\\Models\\MarketItem' => 'market_items',
            'App\\Models\\JobPost'    => 'job_posts',
            'App\\Models\\Business'   => 'businesses',
        ];

        $table = $typeTableMap[$report->reportable_type] ?? null;
        if (!$table) return;

        switch ($action) {
            case 'hide':
                // posts/comments use is_hidden, market_items has status column
                if (in_array($table, ['posts', 'comments'])) {
                    DB::table($table)->where('id', $report->reportable_id)->update(['is_hidden' => true]);
                } else {
                    DB::table($table)->where('id', $report->reportable_id)->update(['is_hidden' => true]);
                }
                break;
            case 'delete':
                DB::table($table)->where('id', $report->reportable_id)->delete();
                break;
            case 'ban_user':
                $content = DB::table($table)->where('id', $report->reportable_id)->first();
                if ($content && isset($content->user_id)) {
                    User::where('id', $content->user_id)->update(['is_banned' => true, 'ban_reason' => '신고 처리에 의한 정지']);
                }
                break;
        }
    }

    // =========================================================================
    // 채팅 모니터링 / Chats
    // =========================================================================

    /**
     * GET /api/admin/chats
     */
    public function chats(Request $request)
    {
        try {
            $query = ChatRoom::withCount('messages');

            if ($search = $request->input('search')) {
                $query->where('name', 'like', "%{$search}%");
            }

            $rooms = $query->orderByDesc('created_at')->paginate(25);

            $stats = [
                'total_rooms'    => ChatRoom::count(),
                'total_messages' => ChatMessage::count(),
                'today_messages' => ChatMessage::whereDate('created_at', today())->count(),
            ];

            return response()->json(['success' => true, 'data' => $rooms, 'stats' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 배너 관리 / Banners
    // =========================================================================

    /**
     * GET /api/admin/banners
     */
    public function banners()
    {
        try {
            $banners = Banner::orderBy('sort_order')->get();
            return response()->json(['success' => true, 'data' => $banners]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/banners
     */
    public function createBanner(Request $request)
    {
        try {
            $validated = $request->validate([
                'title'      => 'required|string|max:200',
                'image_url'  => 'nullable|string|max:500',
                'image'      => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
                'link_url'   => 'nullable|string|max:500',
                'position'   => 'nullable|string|max:50',
                'sort_order' => 'nullable|integer',
                'is_active'  => 'nullable|boolean',
                'starts_at'  => 'nullable|date',
                'ends_at'    => 'nullable|date',
            ]);

            // 이미지 업로드 처리
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = 'banner_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('images/banners'), $filename);
                $validated['image_url'] = '/images/banners/' . $filename;
            }

            unset($validated['image']);
            $banner = Banner::create($validated);

            return response()->json(['success' => true, 'data' => $banner], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/admin/banners/{id}
     */
    public function updateBanner(Request $request, $id)
    {
        try {
            $banner = Banner::findOrFail($id);

            $validated = $request->validate([
                'title'      => 'sometimes|string|max:200',
                'image_url'  => 'nullable|string|max:500',
                'image'      => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
                'link_url'   => 'nullable|string|max:500',
                'position'   => 'nullable|string|max:50',
                'sort_order' => 'nullable|integer',
                'is_active'  => 'nullable|boolean',
                'starts_at'  => 'nullable|date',
                'ends_at'    => 'nullable|date',
            ]);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = 'banner_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('images/banners'), $filename);
                $validated['image_url'] = '/images/banners/' . $filename;
            }

            unset($validated['image']);
            $banner->update($validated);

            return response()->json(['success' => true, 'data' => $banner->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/admin/banners/{id}
     */
    public function deleteBanner($id)
    {
        try {
            Banner::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => '배너가 삭제되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // IP 차단 관리 / IP Bans
    // =========================================================================

    /**
     * GET /api/admin/ip-bans
     */
    public function ipBans()
    {
        try {
            $bans = IpBan::orderByDesc('created_at')->paginate(25);

            $stats = [
                'total'     => IpBan::count(),
                'active'    => IpBan::active()->count(),
                'auto'      => IpBan::where('reason', 'like', '%자동%')->count(),
                'permanent' => IpBan::whereNull('expires_at')->count(),
            ];

            return response()->json(['success' => true, 'data' => $bans, 'stats' => $stats]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/ip-bans
     */
    public function createIpBan(Request $request)
    {
        try {
            $validated = $request->validate([
                'ip_address' => 'required|string|max:45',
                'reason'     => 'required|string|max:500',
                'duration'   => 'required|string|in:1h,1d,7d,30d,permanent',
            ]);

            $expiresAt = match ($validated['duration']) {
                '1h'        => now()->addHour(),
                '1d'        => now()->addDay(),
                '7d'        => now()->addDays(7),
                '30d'       => now()->addDays(30),
                'permanent' => null,
            };

            $ban = IpBan::create([
                'ip_address' => $validated['ip_address'],
                'reason'     => $validated['reason'],
                'banned_by'  => auth()->id(),
                'expires_at' => $expiresAt,
            ]);

            // 캐시 무효화
            Cache::forget("ip_ban:{$validated['ip_address']}");

            return response()->json(['success' => true, 'data' => $ban, 'message' => 'IP가 차단되었습니다.'], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/admin/ip-bans/{id}
     */
    public function deleteIpBan($id)
    {
        try {
            $ban = IpBan::findOrFail($id);
            $ip = $ban->ip_address;
            $ban->delete();

            // 캐시 무효화
            Cache::forget("ip_ban:{$ip}");

            return response()->json(['success' => true, 'message' => 'IP 차단이 해제되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 게임 설정 / Game Settings
    // =========================================================================

    /**
     * GET /api/admin/game-settings
     */
    public function gameSettings()
    {
        try {
            $settings = GameSetting::all()->groupBy('game_type');
            return response()->json(['success' => true, 'data' => $settings]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/admin/game-settings
     */
    public function updateGameSettings(Request $request)
    {
        try {
            $validated = $request->validate([
                'settings'              => 'required|array',
                'settings.*.game_type'  => 'required|string',
                'settings.*.key'        => 'required|string',
                'settings.*.value'      => 'required|string',
            ]);

            foreach ($validated['settings'] as $setting) {
                GameSetting::updateOrCreate(
                    ['game_type' => $setting['game_type'], 'key' => $setting['key']],
                    ['value' => $setting['value']]
                );
            }

            return response()->json(['success' => true, 'message' => '게임 설정이 저장되었습니다.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 결제 관리 / Payments
    // =========================================================================

    /**
     * GET /api/admin/payments
     */
    public function payments(Request $request)
    {
        try {
            $query = Payment::with('user:id,name,email');

            if ($status = $request->input('status')) {
                $query->where('status', $status);
            }
            if ($from = $request->input('from')) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to = $request->input('to')) {
                $query->whereDate('created_at', '<=', $to);
            }

            $payments = $query->orderByDesc('created_at')->paginate(25);

            return response()->json(['success' => true, 'data' => $payments]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 노인 안심 / Elder
    // =========================================================================

    /**
     * GET /api/admin/elder-users
     */
    public function elderUsers(Request $request)
    {
        try {
            $query = ElderSetting::with('user:id,name,nickname,email')
                ->where('elder_mode', true);

            $elders = $query->get()->map(function ($setting) {
                $overdue = true;
                if ($setting->last_checkin_at) {
                    $overdue = $setting->last_checkin_at->diffInHours(now()) > ($setting->checkin_interval ?? 24);
                }

                return [
                    'id'               => $setting->id,
                    'user'             => $setting->user,
                    'guardian_name'    => $setting->guardian_name,
                    'guardian_phone'   => $setting->guardian_phone
                        ? substr($setting->guardian_phone, 0, 3) . '****' . substr($setting->guardian_phone, -4)
                        : null,
                    'checkin_interval' => $setting->checkin_interval,
                    'last_checkin_at'  => $setting->last_checkin_at,
                    'last_sos_at'      => $setting->last_sos_at,
                    'is_overdue'       => $overdue,
                    'alert_sent'       => $setting->alert_sent ?? false,
                ];
            });

            return response()->json(['success' => true, 'data' => $elders]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }
}
