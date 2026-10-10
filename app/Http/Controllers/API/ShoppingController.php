<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AmazonProduct;
use App\Models\AmazonProductClick;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Report;
use App\Models\User;
use App\Services\BadWordFilter;
use App\Support\AmazonLink;
use App\Support\ShoppingReviews;
use App\Support\WritePoints;
use App\Traits\CompressesUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * 쇼핑 = "내돈내산 리뷰". 관리자가 올린 Amazon 제휴 상품(user_id = NULL)과
 * 회원이 자기 Associates 태그로 쓴 리뷰(user_id = 작성자)가 같은 테이블에 있다.
 */
class ShoppingController extends Controller
{
    use CompressesUploads;

    /** 목록/상세에 내려줄 공통 모양 (작성자·HOT 표시 포함) */
    private function present(AmazonProduct $p, array $hot, bool $detail = false): array
    {
        $a = $p->toArray();
        unset($a['admin_note'], $a['affiliate_tag']);   // 내부용
        $a['is_hot'] = in_array($p->id, $hot, true);
        $a['is_member_review'] = $p->user_id !== null;
        $a['author'] = $p->user_id && $p->relationLoaded('user') && $p->user
            ? ['id' => $p->user->id, 'name' => $p->user->nickname ?: $p->user->name, 'avatar' => $p->user->avatar] : null;
        return $a;
    }

    private function visible()
    {
        return AmazonProduct::where('is_active', true)->where('status', 'published');
    }

    // 공개: 목록 (카테고리/검색/종류 필터 + 정렬 + 페이지네이션)
    public function index(Request $request)
    {
        $query = $this->visible()->with('user:id,name,nickname,avatar,lifetime_points');

        if ($request->category) $query->where('category', $request->category);
        if ($request->search) $query->where('title', 'LIKE', '%' . $request->search . '%');
        if ($request->featured) $query->where('is_featured', true);
        if ($request->source === 'member') $query->whereNotNull('user_id');
        if ($request->source === 'official') $query->whereNull('user_id');
        if ($request->author) $query->where('user_id', (int) $request->author);

        $hot = ShoppingReviews::hotIds();
        if ($request->sort === 'hot') {
            $query->whereIn('id', $hot ?: [0]);
            $scores = ShoppingReviews::weeklyScores();
            $products = $query->get()->sortByDesc(fn($p) => $scores[$p->id] ?? 0)->values();
            $page = new \Illuminate\Pagination\LengthAwarePaginator($products->map(fn($p) => $this->present($p, $hot)), $products->count(), 20, 1);
            return response()->json(['success' => true, 'data' => $page, 'hot_ids' => $hot]);
        }

        if ($request->sort === 'popular') {
            $query->orderByDesc('view_count')->orderByDesc('id');
        } elseif ($request->sort === 'latest') {
            $query->orderByDesc('published_at')->orderByDesc('id');
        } else {
            $query->orderByDesc('is_featured')->orderBy('display_order')->orderByDesc('id');
        }

        $page = $query->paginate(20);
        $page->getCollection()->transform(fn($p) => $this->present($p, $hot));

        return response()->json(['success' => true, 'data' => $page, 'hot_ids' => $hot]);
    }

    // 공개: 상세. 비공개(승인 대기/내려감) 리뷰는 작성자·관리자만.
    public function show($id)
    {
        $product = AmazonProduct::with('user:id,name,nickname,avatar,lifetime_points')->find($id);
        $viewer = auth('api')->user();
        $isAdmin = $viewer && in_array($viewer->role, ['admin', 'super_admin', 'moderator'], true);
        $isOwner = $viewer && $product && $product->user_id && $product->user_id === $viewer->id;

        $public = $product && $product->is_active && $product->status === 'published';
        if (!$product || (!$public && !$isOwner && !$isAdmin)) {
            return response()->json(['success' => false, 'message' => '상품을 찾을 수 없습니다'], 404);
        }

        // 작성자 본인/관리자가 보는 건 조회수에 넣지 않는다
        if ($public && !$isOwner && !$isAdmin) {
            ShoppingReviews::recordView($product, $viewer?->id, request()->ip());
            $product->refresh()->load('user:id,name,nickname,avatar,lifetime_points');
        }

        $out = $this->present($product, ShoppingReviews::hotIds(), true);
        if ($isOwner || $isAdmin) $out['admin_note'] = $product->admin_note;
        $out['is_owner'] = (bool) $isOwner;
        return response()->json(['success' => true, 'data' => $out]);
    }

    // 공개: 클릭 기록 후 Amazon 제휴 링크로 리다이렉트 (routes/web.php에 등록 — SPA 캐치올보다 먼저)
    public function go($id)
    {
        $product = AmazonProduct::find($id);
        if (!$product || !$product->is_active || $product->status !== 'published') {
            abort(404);
        }

        $product->increment('clicks');

        AmazonProductClick::create([
            'amazon_product_id' => $product->id,
            'asin'              => $product->asin,
            'category'          => $product->category,
            'user_id'           => auth('api')->id(),
            'page'              => request()->header('referer'),
            'clicked_at'        => now(),
        ]);

        return redirect()->away($product->affiliate_url);
    }

    // ───────────────────────── 회원 리뷰 ─────────────────────────

    /** PUT /shopping/my-tag — 내 Amazon Associates 태그 등록/수정/삭제. 바꾸면 내 리뷰 링크도 새 태그로 갱신 */
    public function saveTag(Request $request)
    {
        $tag = trim((string) $request->input('amazon_tag'));
        $user = $request->user();
        if ($tag !== '' && !AmazonLink::isValidTag($tag)) {
            return response()->json(['success' => false, 'message' => '태그 형식이 올바르지 않아요. Amazon Associates 스토어 ID(예: myshop-20)를 입력해주세요.'], 422);
        }
        $tag = strtolower($tag);
        $user->forceFill(['amazon_tag' => $tag ?: null])->save();

        // 이미 쓴 리뷰의 링크도 새 태그로 (태그를 지우면 리뷰는 내려가고 작성자가 다시 태그를 넣을 때까지 링크 없음)
        AmazonProduct::where('user_id', $user->id)->get()->each(function (AmazonProduct $p) use ($tag) {
            if ($tag) $p->update(['affiliate_tag' => $tag, 'affiliate_url' => AmazonLink::affiliateUrl($p->asin, $tag)]);
            elseif ($p->status === 'published') $p->update(['status' => 'hidden', 'admin_note' => '작성자가 Amazon 태그를 삭제해 숨김']);
        });

        return response()->json(['success' => true, 'message' => $tag ? '태그를 저장했어요. 내 리뷰 링크가 새 태그로 바뀌었어요.' : '태그를 삭제했어요.', 'data' => ['amazon_tag' => $tag ?: null]]);
    }

    /** Amazon 이미지 주소만 허용 (Amazon 이 호스팅하는 걸 링크만 함 — 서버에 복사하지 않음) */
    private const AMAZON_IMG = '#^https://[a-z0-9-]+\.(media-amazon\.com|ssl-images-amazon\.com)/[A-Za-z0-9._%,+\-/~@=]+$#i';
    private const OWN_IMG = '#^/storage/shopping/[A-Za-z0-9._\-]+$#';

    /**
     * 리뷰 입력 검증 + 정리 (작성/수정 공통).
     * 본문은 blocks = [{type:text,text}, {type:image,url}] 로 받고, 글은 태그를 제거한 글자만 저장한다.
     * 직접 찍은 사진(image 블록)은 최소 2장, Amazon 기본 사진은 1~3장.
     */
    private function validateReview(Request $request, bool $creating): array
    {
        $min = max(10, \App\Support\PointRules::get('shopping_review_min_chars', 60));
        $minPhotos = max(1, \App\Support\PointRules::get('shopping_review_min_photos', 2));
        $rules = [
            'title'    => 'required|string|max:200',
            'category' => 'nullable|string|max:50',
            'rating'   => 'required|integer|min:1|max:5',
            'blocks'   => 'required|array|min:1|max:60',
            'blocks.*.type' => 'required|in:text,image',
            'blocks.*.text' => 'nullable|string|max:5000',
            'blocks.*.url'  => 'nullable|string|max:300',
            'amazon_images' => 'required|array|min:1|max:3',
            'amazon_images.*' => ['required', 'string', 'max:500', 'regex:' . self::AMAZON_IMG],
            'purchased' => 'accepted',
            'agree_rules' => 'accepted',
        ];
        if ($creating) $rules['input'] = 'required|string';
        $data = $request->validate($rules, [
            'amazon_images.required' => 'Amazon 상품 사진 주소를 한 개 이상 넣어주세요.',
            'amazon_images.min' => 'Amazon 상품 사진 주소를 한 개 이상 넣어주세요.',
            'amazon_images.*.regex' => 'Amazon 사진 주소가 아니에요. Amazon 상품 페이지에서 사진을 우클릭 → "이미지 주소 복사"한 주소(…media-amazon.com/…)를 붙여넣어주세요.',
            'purchased.accepted' => '"직접 구매해서 사용해 봤어요"에 체크해주세요.',
            'agree_rules.accepted' => '리뷰 작성 규칙에 동의해주세요.',
        ]);

        // 블록 정리: 글은 태그 제거, 사진은 우리 서버에 올린 것만, 빈 글 블록 제거
        $blocks = []; $texts = []; $photos = [];
        foreach ($data['blocks'] as $b) {
            if ($b['type'] === 'text') {
                $t = trim(html_entity_decode(strip_tags((string) ($b['text'] ?? '')), ENT_QUOTES | ENT_HTML5));
                if ($t === '') continue;
                $blocks[] = ['type' => 'text', 'text' => $t]; $texts[] = $t;
            } else {
                $u = (string) ($b['url'] ?? '');
                if (!preg_match(self::OWN_IMG, $u)) {
                    abort(response()->json(['success' => false, 'message' => '올린 사진을 확인하지 못했어요. 사진을 다시 올려주세요.'], 422));
                }
                $blocks[] = ['type' => 'image', 'url' => $u]; $photos[] = $u;
            }
        }
        $body = implode("\n\n", $texts);
        if (mb_strlen($body) < $min) {
            throw \Illuminate\Validation\ValidationException::withMessages(['blocks' => "리뷰는 최소 {$min}자 이상 써주세요."]);
        }
        if (mb_strlen($body) > 8000) {
            throw \Illuminate\Validation\ValidationException::withMessages(['blocks' => '리뷰가 너무 길어요. (최대 8000자)']);
        }
        if (count(array_unique($photos)) < $minPhotos) {
            throw \Illuminate\Validation\ValidationException::withMessages(['blocks' => "직접 찍은 사진을 글 사이에 {$minPhotos}장 이상 넣어주세요."]);
        }

        $data['title'] = trim(strip_tags($data['title']));
        foreach ([$data['title'], $body] as $text) {
            if ($why = ShoppingReviews::violation($text)) {
                abort(response()->json(['success' => false, 'message' => $why], 422));
            }
            if (BadWordFilter::contains($text)) {
                abort(response()->json(['success' => false, 'message' => '부적절한 표현이 포함되어 있어요.'], 422));
            }
        }
        $data['blocks'] = $blocks; $data['body'] = $body; $data['photos'] = array_values(array_unique($photos));
        $data['amazon_images'] = array_values(array_unique($data['amazon_images']));
        return $data;
    }

    /** POST /shopping/review-image — 리뷰 글 사이에 넣을 사진 한 장 올리기 */
    public function uploadReviewImage(Request $request)
    {
        $request->validate(['image' => 'required|image|max:10240']);
        $url = $this->storeCompressedImage($request->file('image'), 'shopping', 1200, 82);
        return response()->json(['success' => true, 'data' => ['url' => $url]]);
    }

    /** POST /shopping/reviews — 내돈내산 리뷰 작성 (내 태그가 있어야 함) */
    public function storeReview(Request $request)
    {
        $user = $request->user();
        if (!AmazonLink::isValidTag($user->amazon_tag)) {
            return response()->json(['success' => false, 'message' => '먼저 마이페이지에서 내 Amazon Associates 태그를 등록해주세요.', 'needs_tag' => true], 422);
        }
        $data = $this->validateReview($request, true);

        $asin = AmazonLink::extractAsin($data['input']);
        if (!$asin) {
            return response()->json(['success' => false, 'message' => '상품을 인식하지 못했어요. amazon.com 상품 주소(…/dp/ASIN) 또는 ASIN 10자리를 입력해주세요. (amzn.to 같은 단축 주소는 쓸 수 없어요)'], 422);
        }
        if (AmazonProduct::where('asin', $asin)->where('user_id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => '이미 내가 리뷰를 쓴 상품이에요. 기존 리뷰를 수정해주세요.'], 422);
        }

        $needsApproval = ShoppingReviews::needsApproval($user);

        $p = AmazonProduct::create([
            'user_id' => $user->id,
            'asin' => $asin,
            'amazon_url' => AmazonLink::amazonUrl($asin),
            'affiliate_url' => AmazonLink::affiliateUrl($asin, $user->amazon_tag),
            'affiliate_tag' => strtolower($user->amazon_tag),
            'title' => $data['title'],
            'category' => $data['category'] ?? null,
            'rating' => $data['rating'],
            'image_url' => $data['amazon_images'][0],
            'amazon_image_urls' => $data['amazon_images'],
            'own_image_urls' => $data['photos'],
            'our_description' => $data['body'],
            'review_blocks' => $data['blocks'],
            'is_active' => true,
            'status' => $needsApproval ? 'pending' : 'published',
            'published_at' => $needsApproval ? null : now(),
        ]);

        if (!$needsApproval) {
            try { WritePoints::award($user, AmazonProduct::class, $p->id, '내돈내산 리뷰 작성'); } catch (\Throwable $e) {}
        } else {
            $this->notifyAdmins('새 내돈내산 리뷰 승인 요청', "'{$p->title}' — 처음 리뷰를 쓴 회원의 글이에요. 확인 후 승인해주세요.", $p->id);
        }

        ShoppingReviews::forget();
        return response()->json([
            'success' => true,
            'message' => $needsApproval ? '리뷰가 접수됐어요. 첫 리뷰는 관리자 확인 후 공개돼요.' : '리뷰가 등록됐어요!',
            'data' => $p,
        ], 201);
    }

    /** PUT/POST /shopping/reviews/{id} — 내 리뷰 수정 (상품/태그는 못 바꿈) */
    public function updateReview(Request $request, $id)
    {
        $p = AmazonProduct::where('user_id', $request->user()->id)->findOrFail($id);
        $data = $this->validateReview($request, false);

        $update = [
            'title' => $data['title'], 'category' => $data['category'] ?? $p->category, 'rating' => $data['rating'],
            'our_description' => $data['body'], 'review_blocks' => $data['blocks'],
            'amazon_image_urls' => $data['amazon_images'], 'image_url' => $data['amazon_images'][0],
            'own_image_urls' => $data['photos'],
        ];
        // 내려간 리뷰(hidden/rejected)를 고치면 다시 승인 대기로
        if (in_array($p->status, ['hidden', 'rejected'], true)) { $update['status'] = 'pending'; $update['admin_note'] = null; }
        $p->update($update);

        ShoppingReviews::forget();
        return response()->json(['success' => true, 'message' => $p->status === 'pending' ? '수정했어요. 관리자 확인 후 다시 공개돼요.' : '수정했어요.', 'data' => $p->fresh()]);
    }

    /** DELETE /shopping/reviews/{id} */
    public function destroyReview(Request $request, $id)
    {
        $p = AmazonProduct::where('user_id', $request->user()->id)->findOrFail($id);
        $p->delete();
        ShoppingReviews::forget();
        return response()->json(['success' => true, 'message' => '삭제했어요.']);
    }

    /** GET /shopping/my — 내 리뷰 + 조회/클릭/댓글/신고 통계 */
    public function my(Request $request)
    {
        $user = $request->user();
        $since = now()->subDays(7);
        $hot = ShoppingReviews::hotIds();
        $items = AmazonProduct::where('user_id', $user->id)->orderByDesc('id')->get();
        $ids = $items->pluck('id');

        $weekViews = \App\Models\AmazonProductView::whereIn('amazon_product_id', $ids)->where('viewed_at', '>=', $since)->selectRaw('amazon_product_id, COUNT(*) c')->groupBy('amazon_product_id')->pluck('c', 'amazon_product_id');
        $weekClicks = AmazonProductClick::whereIn('amazon_product_id', $ids)->where('clicked_at', '>=', $since)->selectRaw('amazon_product_id, COUNT(*) c')->groupBy('amazon_product_id')->pluck('c', 'amazon_product_id');
        $comments = Comment::where('commentable_type', AmazonProduct::class)->whereIn('commentable_id', $ids)->where('is_hidden', false)->selectRaw('commentable_id, COUNT(*) c')->groupBy('commentable_id')->pluck('c', 'commentable_id');
        $reports = Report::where('reportable_type', AmazonProduct::class)->whereIn('reportable_id', $ids)->selectRaw('reportable_id, COUNT(DISTINCT reporter_id) c')->groupBy('reportable_id')->pluck('c', 'reportable_id');

        $rows = $items->map(function (AmazonProduct $p) use ($weekViews, $weekClicks, $comments, $reports, $hot) {
            $a = $p->toArray();
            $a['week_views'] = (int) ($weekViews[$p->id] ?? 0);
            $a['week_clicks'] = (int) ($weekClicks[$p->id] ?? 0);
            $a['comments'] = (int) ($comments[$p->id] ?? 0);
            $a['reports'] = (int) ($reports[$p->id] ?? 0);
            $a['is_hot'] = in_array($p->id, $hot, true);
            return $a;
        });

        return response()->json(['success' => true, 'data' => [
            'amazon_tag' => $user->amazon_tag,
            'items' => $rows,
            'totals' => [
                'reviews' => $items->count(),
                'views' => (int) $items->sum('view_count'),
                'clicks' => (int) $items->sum('clicks'),
                'comments' => (int) $rows->sum('comments'),
                'week_views' => (int) $rows->sum('week_views'),
                'week_clicks' => (int) $rows->sum('week_clicks'),
            ],
            'rules' => ['min_chars' => max(10, \App\Support\PointRules::get('shopping_review_min_chars', 60)), 'min_photos' => max(1, \App\Support\PointRules::get('shopping_review_min_photos', 2)), 'first_approval' => ShoppingReviews::needsApproval($user)],
        ]]);
    }

    private function notifyAdmins(string $title, string $content, int $id): void
    {
        try {
            foreach (User::whereIn('role', ['admin', 'super_admin', 'moderator'])->pluck('id') as $adminId) {
                Notification::create(['user_id' => $adminId, 'type' => 'shopping_review', 'title' => $title, 'content' => $content, 'data' => ['product_id' => $id]]);
            }
        } catch (\Throwable $e) {}
    }
}
