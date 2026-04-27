<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{
    MarketItem, JobPost, RealEstateListing, Event, Club, QaPost, RecipePost,
    Post, News, Business, Comment, Report, BannerAd, PointLog,
    QaCategory, MusicCategory, NewsCategory, RecipeCategory, Board,
    MusicTrack, GroupBuy, Short
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminBoardController extends Controller
{
    /**
     * 게시판별 통합 관리 API
     * slug 는 URL 경로 (market, qa, jobs, realestate, events, clubs, recipes, community, news)
     */
    protected array $registry = [
        'market'     => ['model' => MarketItem::class,        'label' => '장터',    'icon' => '🛒', 'has_category_field' => true,  'category_model' => null],
        'jobs'       => ['model' => JobPost::class,           'label' => '구인구직', 'icon' => '💼', 'has_category_field' => true,  'category_model' => null, 'major_type_field' => 'post_type', 'major_types' => ['hiring' => '💼 구인', 'seeking' => '🙋 구직']],
        'realestate' => ['model' => RealEstateListing::class, 'label' => '부동산',  'icon' => '🏠', 'has_category_field' => true,  'category_model' => null, 'category_field' => 'property_type', 'major_type_field' => 'type', 'major_types' => ['rent' => '🏘 렌트', 'sale' => '💰 매매', 'roommate' => '👥 룸메이트']],
        'events'     => ['model' => Event::class,             'label' => '이벤트',  'icon' => '🎉', 'has_category_field' => true,  'category_model' => null],
        'clubs'      => ['model' => Club::class,              'label' => '동호회',  'icon' => '👥', 'has_category_field' => true,  'category_model' => null],
        'qa'         => ['model' => QaPost::class,            'label' => 'Q&A',    'icon' => '❓', 'has_category_field' => true,  'category_model' => QaCategory::class, 'category_field' => 'category_id'],
        'recipes'    => ['model' => RecipePost::class,        'label' => '레시피',  'icon' => '🍳', 'has_category_field' => true,  'category_model' => RecipeCategory::class, 'category_field' => 'category_id'],
        'news'       => ['model' => News::class,              'label' => '뉴스',    'icon' => '📰', 'has_category_field' => true,  'category_model' => NewsCategory::class, 'category_field' => 'category_id'],
        'community'  => ['model' => Post::class,              'label' => '커뮤니티', 'icon' => '💬', 'has_category_field' => true, 'category_model' => Board::class, 'category_field' => 'board_id'],
        'business'   => ['model' => Business::class,          'label' => '업소록',   'icon' => '🏪', 'has_category_field' => true,  'category_model' => null],
        'music'      => ['model' => MusicTrack::class,        'label' => '음악',     'icon' => '🎵', 'has_category_field' => true,  'category_model' => MusicCategory::class, 'category_field' => 'category_id'],
        'groupbuy'   => ['model' => GroupBuy::class,          'label' => '공동구매', 'icon' => '🛍', 'has_category_field' => true,  'category_model' => null],
        'shorts'     => ['model' => Short::class,             'label' => '숏츠',     'icon' => '🎬', 'has_category_field' => false, 'category_model' => null, 'category_field' => null],
    ];

    protected function config(string $slug): array
    {
        if (!isset($this->registry[$slug])) {
            abort(404, "Board '{$slug}' not found");
        }
        return $this->registry[$slug];
    }

    /**
     * 지원 게시판 전체 목록 (메타)
     */
    public function list()
    {
        $data = [];
        foreach ($this->registry as $slug => $cfg) {
            $model = $cfg['model'];
            $data[] = [
                'slug' => $slug,
                'label' => $cfg['label'],
                'icon' => $cfg['icon'],
                'total' => $model::count(),
                'today' => $model::whereDate('created_at', today())->count(),
            ];
        }
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * 게시판 요약 (통계 카드용)
     */
    public function overview(string $slug)
    {
        $cfg = $this->config($slug);
        $model = $cfg['model'];
        $fq = fn() => $model::query();

        $reportableClass = $cfg['model'];
        $pendingReports = Report::where('reportable_type', $reportableClass)->where('status', 'pending')->count();

        $activeBanners = BannerAd::where(function($q) use ($slug) {
            $q->where('page', $slug)->orWhereJsonContains('target_pages', $slug)->orWhere('page', 'all');
        })->where('status', 'active')->count();

        // 대분류(major_type) 통계 — 구인/구직, 렌트/매매 등
        $majorTypesBreakdown = null;
        if (!empty($cfg['major_types']) && !empty($cfg['major_type_field'])) {
            $field = $cfg['major_type_field'];
            $counts = $model::select($field)
                ->selectRaw('COUNT(*) as cnt')
                ->groupBy($field)->pluck('cnt', $field)->all();
            $majorTypesBreakdown = [];
            foreach ($cfg['major_types'] as $key => $label) {
                $majorTypesBreakdown[] = [
                    'key' => $key,
                    'label' => $label,
                    'count' => $counts[$key] ?? 0,
                ];
            }
        }

        return response()->json(['success' => true, 'data' => [
            'total' => $fq()->count(),
            'today' => $fq()->whereDate('created_at', today())->count(),
            'week' => $fq()->where('created_at', '>=', now()->subWeek())->count(),
            'pending_reports' => $pendingReports,
            'active_banners' => $activeBanners,
            'promoted' => $fq()->when(
                in_array('promotion_tier', (new $model)->getFillable()),
                fn($q) => $q->whereNotNull('promotion_tier')->where('promotion_expires_at', '>', now())
            )->count(),
            'major_types' => $cfg['major_types'] ?? null,
            'major_type_field' => $cfg['major_type_field'] ?? null,
            'major_types_breakdown' => $majorTypesBreakdown,
        ]]);
    }

    /**
     * 게시글 목록 (공통)
     */
    public function posts(string $slug, Request $request)
    {
        $cfg = $this->config($slug);
        $model = $cfg['model'];
        $modelInstance = new $model;
        $fields = $modelInstance->getFillable();
        $table = $modelInstance->getTable();

        $query = $model::query();
        if (in_array('user_id', $fields) && method_exists(new $model, 'user')) {
            $query->with('user:id,name,email,nickname');
        }

        if ($request->search) {
            $s = "%{$request->search}%";
            $query->where(function($q) use ($s, $model) {
                $fields = (new $model)->getFillable();
                if (in_array('title', $fields)) $q->orWhere('title', 'like', $s);
                if (in_array('name', $fields)) $q->orWhere('name', 'like', $s);
                if (in_array('content', $fields)) $q->orWhere('content', 'like', $s);
            });
        }
        if ($request->category) {
            $catField = $cfg['category_field'] ?? 'category';
            $query->where($catField, $request->category);
        }
        // 대분류(major_type) 필터 — 구인/구직, 렌트/매매
        if ($request->major_type && !empty($cfg['major_type_field'])) {
            $query->where($cfg['major_type_field'], $request->major_type);
        }

        // 정렬 (sort_by + sort_dir) — 화이트리스트로 SQL injection 방지
        $sortBy = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowed = array_unique(array_merge(
            ['id', 'created_at', 'updated_at', 'view_count', 'user_id', 'title', 'name'],
            $fields
        ));
        if (!in_array($sortBy, $allowed) || !\Schema::hasColumn($table, $sortBy)) {
            $sortBy = 'id';
        }
        $query->orderBy($sortBy, $sortDir);

        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    // ═════════════════════════════════════
    //   게시글 레벨 관리 액션
    // ═════════════════════════════════════

    /**
     * 게시글 상세 + 관리 액션용 메타 (이 모델에서 사용 가능한 필드 플래그)
     */
    public function postDetail(string $slug, $id)
    {
        $cfg = $this->config($slug);
        $model = $cfg['model'];
        $fields = (new $model)->getFillable();
        // user 관계는 user_id 있는 모델만 로드 (News 등은 없음)
        $query = $model::query();
        if (in_array('user_id', $fields) && method_exists(new $model, 'user')) {
            $query->with('user:id,name,email,nickname');
        }
        $item = $query->findOrFail($id);

        // 카테고리 이름도 함께 로드 (FK 기반)
        if ($cfg['category_model']) {
            $catField = $cfg['category_field'] ?? 'category';
            $catId = $item->$catField ?? null;
            if ($catId) {
                $catModel = $cfg['category_model'];
                $cat = $catModel::find($catId);
                $item->setAttribute('_category_name', $cat?->name);
            }
        }

        $actions = [
            'pin'            => in_array('is_pinned', $fields),
            'hide'           => in_array('is_hidden', $fields),
            'active'         => in_array('is_active', $fields),
            'resolved'       => in_array('is_resolved', $fields),
            'approved'       => in_array('is_approved', $fields),
            'verified'       => in_array('is_verified', $fields),
            'claimed'        => in_array('is_claimed', $fields),
            'lock_comments'  => in_array('is_locked', $fields),
            'promote'        => in_array('promotion_tier', $fields),
            'status'         => in_array('status', $fields),
            'category'       => in_array(($cfg['category_field'] ?? 'category'), $fields),
            'editable_fields'=> array_values(array_intersect($fields, ['title','name','content','description','price','category','city','state'])),
        ];

        // 댓글 + 답글 (polymorphic: commentable_type = model class, commentable_id = post id)
        $comments = Comment::with([
                'user:id,name,email,nickname',
                'replies.user:id,name,email,nickname',
            ])
            ->where('commentable_type', $model)
            ->where('commentable_id', $id)
            ->whereNull('parent_id')  // 최상위 댓글만 (답글은 replies 로 중첩)
            ->orderByDesc('created_at')
            ->get();

        // 게시글 관련 포인트 로그
        $pointLogs = PointLog::where('related_type', $model)
            ->where('related_id', $id)
            ->orderByDesc('created_at')->limit(20)->get();

        // 신고
        $reports = Report::with('reporter:id,name,email')
            ->where('reportable_type', $model)
            ->where('reportable_id', $id)
            ->orderByDesc('created_at')->get();

        return response()->json(['success' => true, 'data' => [
            'item' => $item,
            'actions' => $actions,
            'comments' => $comments,
            'point_logs' => $pointLogs,
            'reports' => $reports,
        ]]);
    }

    /**
     * 댓글 삭제/숨김
     */
    public function toggleComment(string $slug, $commentId, Request $request)
    {
        $this->config($slug);
        $comment = Comment::findOrFail($commentId);
        $field = $request->input('field', 'is_hidden');
        if ($field === 'is_hidden') {
            $comment->update(['is_hidden' => !$comment->is_hidden]);
            return response()->json(['success'=>true,'data'=>['is_hidden'=>$comment->is_hidden]]);
        }
        return response()->json(['success'=>false], 422);
    }

    public function deleteComment(string $slug, $commentId)
    {
        $this->config($slug);
        Comment::where('id', $commentId)->delete();
        Comment::where('parent_id', $commentId)->delete(); // 답글도 같이
        return response()->json(['success'=>true]);
    }

    /**
     * 불린 필드 토글 (is_pinned, is_hidden, is_active, is_locked 등)
     */
    public function toggleField(string $slug, $id, Request $request)
    {
        $cfg = $this->config($slug);
        $model = $cfg['model'];
        $field = $request->input('field');

        $allowed = ['is_pinned','is_hidden','is_active','is_resolved','is_approved','is_verified','is_claimed','is_locked','is_pinned'];
        if (!in_array($field, $allowed)) {
            return response()->json(['success'=>false,'message'=>'허용되지 않은 필드입니다'], 422);
        }
        if (!in_array($field, (new $model)->getFillable())) {
            return response()->json(['success'=>false,'message'=>"이 게시판에서는 {$field} 지원 안됨"], 422);
        }

        $item = $model::findOrFail($id);
        $item->update([$field => !$item->$field]);
        return response()->json(['success'=>true,'data'=>[$field => $item->$field]]);
    }

    /**
     * 인라인 편집 (title/content/category 등)
     */
    public function updatePost(string $slug, $id, Request $request)
    {
        $cfg = $this->config($slug);
        $model = $cfg['model'];
        $fields = (new $model)->getFillable();

        $editable = array_intersect($fields, [
            'title','name','content','description','price','category','category_id',
            'city','state','status','type','property_type','event_type',
            'salary_min','salary_max','start_date','end_date',
        ]);
        $data = $request->only($editable);

        $item = $model::findOrFail($id);
        $item->update($data);

        return response()->json(['success'=>true,'data'=>$item->fresh()]);
    }

    /**
     * 게시글 삭제 (공통 — 기존 per-resource endpoint 필요없이)
     */
    public function deletePost(string $slug, $id)
    {
        $cfg = $this->config($slug);
        $model = $cfg['model'];
        $model::findOrFail($id)->delete();
        return response()->json(['success'=>true]);
    }

    /**
     * 작성자 포인트 수동 조정 (게시글 관련)
     */
    public function adjustPoints(string $slug, $id, Request $request)
    {
        $cfg = $this->config($slug);
        $model = $cfg['model'];
        $item = $model::findOrFail($id);
        $user = \App\Models\User::find($item->user_id);
        if (!$user) return response()->json(['success'=>false,'message'=>'작성자 없음'], 404);

        $amount = (int)$request->input('amount', 0);
        $reason = $request->input('reason', '관리자 수동 조정');
        if ($amount === 0) return response()->json(['success'=>false,'message'=>'금액 입력 필요'], 422);

        $user->addPoints($amount, $reason, 'admin_adjust', [
            'type' => $model,
            'id' => $item->id,
        ]);

        return response()->json(['success'=>true,'message'=>"{$amount}P 조정됨",'new_balance'=>$user->fresh()->points]);
    }

    /**
     * 카테고리 변경 (문자열 또는 FK)
     */
    public function changeCategory(string $slug, $id, Request $request)
    {
        $cfg = $this->config($slug);
        $model = $cfg['model'];
        $catField = $cfg['category_field'] ?? 'category';

        $item = $model::findOrFail($id);
        $item->update([$catField => $request->input('category')]);
        return response()->json(['success'=>true,'data'=>$item->fresh()]);
    }

    // ═════════════════════════════════════
    //   카테고리 관리
    // ═════════════════════════════════════

    public function categories(string $slug, Request $request)
    {
        $cfg = $this->config($slug);
        $majorType = $request->query('major_type'); // 구인/구직, 렌트/매매 — scope 분리

        if ($cfg['category_model']) {
            // FK 테이블 기반 (qa, recipes, news) — sort_order 있으면 그 기준, 없으면 id
            $modelClass = $cfg['category_model'];
            $table = (new $modelClass)->getTable();
            $hasSort = Schema::hasColumn($table, 'sort_order');
            $query = $modelClass::query();
            $items = ($hasSort ? $query->orderBy('sort_order') : $query->orderBy('id'))->get();
        } else {
            // 문자열 필드 기반 — major_type 이 있으면 별도 키로 저장
            $key = $majorType
                ? "board.{$slug}.categories.{$majorType}"
                : "board.{$slug}.categories";
            $setting = DB::table('point_settings')->where('key', $key)->first();
            $items = $setting && $setting->value ? json_decode($setting->value, true) : [];

            // settings 비어있으면 실제 데이터에서 distinct 자동 수집 (카테고리 필드 있을 때만)
            $catField = $cfg['category_field'] ?? 'category';
            if (empty($items) && $catField && ($cfg['has_category_field'] ?? false)) {
                $model = $cfg['model'];
                $q = $model::query()
                    ->select($catField)
                    ->whereNotNull($catField)
                    ->where($catField, '!=', '');
                // major_type 스코프 제한
                if ($majorType && !empty($cfg['major_type_field'])) {
                    $q->where($cfg['major_type_field'], $majorType);
                }
                $distinct = $q->groupBy($catField)
                    ->selectRaw("COUNT(*) as cnt")
                    ->orderByDesc('cnt')
                    ->get();
                $items = $distinct->map(fn($r) => [
                    'name' => $r->$catField,
                    'slug' => $r->$catField,
                    'icon' => '🏷',
                    'is_active' => true,
                    'post_count' => $r->cnt,
                    'auto_detected' => true,
                ])->all();
            }
        }

        return response()->json([
            'success' => true,
            'data' => $items,
            'uses_table' => !!$cfg['category_model'],
            'major_type' => $majorType,
        ]);
    }

    public function saveCategories(string $slug, Request $request)
    {
        $cfg = $this->config($slug);
        $categories = $request->input('categories', []);
        $majorType = $request->input('major_type');

        if ($cfg['category_model']) {
            $modelClass = $cfg['category_model'];
            $table = (new $modelClass)->getTable();
            $hasAutoFetch = Schema::hasColumn($table, 'auto_fetch');
            $hasIsActive = Schema::hasColumn($table, 'is_active');

            $existingIds = collect($categories)->pluck('id')->filter()->all();
            // 삭제된 항목
            $modelClass::whereNotIn('id', $existingIds ?: [0])->delete();
            // upsert
            foreach ($categories as $idx => $cat) {
                $name = trim($cat['name'] ?? '');
                if ($name === '') continue;
                $slug = trim($cat['slug'] ?? '');
                if ($slug === '') {
                    // slug 비었으면 name 기반 자동 생성 (한글은 transliterate, 공백/특수문자 -)
                    $slug = \Str::slug($name);
                    if ($slug === '') $slug = 'cat-' . substr(md5($name), 0, 8);
                }
                // 다른 row 와 slug 충돌 시 번호 붙이기
                $base = $slug;
                $i = 2;
                while ($modelClass::where('slug', $slug)
                    ->when(!empty($cat['id']), fn($q) => $q->where('id', '!=', $cat['id']))
                    ->exists()) {
                    $slug = $base . '-' . $i++;
                }

                $data = [
                    'name' => $name,
                    'slug' => $slug,
                    'sort_order' => $idx,
                ];
                if ($hasAutoFetch && array_key_exists('auto_fetch', $cat)) {
                    $data['auto_fetch'] = (bool) $cat['auto_fetch'];
                }
                if ($hasIsActive && array_key_exists('is_active', $cat)) {
                    $data['is_active'] = (bool) $cat['is_active'];
                }
                if (!empty($cat['id'])) {
                    $modelClass::where('id', $cat['id'])->update($data);
                } else {
                    $modelClass::create($data);
                }
            }
            $items = $modelClass::orderBy('sort_order')->get();
        } else {
            $key = $majorType
                ? "board.{$slug}.categories.{$majorType}"
                : "board.{$slug}.categories";
            $clean = array_map(fn($c) => [
                'name' => $c['name'] ?? '',
                'slug' => $c['slug'] ?? null,
                'icon' => $c['icon'] ?? null,
                'is_active' => $c['is_active'] ?? true,
            ], $categories);
            DB::table('point_settings')->updateOrInsert(
                ['key' => $key],
                ['category' => 'board_meta', 'label' => "{$cfg['label']} 카테고리".($majorType ? " ({$majorType})" : ''), 'value' => json_encode($clean, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()]
            );
            $items = $clean;
        }

        return response()->json(['success' => true, 'data' => $items, 'message' => '저장되었습니다']);
    }

    // ═════════════════════════════════════
    //   게시판 설정 (board.{slug}.{key})
    // ═════════════════════════════════════

    public function settings(string $slug)
    {
        $cfg = $this->config($slug);
        $items = DB::table('point_settings')
            ->where('key', 'like', "board.{$slug}.%")
            ->where('key', 'not like', "board.{$slug}.categories")
            ->where('key', 'not like', "board.{$slug}.point_%")
            ->get()->keyBy('key');

        return response()->json(['success' => true, 'data' => $items, 'label' => $cfg['label']]);
    }

    public function saveSettings(string $slug, Request $request)
    {
        $this->config($slug);
        $settings = $request->input('settings', []);
        foreach ($settings as $key => $value) {
            if (!str_starts_with($key, "board.{$slug}.")) continue;
            DB::table('point_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'category' => 'board_config',
                    'label' => $key,
                    'value' => is_array($value) ? json_encode($value) : (string)$value,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
        return response()->json(['success' => true, 'message' => '설정이 저장되었습니다']);
    }

    // ═════════════════════════════════════
    //   포인트 규칙 (board.{slug}.point_*)
    // ═════════════════════════════════════

    public function points(string $slug)
    {
        $this->config($slug);
        $items = DB::table('point_settings')->where('key', 'like', "board.{$slug}.point_%")->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function savePoints(string $slug, Request $request)
    {
        $this->config($slug);
        $points = $request->input('points', []);
        foreach ($points as $key => $value) {
            if (!str_starts_with($key, "board.{$slug}.point_")) continue;
            DB::table('point_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'category' => 'board_points',
                    'label' => $key,
                    'value' => (string)$value,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
        return response()->json(['success' => true, 'message' => '포인트 규칙이 저장되었습니다']);
    }

    // ═════════════════════════════════════
    //   배너 (banner_ads.page = slug)
    // ═════════════════════════════════════

    public function banners(string $slug)
    {
        $this->config($slug);
        $items = BannerAd::with('user:id,name,email')
            ->where(function($q) use ($slug) {
                $q->where('page', $slug)->orWhereJsonContains('target_pages', $slug);
            })
            ->orderByDesc('created_at')
            ->get();

        $stats = [
            'total' => $items->count(),
            'active' => $items->where('status', 'active')->count(),
            'pending' => $items->where('status', 'pending')->count(),
            'total_revenue' => $items->where('status', 'active')->sum('total_cost'),
            'total_impressions' => $items->sum('impressions'),
            'total_clicks' => $items->sum('clicks'),
        ];

        return response()->json(['success' => true, 'data' => $items, 'stats' => $stats]);
    }

    // ═════════════════════════════════════
    //   신고/리포트 (해당 게시판만)
    // ═════════════════════════════════════

    public function reports(string $slug)
    {
        $cfg = $this->config($slug);
        $modelClass = $cfg['model'];
        $reports = Report::with('reporter:id,name,email,nickname')
            ->where('reportable_type', $modelClass)
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json(['success' => true, 'data' => $reports]);
    }

    // ═════════════════════════════════════
    //   전체 리포트 (Overview 확장판)
    // ═════════════════════════════════════

    public function fullReport()
    {
        $boards = [];
        foreach ($this->registry as $slug => $cfg) {
            $model = $cfg['model'];
            $boards[] = [
                'slug' => $slug,
                'label' => $cfg['label'],
                'icon' => $cfg['icon'],
                'total' => $model::count(),
                'today' => $model::whereDate('created_at', today())->count(),
                'week' => $model::where('created_at', '>=', now()->subWeek())->count(),
                'reports' => Report::where('reportable_type', $model)->where('status', 'pending')->count(),
            ];
        }

        // 결제/주문
        $paymentStats = [
            'total_revenue' => \App\Models\Payment::where('status', 'completed')->sum('amount'),
            'total_orders' => \App\Models\Payment::count(),
            'completed' => \App\Models\Payment::where('status', 'completed')->count(),
            'refunded' => \App\Models\Payment::where('status', 'refunded')->count(),
            'month_revenue' => \App\Models\Payment::where('status', 'completed')
                ->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
            'today_revenue' => \App\Models\Payment::where('status', 'completed')
                ->whereDate('created_at', today())->sum('amount'),
        ];

        // 포인트
        $pointStats = [
            'total_issued' => PointLog::where('amount', '>', 0)->sum('amount'),
            'total_spent' => abs(PointLog::where('amount', '<', 0)->sum('amount')),
            'today_issued' => PointLog::where('amount', '>', 0)->whereDate('created_at', today())->sum('amount'),
            'today_spent' => abs(PointLog::where('amount', '<', 0)->whereDate('created_at', today())->sum('amount')),
        ];

        // 광고
        $bannerStats = [
            'active' => BannerAd::where('status', 'active')->count(),
            'pending' => BannerAd::where('status', 'pending')->count(),
            'total_revenue' => BannerAd::where('status', 'active')->sum('total_cost'),
        ];

        // 회원
        $userStats = [
            'total' => \App\Models\User::count(),
            'new_today' => \App\Models\User::whereDate('created_at', today())->count(),
            'new_week' => \App\Models\User::where('created_at', '>=', now()->subWeek())->count(),
            'banned' => \App\Models\User::where('is_banned', true)->count(),
        ];

        return response()->json(['success' => true, 'data' => [
            'boards' => $boards,
            'payments' => $paymentStats,
            'points' => $pointStats,
            'banners' => $bannerStats,
            'users' => $userStats,
            'top_posters' => DB::table('users')
                ->join('posts', 'users.id', '=', 'posts.user_id')
                ->select('users.id', 'users.name', 'users.email', DB::raw('COUNT(posts.id) as post_count'))
                ->groupBy('users.id', 'users.name', 'users.email')
                ->orderByDesc('post_count')->limit(10)->get(),
            'recent_payments' => \App\Models\Payment::with('user:id,name,email')->latest()->limit(10)->get(),
            'recent_reports' => Report::with('reporter:id,name')->latest()->limit(10)->get(),
        ]]);
    }
}
