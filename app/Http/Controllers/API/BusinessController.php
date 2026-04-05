<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessReview;
use App\Models\Bookmark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BusinessController extends Controller
{
    /**
     * GET /api/businesses
     * List businesses with category, subcategory, search, distance, sort, pagination
     */
    public function index(Request $request)
    {
        $query = Business::query();

        // Category filter
        if ($request->filled('category')) {
            $query->where(function ($q) use ($request) {
                $q->where('category', $request->category)
                  ->orWhere('category', 'like', '%' . $request->category . '%');
            });
        }

        // Subcategory filter
        if ($request->filled('subcategory')) {
            $query->where('subcategory', $request->subcategory);
        }

        // City filter
        if ($request->filled('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        // State filter
        if ($request->filled('state') && !$request->filled('city')) {
            $stateCities = [
                'CA' => ['Los Angeles', 'LA', 'San Francisco', 'San Diego'],
                'NY' => ['New York', 'NY', 'Flushing'],
                'TX' => ['Houston', 'Dallas'],
                'WA' => ['Seattle'],
                'IL' => ['Chicago'],
                'GA' => ['Atlanta'],
                'DC' => ['Washington'],
                'NV' => ['Las Vegas'],
                'FL' => ['Miami'],
                'MA' => ['Boston'],
                'HI' => ['Honolulu'],
                'CO' => ['Denver'],
                'NJ' => ['Fort Lee'],
                'VA' => ['Annandale'],
                'OR' => ['Portland'],
                'MN' => ['Minneapolis'],
                'MI' => ['Detroit'],
                'AZ' => ['Phoenix'],
                'MD' => ['Baltimore'],
                'PA' => ['Philadelphia'],
            ];
            $s = strtoupper($request->state);
            if (isset($stateCities[$s])) {
                $query->where(function ($q) use ($stateCities, $s) {
                    foreach ($stateCities[$s] as $city) {
                        $q->orWhere('city', 'like', '%' . $city . '%');
                    }
                });
            }
        }

        // Search by name / description
        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('name_ko', 'like', "%{$keyword}%")
                  ->orWhere('name_en', 'like', "%{$keyword}%")
                  ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        // Distance filter (Haversine)
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $radius = $request->input('radius');

        if ($lat && $lng && $radius && (float) $radius > 0) {
            $lat = (float) $lat;
            $lng = (float) $lng;
            $r = (float) $radius;

            $query->selectRaw(
                "*, (3959 * acos(cos(radians(?)) * cos(radians(COALESCE(lat, 0))) * cos(radians(COALESCE(lng, 0)) - radians(?)) + sin(radians(?)) * sin(radians(COALESCE(lat, 0))))) AS distance",
                [$lat, $lng, $lat]
            )->having('distance', '<', $r);
        }

        // Sorting
        $sort = $request->input('sort', 'default');
        switch ($sort) {
            case 'rating':
                $query->orderByDesc('rating');
                break;
            case 'distance':
                if ($lat && $lng) {
                    $query->orderBy('distance');
                }
                break;
            case 'newest':
                $query->orderByDesc('created_at');
                break;
            default:
                $query->orderByDesc('rating');
                break;
        }

        $perPage = min((int) ($request->per_page ?? 20), 50);

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($perPage),
        ]);
    }

    /**
     * GET /api/businesses/{id}
     * Show single business with reviews, increment view_count
     */
    public function show($id)
    {
        $business = Business::with([
            'owner:id,name,nickname,avatar',
            'reviews.user:id,name,nickname,avatar',
        ])->findOrFail($id);

        $business->increment('view_count');

        $data = $business->toArray();

        // Bookmark status
        $data['is_bookmarked'] = false;
        if (Auth::check()) {
            $data['is_bookmarked'] = Bookmark::where('user_id', Auth::id())
                ->where('bookmarkable_type', Business::class)
                ->where('bookmarkable_id', $id)
                ->exists();
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * POST /api/businesses
     * Create business (auth required), handle logo + images upload
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'category'    => 'required|string',
            'address'     => 'required|string',
            'description' => 'nullable|string|max:3000',
            'phone'       => 'nullable|string|max:20',
            'website'     => 'nullable|string|max:255',
            'hours'       => 'nullable|string|max:500',
            'city'        => 'nullable|string|max:100',
            'state'       => 'nullable|string|max:50',
            'zipcode'     => 'nullable|string|max:20',
            'lat'         => 'nullable|numeric',
            'lng'         => 'nullable|numeric',
            'logo'        => 'nullable|image|max:3072',
            'images'      => 'nullable|array|max:10',
            'images.*'    => 'image|max:5120',
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('businesses/logos', 'public');
        }

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('businesses/images', 'public');
                $images[] = Storage::url($path);
            }
        }

        $business = Business::create(array_merge(
            $request->only([
                'name', 'name_ko', 'name_en', 'category', 'subcategory',
                'description', 'address', 'city', 'state', 'zipcode',
                'lat', 'lng', 'phone', 'website', 'hours',
            ]),
            [
                'owner_id' => Auth::id(),
                'logo'     => $logoPath,
                'images'   => !empty($images) ? $images : null,
            ]
        ));

        return response()->json([
            'success' => true,
            'message' => '업소가 등록되었습니다.',
            'data'    => $business,
        ], 201);
    }

    /**
     * PUT /api/businesses/{id}
     * Update own business or claimed business
     */
    public function update(Request $request, $id)
    {
        $business = Business::findOrFail($id);

        if ($business->owner_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json([
                'success' => false,
                'message' => '수정 권한이 없습니다.',
            ], 403);
        }

        $request->validate([
            'name'        => 'sometimes|string|max:100',
            'category'    => 'sometimes|string',
            'address'     => 'sometimes|string',
            'description' => 'nullable|string|max:3000',
            'logo'        => 'nullable|image|max:3072',
            'images'      => 'nullable|array|max:10',
            'images.*'    => 'image|max:5120',
        ]);

        if ($request->hasFile('logo')) {
            if ($business->logo) {
                Storage::disk('public')->delete($business->logo);
            }
            $business->logo = $request->file('logo')->store('businesses/logos', 'public');
        }

        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $file) {
                $path = $file->store('businesses/images', 'public');
                $images[] = Storage::url($path);
            }
            $business->images = $images;
        }

        $business->fill($request->only([
            'name', 'name_ko', 'name_en', 'category', 'subcategory',
            'description', 'address', 'lat', 'lng', 'phone', 'website',
            'hours', 'city', 'state', 'zipcode',
        ]));

        $business->save();

        return response()->json([
            'success' => true,
            'message' => '수정되었습니다.',
            'data'    => $business,
        ]);
    }

    /**
     * GET /api/businesses/{id}/reviews
     * List reviews for a business
     */
    public function reviews($id)
    {
        Business::findOrFail($id);

        $reviews = BusinessReview::where('business_id', $id)
            ->with('user:id,name,nickname,avatar')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $reviews,
        ]);
    }

    /**
     * POST /api/businesses/{id}/reviews
     * Create review with rating 1-5 (auth, can't review own business)
     */
    public function storeReview(Request $request, $id)
    {
        $business = Business::findOrFail($id);

        if ($business->owner_id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => '자신의 업소에는 리뷰를 작성할 수 없습니다.',
            ], 422);
        }

        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'content' => 'nullable|string|max:1000',
        ]);

        $review = BusinessReview::updateOrCreate(
            ['business_id' => $id, 'user_id' => Auth::id()],
            ['rating' => $request->rating, 'content' => $request->content]
        );

        // Recalculate averages
        $avg = $business->reviews()->avg('rating');
        $business->update([
            'rating'       => round($avg, 1),
            'review_count' => $business->reviews()->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '리뷰가 등록되었습니다.',
            'data'    => $review->load('user:id,name,nickname,avatar'),
        ], 201);
    }

    /**
     * POST /api/businesses/{id}/bookmark
     * Toggle bookmark
     */
    public function toggleBookmark($id)
    {
        Business::findOrFail($id);
        $userId = Auth::id();

        $existing = Bookmark::where('user_id', $userId)
            ->where('bookmarkable_type', Business::class)
            ->where('bookmarkable_id', $id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['success' => true, 'data' => ['bookmarked' => false]]);
        }

        Bookmark::create([
            'user_id'           => $userId,
            'bookmarkable_type' => Business::class,
            'bookmarkable_id'   => $id,
        ]);

        return response()->json(['success' => true, 'data' => ['bookmarked' => true]]);
    }

    /**
     * POST /api/businesses/{id}/track/{type}
     * Track stats: phone, direction, website, view
     */
    public function trackStat(Request $request, $id)
    {
        $type = $request->route('type');
        $col = match ($type) {
            'phone'     => 'phone_clicks',
            'direction' => 'direction_clicks',
            'website'   => 'website_clicks',
            default     => 'views',
        };

        DB::table('business_stats')->updateOrInsert(
            ['business_id' => $id, 'stat_date' => now()->toDateString()],
            [$col => DB::raw($col . ' + 1'), 'updated_at' => now()]
        );

        return response()->json(['success' => true]);
    }
}
