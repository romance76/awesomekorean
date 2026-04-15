<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessClaim;
use App\Models\BusinessMenu;
use App\Models\BusinessMenuOption;
use App\Models\BusinessReview;
use App\Support\ThumbHelper;
use App\Traits\AdminAuthorizes;
use App\Traits\CompressesUploads;
use App\Traits\HasPromotions;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    use AdminAuthorizes, CompressesUploads, HasPromotions;

    protected string $promoResource = 'business';
    protected string $promoModel = Business::class;
    protected string $promoCategoryColumn = 'category';

    public function promote(Request $request, $id)
    {
        $item = $this->findOwnedOrAdmin(Business::class, $id);
        return $this->handlePromote($item, $request);
    }

    public function promotionSlots(Request $request)
    {
        return $this->handlePromotionSlots($request);
    }

    public function index(Request $request)
    {
        $this->expireStalePromotions();

        $query = Business::query()
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->when($request->search, fn($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($request->state, fn($q, $v) => $q->where('state', $v))
            ->when($request->city, fn($q, $v) => $q->where('city', $v));

        $hasLocation = false;
        // 위치 필터 — bounding box 사전 필터 (인덱스 활용) + Haversine
        if ($request->lat && $request->lng) {
            $lat = (float) $request->lat;
            $lng = (float) $request->lng;
            $radius = (int) ($request->radius ?? 50);
            $latDelta = $radius / 69.0;
            $lngDelta = $radius / (69.0 * cos(deg2rad($lat)));
            $query->whereBetween('lat', [$lat - $latDelta, $lat + $latDelta])
                  ->whereBetween('lng', [$lng - $lngDelta, $lng + $lngDelta]);
            $query->nearby($lat, $lng, $radius);
            $hasLocation = true;
        }

        // 프로모션 티어 제외 + 우선 정렬 (사용자 정렬 옵션보다 먼저 적용)
        $this->excludeCrossTierPromotion($query, $hasLocation);
        $this->applyPromotionOrdering($query, $request->user_state, $hasLocation);

        $sort = $request->sort ?? 'popular';
        if ($sort === 'distance' && $request->lat) $query->orderBy('distance');
        elseif ($sort === 'popular') $query->orderByDesc('view_count');
        elseif ($sort === 'rating') $query->orderByDesc('rating');
        elseif ($sort === 'newest') $query->orderByDesc('created_at');
        elseif ($sort === 'reviews') $query->orderByDesc('review_count');
        elseif ($sort === 'random') $query->inRandomOrder();
        else $query->orderByDesc('view_count');

        $perPage = min((int) ($request->per_page ?? 20), 50);
        $paginated = $query->paginate($perPage);

        // 이미지 정리 — file_exists 없이 해시 경로만 계산
        $paginated->getCollection()->transform(function ($b) {
            $imgs = is_array($b->images) ? $b->images : (is_string($b->images) ? json_decode($b->images, true) : []);
            $valid = [];
            if (is_array($imgs)) {
                foreach ($imgs as $img) {
                    if (!is_string($img)) continue;
                    if (str_contains($img, 'maps.googleapis.com')) continue;
                    if (!str_starts_with($img, 'http') && !str_starts_with($img, '/')) {
                        $img = '/storage/' . $img;
                    }
                    $valid[] = $img;
                }
            }
            $first = !empty($valid) ? $valid[0] : null;
            // file_exists 생략 — 항상 정적 경로 반환, 없으면 프론트에서 /api/thumb fallback
            if ($first) {
                $hash = md5(html_entity_decode($first, ENT_QUOTES | ENT_HTML5));
                $b->thumbnail_url = '/storage/thumbs/' . substr($hash, 0, 2) . '/' . substr($hash, 2) . '_240.jpg';
            } else {
                $b->thumbnail_url = null;
            }
            $b->images = $valid;
            return $b;
        });
        return response()->json(['success' => true, 'data' => $paginated]);
    }

    public function show($id)
    {
        $biz = Business::with('reviews.user:id,name,nickname,avatar')->findOrFail($id);
        $biz->increment('view_count');
        return response()->json(['success' => true, 'data' => $biz]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|max:100', 'category' => 'required']);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $images[] = $this->storeCompressedImageRaw($img, 'businesses', 1400, 80);
            }
        }

        $logo = $request->hasFile('logo')
            ? $this->storeCompressedImageRaw($request->file('logo'), 'businesses/logos', 500, 82)
            : null;

        $biz = Business::create(array_merge(
            $request->only('name', 'description', 'category', 'subcategory', 'phone', 'email', 'website', 'address', 'city', 'state', 'zipcode', 'lat', 'lng', 'hours'),
            ['user_id' => auth()->id(), 'images' => $images ?: null, 'logo' => $logo]
        ));

        return response()->json(['success' => true, 'data' => $biz], 201);
    }

    public function reviews($id)
    {
        $reviews = BusinessReview::with('user:id,name,nickname,avatar')
            ->where('business_id', $id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $reviews]);
    }

    public function storeReview(Request $request, $id)
    {
        $request->validate(['rating' => 'required|integer|min:1|max:5', 'content' => 'nullable|max:1000']);

        $biz = Business::findOrFail($id);
        if ($biz->user_id === auth()->id()) {
            return response()->json(['success' => false, 'message' => '본인 업소에는 리뷰를 작성할 수 없습니다'], 403);
        }

        $review = BusinessReview::create([
            'business_id' => $id,
            'user_id' => auth()->id(),
            'rating' => $request->rating,
            'content' => $request->content,
        ]);

        // Recalculate rating
        $avg = BusinessReview::where('business_id', $id)->avg('rating');
        $count = BusinessReview::where('business_id', $id)->count();
        $biz->update(['rating' => round($avg, 2), 'review_count' => $count]);

        return response()->json(['success' => true, 'data' => $review], 201);
    }

    // ─── 소유권 신청 ───
    public function claim(Request $request, $id)
    {
        $request->validate(['phone' => 'required']);

        $biz = Business::findOrFail($id);

        if ($biz->is_claimed) {
            return response()->json(['success' => false, 'message' => '이미 인증된 업소입니다'], 400);
        }

        if (BusinessClaim::where('business_id', $id)->where('user_id', auth()->id())->where('status', 'pending')->exists()) {
            return response()->json(['success' => false, 'message' => '이미 신청 중입니다'], 400);
        }

        $documentUrl = null;
        if ($request->hasFile('document')) {
            $documentUrl = $this->storeDocument($request->file('document'), 'claims');
        }

        $claim = BusinessClaim::create([
            'business_id' => $id,
            'user_id' => auth()->id(),
            'document_url' => $documentUrl,
            'notes' => $request->phone,
            'status' => 'pending',
        ]);

        return response()->json(['success' => true, 'data' => $claim], 201);
    }

    // ─── 내 업소 목록 ───
    public function myBusinesses()
    {
        $businesses = Business::where('owner_id', auth()->id())
            ->with('menus.options')
            ->get();

        return response()->json(['success' => true, 'data' => $businesses]);
    }

    // ─── 내 업소 수정 ───
    public function updateMyBusiness(Request $request, $id)
    {
        $biz = Business::where('id', $id)->where('owner_id', auth()->id())->firstOrFail();
        $biz->update($request->only('name', 'description', 'phone', 'website', 'address', 'city', 'state', 'zipcode', 'hours'));

        return response()->json(['success' => true, 'data' => $biz]);
    }

    // ─── 내 업소 사진 업로드 ───
    public function uploadMyBusinessPhotos(Request $request, $id)
    {
        $biz = Business::where('id', $id)->where('owner_id', auth()->id())->firstOrFail();

        $images = $biz->images ?: [];
        foreach ($request->file('photos') as $photo) {
            $images[] = $this->storeCompressedImage($photo, 'businesses', 1400, 80);
        }
        $biz->update(['images' => $images]);

        return response()->json(['success' => true, 'data' => $biz]);
    }

    // ─── 메뉴 목록 (공개) ───
    public function menus($id)
    {
        $biz = Business::findOrFail($id);

        $menus = BusinessMenu::where('business_id', $id)
            ->with('options')
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['success' => true, 'data' => $menus]);
    }

    // ─── 메뉴 등록 ───
    public function storeMenu(Request $request, $id)
    {
        $biz = Business::where('id', $id)->where('owner_id', auth()->id())->firstOrFail();

        $request->validate([
            'name' => 'required',
            'price' => 'required|numeric',
        ]);

        $menu = BusinessMenu::create(array_merge(
            $request->only('name', 'description', 'price', 'category', 'sort_order', 'is_available'),
            ['business_id' => $id]
        ));

        if ($request->options) {
            foreach ($request->options as $opt) {
                BusinessMenuOption::create([
                    'business_menu_id' => $menu->id,
                    'name' => $opt['name'],
                    'price_add' => $opt['price_add'] ?? 0,
                ]);
            }
        }

        return response()->json(['success' => true, 'data' => $menu->load('options')], 201);
    }

    // ─── 메뉴 수정 ───
    public function updateMenu(Request $request, $bizId, $menuId)
    {
        Business::where('id', $bizId)->where('owner_id', auth()->id())->firstOrFail();

        $menu = BusinessMenu::where('id', $menuId)->where('business_id', $bizId)->firstOrFail();
        $menu->update($request->only('name', 'description', 'price', 'category', 'sort_order', 'is_available'));

        if ($request->has('options')) {
            $menu->options()->delete();
            foreach ($request->options as $opt) {
                BusinessMenuOption::create([
                    'business_menu_id' => $menu->id,
                    'name' => $opt['name'],
                    'price_add' => $opt['price_add'] ?? 0,
                ]);
            }
        }

        return response()->json(['success' => true, 'data' => $menu->load('options')]);
    }

    // ─── 메뉴 삭제 ───
    public function deleteMenu(Request $request, $bizId, $menuId)
    {
        Business::where('id', $bizId)->where('owner_id', auth()->id())->firstOrFail();
        BusinessMenu::where('id', $menuId)->where('business_id', $bizId)->firstOrFail()->delete();

        return response()->json(['success' => true]);
    }
}
