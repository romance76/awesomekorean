<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RealEstateListing;
use App\Models\Bookmark;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class RealEstateController extends Controller
{
    /**
     * GET /api/realestate
     * List with type, property_type, price range, bedrooms, distance, pagination
     */
    public function index(Request $request)
    {
        $query = RealEstateListing::with('user:id,name,nickname,avatar')
            ->where('is_active', true);

        // Type filter (rent/sale/roommate)
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Property type filter
        if ($request->filled('property_type')) {
            $query->where('property_type', $request->property_type);
        }

        // Price range
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        // Bedrooms
        if ($request->filled('bedrooms')) {
            $query->where('bedrooms', '>=', (int) $request->bedrooms);
        }

        // City/state filter
        if ($request->filled('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }
        if ($request->filled('state')) {
            $query->where('state', $request->state);
        }

        // Search
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('address', 'like', "%{$s}%")
                  ->orWhere('city', 'like', "%{$s}%")
                  ->orWhere('content', 'like', "%{$s}%");
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
                "*, (3959 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance",
                [$lat, $lng, $lat]
            )->having('distance', '<', $r)
              ->orderBy('distance');
        } else {
            $query->orderByDesc('created_at');
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate(20),
        ]);
    }

    /**
     * GET /api/realestate/{id}
     * Single listing with view_count increment
     */
    public function show($id)
    {
        $listing = RealEstateListing::with('user:id,name,nickname,avatar')->findOrFail($id);
        $listing->increment('view_count');

        $data = $listing->toArray();

        // Bookmark status
        $data['is_bookmarked'] = false;
        if (Auth::check()) {
            $data['is_bookmarked'] = Bookmark::where('user_id', Auth::id())
                ->where('bookmarkable_type', RealEstateListing::class)
                ->where('bookmarkable_id', $id)
                ->exists();
        }

        // Comments
        $data['comments'] = Comment::where('commentable_type', 'real_estate_listing')
            ->where('commentable_id', $id)
            ->with('user:id,name,nickname,avatar')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * POST /api/realestate
     * Create listing with image upload, location data
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:200',
            'type'        => 'required|in:렌트,매매,룸메이트,상가,전세',
            'content'     => 'nullable|string',
            'address'     => 'required|string',
            'price'       => 'nullable|numeric|min:0',
            'deposit'     => 'nullable|numeric|min:0',
            'bedrooms'    => 'nullable|integer|min:0',
            'bathrooms'   => 'nullable|integer|min:0',
            'sqft'        => 'nullable|integer|min:0',
            'lat'         => 'nullable|numeric',
            'lng'         => 'nullable|numeric',
            'images'      => 'nullable|array|max:10',
            'photos.*'    => 'image|max:5120',
        ]);

        $photos = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('realestate', 'public');
                $photos[] = Storage::url($path);
            }
        }

        $listing = RealEstateListing::create(array_merge(
            $request->only([
                'title', 'content', 'type', 'property_type', 'price', 'deposit',
                'address', 'city', 'state', 'zipcode', 'lat', 'lng',
                'bedrooms', 'bathrooms', 'sqft',
                'contact_phone', 'contact_email',
            ]),
            [
                'user_id' => Auth::id(),
                'images'  => $photos,
            ]
        ));

        return response()->json([
            'success' => true,
            'message' => '매물이 등록되었습니다.',
            'data'    => $listing,
        ], 201);
    }

    /**
     * PUT /api/realestate/{id}
     * Update own listing
     */
    public function update(Request $request, $id)
    {
        $listing = RealEstateListing::findOrFail($id);

        if ($listing->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json([
                'success' => false,
                'message' => '수정 권한이 없습니다.',
            ], 403);
        }

        $request->validate([
            'title'    => 'sometimes|string|max:200',
            'type'     => 'sometimes|in:렌트,매매,룸메이트,상가,전세',
            'price'    => 'nullable|numeric|min:0',
            'images'   => 'nullable|array|max:10',
            'photos.*' => 'image|max:5120',
        ]);

        if ($request->hasFile('images')) {
            $photos = [];
            foreach ($request->file('images') as $file) {
                $path = $file->store('realestate', 'public');
                $photos[] = Storage::url($path);
            }
            $listing->photos = $photos;
        }

        $listing->fill($request->only([
            'title', 'content', 'type', 'property_type', 'price', 'deposit',
            'address', 'city', 'state', 'zipcode', 'lat', 'lng',
            'bedrooms', 'bathrooms', 'sqft',
            'contact_phone', 'contact_email', 'is_active',
        ]));
        $listing->save();

        return response()->json([
            'success' => true,
            'message' => '수정되었습니다.',
            'data'    => $listing,
        ]);
    }

    /**
     * DELETE /api/realestate/{id}
     */
    public function destroy($id)
    {
        $listing = RealEstateListing::findOrFail($id);

        if ($listing->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json([
                'success' => false,
                'message' => '삭제 권한이 없습니다.',
            ], 403);
        }

        $listing->delete();

        return response()->json([
            'success' => true,
            'message' => '삭제되었습니다.',
        ]);
    }

    /**
     * POST /api/realestate/{id}/comment
     */
    public function comment(Request $request, $id)
    {
        RealEstateListing::findOrFail($id);
        $request->validate(['content' => 'required|string|max:2000']);

        $comment = Comment::create([
            'commentable_type' => 'real_estate_listing',
            'commentable_id'   => $id,
            'user_id'          => Auth::id(),
            'content'          => $request->content,
        ]);

        return response()->json([
            'success' => true,
            'message' => '댓글이 등록되었습니다.',
            'data'    => $comment->load('user:id,name,nickname,avatar'),
        ], 201);
    }

    /**
     * POST /api/realestate/{id}/bookmark
     */
    public function bookmark($id)
    {
        RealEstateListing::findOrFail($id);
        $userId = Auth::id();

        $existing = Bookmark::where('user_id', $userId)
            ->where('bookmarkable_type', RealEstateListing::class)
            ->where('bookmarkable_id', $id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['success' => true, 'data' => ['bookmarked' => false]]);
        }

        Bookmark::create([
            'user_id'           => $userId,
            'bookmarkable_type' => RealEstateListing::class,
            'bookmarkable_id'   => $id,
        ]);

        return response()->json(['success' => true, 'data' => ['bookmarked' => true]]);
    }

    /**
     * Admin: GET /api/admin/realestate
     */
    public function adminIndex(Request $request)
    {
        $q = RealEstateListing::with('user:id,name')->latest();
        if ($request->filled('search')) {
            $q->where('title', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }
        if ($request->filled('is_active')) {
            $q->where('is_active', (bool) $request->is_active);
        }

        return response()->json(['success' => true, 'data' => $q->paginate(25)]);
    }

    /**
     * Admin: DELETE /api/admin/realestate/{id}
     */
    public function adminDestroy($id)
    {
        RealEstateListing::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => '삭제되었습니다.']);
    }

    /**
     * Admin: PATCH /api/admin/realestate/{id}/toggle
     */
    public function adminToggle($id)
    {
        $listing = RealEstateListing::findOrFail($id);
        $listing->is_active = !$listing->is_active;
        $listing->save();

        return response()->json([
            'success' => true,
            'message' => '상태가 변경되었습니다.',
            'data'    => ['is_active' => $listing->is_active],
        ]);
    }
}
