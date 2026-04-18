<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{
    MarketItem, JobPost, RealEstateListing, Event, Club, QaPost, RecipePost,
    Post, News, Business, Comment, Report, BannerAd, PointLog,
    QaCategory, MusicCategory, NewsCategory, RecipeCategory, Board
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBoardController extends Controller
{
    /**
     * 게시판별 통합 관리 API
     * slug 는 URL 경로 (market, qa, jobs, realestate, events, clubs, recipes, community, news)
     */
    protected array $registry = [
        'market'     => ['model' => MarketItem::class,        'label' => '장터',    'icon' => '🛒', 'has_category_field' => true,  'category_model' => null],
        'jobs'       => ['model' => JobPost::class,           'label' => '구인구직', 'icon' => '💼', 'has_category_field' => true,  'category_model' => null],
        'realestate' => ['model' => RealEstateListing::class, 'label' => '부동산',  'icon' => '🏠', 'has_category_field' => false, 'category_model' => null, 'category_field' => 'property_type'],
        'events'     => ['model' => Event::class,             'label' => '이벤트',  'icon' => '🎉', 'has_category_field' => true,  'category_model' => null],
        'clubs'      => ['model' => Club::class,              'label' => '동호회',  'icon' => '👥', 'has_category_field' => true,  'category_model' => null],
        'qa'         => ['model' => QaPost::class,            'label' => 'Q&A',    'icon' => '❓', 'has_category_field' => true,  'category_model' => QaCategory::class, 'category_field' => 'category_id'],
        'recipes'    => ['model' => RecipePost::class,        'label' => '레시피',  'icon' => '🍳', 'has_category_field' => true,  'category_model' => RecipeCategory::class, 'category_field' => 'category_id'],
        'news'       => ['model' => News::class,              'label' => '뉴스',    'icon' => '📰', 'has_category_field' => true,  'category_model' => NewsCategory::class, 'category_field' => 'category_id'],
        'community'  => ['model' => Post::class,              'label' => '커뮤니티', 'icon' => '💬', 'has_category_field' => false, 'category_model' => null, 'category_field' => 'board_id'],
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
        ]]);
    }

    /**
     * 게시글 목록 (공통)
     */
    public function posts(string $slug, Request $request)
    {
        $cfg = $this->config($slug);
        $model = $cfg['model'];
        $query = $model::query()->with('user:id,name,email,nickname')->latest();

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

        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    // ═════════════════════════════════════
    //   카테고리 관리
    // ═════════════════════════════════════

    public function categories(string $slug)
    {
        $cfg = $this->config($slug);

        if ($cfg['category_model']) {
            // FK 테이블 기반 (qa, recipes, news)
            $items = $cfg['category_model']::orderBy('sort_order')->get();
        } else {
            // 문자열 필드 기반 (market, jobs 등) — settings 에서 JSON 리스트로 관리
            $key = "board.{$slug}.categories";
            $setting = DB::table('point_settings')->where('key', $key)->first();
            $items = $setting && $setting->value ? json_decode($setting->value, true) : [];
        }

        return response()->json(['success' => true, 'data' => $items, 'uses_table' => !!$cfg['category_model']]);
    }

    public function saveCategories(string $slug, Request $request)
    {
        $cfg = $this->config($slug);
        $categories = $request->input('categories', []);

        if ($cfg['category_model']) {
            $modelClass = $cfg['category_model'];
            $existingIds = collect($categories)->pluck('id')->filter()->all();
            // 삭제된 항목
            $modelClass::whereNotIn('id', $existingIds ?: [0])->delete();
            // upsert
            foreach ($categories as $idx => $cat) {
                $data = [
                    'name' => $cat['name'] ?? '',
                    'slug' => $cat['slug'] ?? null,
                    'sort_order' => $idx,
                ];
                if (!empty($cat['id'])) {
                    $modelClass::where('id', $cat['id'])->update($data);
                } else {
                    $modelClass::create($data);
                }
            }
            $items = $modelClass::orderBy('sort_order')->get();
        } else {
            $key = "board.{$slug}.categories";
            $clean = array_map(fn($c) => [
                'name' => $c['name'] ?? '',
                'slug' => $c['slug'] ?? null,
                'icon' => $c['icon'] ?? null,
                'is_active' => $c['is_active'] ?? true,
            ], $categories);
            DB::table('point_settings')->updateOrInsert(
                ['key' => $key],
                ['category' => 'board_meta', 'label' => "{$cfg['label']} 카테고리", 'value' => json_encode($clean, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()]
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
