<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MarketItem;
use Illuminate\Http\Request;

class MarketController extends Controller
{
    public function index(Request $request)
    {
        $query = MarketItem::with('user:id,name,nickname')
            ->whereIn('status', ['active', 'reserved'])
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->when($request->condition, fn($q, $v) => $q->where('condition', $v))
            ->when($request->search, fn($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->when($request->min_price, fn($q, $v) => $q->where('price', '>=', $v))
            ->when($request->max_price, fn($q, $v) => $q->where('price', '<=', $v));

        if ($request->lat && $request->lng) {
            $query->nearby($request->lat, $request->lng, $request->radius ?? 10);
        } else {
            $query->orderByDesc('created_at');
        }

        return response()->json(['success' => true, 'data' => $query->paginate($request->per_page ?? 20)]);
    }

    public function show($id)
    {
        $item = MarketItem::with('user:id,name,nickname,avatar')->findOrFail($id);
        $item->increment('view_count');
        return response()->json(['success' => true, 'data' => $item]);
    }

    public function store(Request $request)
    {
        $request->validate(['title' => 'required|max:200', 'content' => 'required', 'price' => 'required|numeric|min:0', 'category' => 'required', 'condition' => 'required']);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $images[] = $img->store('market', 'public');
            }
        }

        $item = MarketItem::create(array_merge(
            $request->only('title', 'content', 'price', 'category', 'condition', 'lat', 'lng', 'city', 'state', 'is_negotiable'),
            ['user_id' => auth()->id(), 'images' => $images ?: null]
        ));

        return response()->json(['success' => true, 'data' => $item], 201);
    }

    public function update(Request $request, $id)
    {
        $item = MarketItem::where('user_id', auth()->id())->findOrFail($id);
        $item->update($request->only('title', 'content', 'price', 'category', 'condition', 'status', 'is_negotiable'));
        return response()->json(['success' => true, 'data' => $item]);
    }

    public function destroy($id)
    {
        MarketItem::where('user_id', auth()->id())->findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => '삭제되었습니다']);
    }
}
