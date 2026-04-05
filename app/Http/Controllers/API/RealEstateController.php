<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RealEstateListing;
use Illuminate\Http\Request;

class RealEstateController extends Controller
{
    public function index(Request $request)
    {
        $query = RealEstateListing::with('user:id,name,nickname')
            ->active()
            ->when($request->type, fn($q, $v) => $q->where('type', $v))
            ->when($request->property_type, fn($q, $v) => $q->where('property_type', $v))
            ->when($request->min_price, fn($q, $v) => $q->where('price', '>=', $v))
            ->when($request->max_price, fn($q, $v) => $q->where('price', '<=', $v))
            ->when($request->bedrooms, fn($q, $v) => $q->where('bedrooms', '>=', $v))
            ->when($request->search, fn($q, $v) => $q->where('title', 'like', "%{$v}%"));

        if ($request->lat && $request->lng) {
            $query->nearby($request->lat, $request->lng, $request->radius ?? 50);
        } else {
            $query->orderByDesc('created_at');
        }

        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    public function show($id)
    {
        $listing = RealEstateListing::with('user:id,name,nickname,avatar')->findOrFail($id);
        $listing->increment('view_count');
        return response()->json(['success' => true, 'data' => $listing]);
    }

    public function store(Request $request)
    {
        $request->validate(['title' => 'required|max:200', 'content' => 'required', 'type' => 'required', 'property_type' => 'required', 'price' => 'required|numeric']);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) $images[] = $img->store('realestate', 'public');
        }

        $listing = RealEstateListing::create(array_merge(
            $request->only('title', 'content', 'type', 'property_type', 'price', 'deposit', 'address', 'city', 'state', 'zipcode', 'lat', 'lng', 'bedrooms', 'bathrooms', 'sqft', 'contact_phone', 'contact_email'),
            ['user_id' => auth()->id(), 'images' => $images ?: null]
        ));

        return response()->json(['success' => true, 'data' => $listing], 201);
    }

    public function update(Request $request, $id)
    {
        $listing = RealEstateListing::where('user_id', auth()->id())->findOrFail($id);
        $listing->update($request->only('title', 'content', 'price', 'deposit', 'is_active'));
        return response()->json(['success' => true, 'data' => $listing]);
    }

    public function destroy($id)
    {
        RealEstateListing::where('user_id', auth()->id())->findOrFail($id)->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => '삭제되었습니다']);
    }
}
