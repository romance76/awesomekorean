<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessReview;
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

        $sort = $request->sort ?? 'rating';
        if ($sort === 'rating') $query->orderByDesc('rating');
        elseif ($sort === 'newest') $query->orderByDesc('created_at');
        elseif ($sort === 'reviews') $query->orderByDesc('review_count');

        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
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
}
