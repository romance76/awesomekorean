<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessClaim;
use App\Models\BusinessMenu;
use App\Models\BusinessMenuOption;
use App\Models\BusinessReview;
use App\Support\ThumbHelper;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function index(Request $request)
    {
        $query = Business::query()
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->when($request->search, fn($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($request->state, fn($q, $v) => $q->where('state', $v))
            ->when($request->city, fn($q, $v) => $q->where('city', $v));

        if ($request->lat && $request->lng) {
            $query->nearby($request->lat, $request->lng, $request->radius ?? 50);
        }

        $sort = $request->sort ?? 'random';
        if ($sort === 'random') $query->inRandomOrder();
        elseif ($sort === 'distance' && $request->lat) $query->orderBy('distance');
        elseif ($sort === 'popular') $query->orderByDesc('view_count');
        elseif ($sort === 'rating') $query->orderByDesc('rating');
        elseif ($sort === 'newest') $query->orderByDesc('created_at');
        elseif ($sort === 'reviews') $query->orderByDesc('review_count');
        elseif ($sort === 'views') $query->orderByDesc('view_count');

        $paginated = $query->paginate(10);
        $paginated->getCollection()->transform(function ($b) {
            $imgs = is_array($b->images) ? $b->images : (is_string($b->images) ? json_decode($b->images, true) : []);
            // Google Places photoreference 는 만료되어 403 → 로컬 이미지만 사용
            $valid = [];
            if (is_array($imgs)) {
                foreach ($imgs as $img) {
                    if (!is_string($img)) continue;
                    if (str_contains($img, 'maps.googleapis.com')) continue;
                    // businesses/xxx.jpg 처럼 상대 경로로 저장된 경우 /storage/ 접두사
                    if (!str_starts_with($img, 'http') && !str_starts_with($img, '/')) {
                        $img = '/storage/' . $img;
                    }
                    $valid[] = $img;
                }
            }
            $first = !empty($valid) ? $valid[0] : null;
            $b->thumbnail_url = $first ? ThumbHelper::url($first, 240) : null;
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
            foreach ($request->file('images') as $img) $images[] = $img->store('businesses', 'public');
        }

        $logo = $request->hasFile('logo') ? $request->file('logo')->store('businesses/logos', 'public') : null;

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
            $documentUrl = '/storage/' . $request->file('document')->store('claims', 'public');
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
            $path = $photo->store('businesses', 'public');
            $images[] = '/storage/' . $path;
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
