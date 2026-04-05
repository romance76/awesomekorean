<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MarketItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MarketController extends Controller
{
    /**
     * GET /api/market
     */
    public function index(Request $request)
    {
        $query = MarketItem::with('user:id,name,nickname,avatar')
            ->whereIn('status', ['active', 'reserved']);

        // Category filter
        if ($request->category) {
            $query->where('category', $request->category);
        }

        // Condition filter
        if ($request->condition) {
            $query->where('condition', $request->condition);
        }

        // Price range
        if ($request->min_price) {
            $query->where('price', '>=', (float) $request->min_price);
        }
        if ($request->max_price) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        // Search
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Distance filter
        if ($request->lat && $request->lng) {
            $lat = (float) $request->lat;
            $lng = (float) $request->lng;
            $radius = (float) ($request->radius ?? 30);
            $query->selectRaw("market_items.*, (3959 * acos(cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))) AS distance", [$lat, $lng, $lat])
                  ->having('distance', '<=', $radius)
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
     * GET /api/market/{id}
     */
    public function show($id)
    {
        $item = MarketItem::with('user:id,name,nickname,avatar')->findOrFail($id);
        $item->increment('view_count');

        return response()->json([
            'success' => true,
            'data'    => $item,
        ]);
    }

    /**
     * POST /api/market
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:200',
            'content' => 'required|string',
            'price'       => 'required|numeric|min:0',
            'category'    => 'nullable|string|max:50',
            'condition'   => 'nullable|string|max:50',
            'images'      => 'nullable|array|max:10',
            'images.*'    => 'image|max:5120',
        ]);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('market', 'public');
            }
        }

        $item = MarketItem::create(array_merge(
            $request->only([
                'title', 'content', 'price', 'is_negotiable',
                'category', 'condition',
            ]),
            [
                'user_id' => auth()->id(),
                'images'  => !empty($images) ? json_encode($images) : null,
            ]
        ));

        return response()->json([
            'success' => true,
            'message' => '등록되었습니다.',
            'data'    => $item,
        ], 201);
    }

    /**
     * PUT /api/market/{id}
     */
    public function update(Request $request, $id)
    {
        $item = MarketItem::findOrFail($id);

        if ($item->user_id !== auth()->id() && !auth()->user()->is_admin) {
            return response()->json(['success' => false, 'message' => '수정 권한이 없습니다.'], 403);
        }

        $request->validate([
            'title'       => 'sometimes|string|max:200',
            'content' => 'sometimes|string',
            'price'       => 'sometimes|numeric|min:0',
        ]);

        $data = $request->only([
            'title', 'content', 'price', 'is_negotiable',
            'category', 'condition', 'status',
        ]);

        // Handle new image uploads
        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('market', 'public');
            }
            $data['images'] = json_encode($images);
        }

        $item->update($data);

        return response()->json([
            'success' => true,
            'message' => '수정되었습니다.',
            'data'    => $item->fresh(),
        ]);
    }

    /**
     * DELETE /api/market/{id}
     */
    public function destroy($id)
    {
        $item = MarketItem::findOrFail($id);

        if ($item->user_id !== auth()->id() && !auth()->user()->is_admin) {
            return response()->json(['success' => false, 'message' => '삭제 권한이 없습니다.'], 403);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => '삭제되었습니다.',
        ]);
    }
}
