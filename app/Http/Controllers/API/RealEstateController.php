<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RealEstateListing;
use App\Traits\AdminAuthorizes;
use App\Traits\CompressesUploads;
use App\Traits\HasPromotions;
use Illuminate\Http\Request;

class RealEstateController extends Controller
{
    use AdminAuthorizes, CompressesUploads, HasPromotions;

    protected string $promoResource = 'realestate';
    protected string $promoModel = \App\Models\RealEstateListing::class;
    // 부동산은 type (sale/rent/roommate) 을 카테고리로 사용
    protected string $promoCategoryColumn = 'type';

    public function promote(Request $request, $id)
    {
        $item = $this->findOwnedOrAdmin(\App\Models\RealEstateListing::class, $id);
        return $this->handlePromote($item, $request);
    }

    public function promotionSlots(Request $request)
    {
        return $this->handlePromotionSlots($request);
    }

    public function index(Request $request)
    {
        $this->expireStalePromotions();

        $query = RealEstateListing::with('user:id,name,nickname');
        // user_id 필터: 본인 것은 비활성 포함, 남의 것은 active 만
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        } else {
            $query->active();
        }
        $query->when($request->type, fn($q, $v) => $q->where('type', $v))
            ->when($request->property_type, fn($q, $v) => $q->where('property_type', $v))
            ->when($request->min_price, fn($q, $v) => $q->where('price', '>=', $v))
            ->when($request->max_price, fn($q, $v) => $q->where('price', '<=', $v))
            ->when($request->bedrooms, fn($q, $v) => $q->where('bedrooms', '>=', $v))
            ->when($request->search, fn($q, $v) => $q->where('title', 'like', "%{$v}%"));

        $hasLocation = $request->lat && $request->lng;
        if ($hasLocation) {
            $query->nearby($request->lat, $request->lng, $request->radius ?? 50);
        }

        $this->excludeCrossTierPromotion($query, $hasLocation);
        $this->applyPromotionOrdering($query, $request->user_state, $hasLocation);
        $query->orderByDesc('created_at');

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
            foreach ($request->file('images') as $img) $images[] = $this->storeCompressedImageRaw($img, 'realestate', 1400, 80);
        }

        $data = $request->only('title', 'content', 'type', 'property_type', 'price', 'deposit', 'address', 'city', 'state', 'zipcode', 'lat', 'lng', 'bedrooms', 'bathrooms', 'sqft', 'contact_phone', 'contact_email');

        // zipcode 기반 lat/lng 자동 지오코딩 (좌표 없으면 거리 필터에서 빠짐)
        if ((empty($data['lat']) || empty($data['lng'])) && !empty($data['zipcode'])) {
            $geo = $this->geocodeZip($data['zipcode']);
            if ($geo) {
                $data['lat'] = $geo['lat'];
                $data['lng'] = $geo['lng'];
                if (empty($data['city'])) $data['city'] = $geo['city'];
                if (empty($data['state'])) $data['state'] = $geo['state'];
            }
        }
        // 여전히 좌표 없으면 유저 프로필로 fallback
        $u = auth()->user();
        if (empty($data['lat'])) $data['lat'] = $u->latitude;
        if (empty($data['lng'])) $data['lng'] = $u->longitude;
        if (empty($data['city'])) $data['city'] = $u->city;
        if (empty($data['state'])) $data['state'] = $u->state;

        $listing = RealEstateListing::create(array_merge(
            $data, ['user_id' => auth()->id(), 'images' => $images ?: null]
        ));

        return response()->json(['success' => true, 'data' => $listing], 201);
    }

    public function update(Request $request, $id)
    {
        $listing = $this->findOwnedOrAdmin(RealEstateListing::class, $id);
        $data = $request->only('title', 'content', 'type', 'property_type', 'price', 'deposit', 'address', 'city', 'state', 'zipcode', 'bedrooms', 'bathrooms', 'sqft', 'contact_phone', 'contact_email', 'is_active');
        // zipcode 변경시 좌표 재지오코딩
        if (!empty($data['zipcode']) && $data['zipcode'] !== $listing->zipcode) {
            $geo = $this->geocodeZip($data['zipcode']);
            if ($geo) {
                $data['lat'] = $geo['lat'];
                $data['lng'] = $geo['lng'];
            }
        }
        $listing->update($data);
        return response()->json(['success' => true, 'data' => $listing]);
    }

    private function geocodeZip(?string $zip): ?array
    {
        if (!$zip || !preg_match('/^\d{5}$/', $zip)) return null;
        try {
            return \Cache::remember("geo_zip_{$zip}", now()->addHours(24), function () use ($zip) {
                $ctx = stream_context_create(['http' => ['timeout' => 3]]);
                $resp = @file_get_contents("https://api.zippopotam.us/us/{$zip}", false, $ctx);
                if (!$resp) return null;
                $d = json_decode($resp, true);
                $place = $d['places'][0] ?? null;
                if (!$place) return null;
                return [
                    'lat' => (float) $place['latitude'],
                    'lng' => (float) $place['longitude'],
                    'city' => $place['place name'] ?? null,
                    'state' => $place['state abbreviation'] ?? null,
                ];
            });
        } catch (\Throwable $e) { return null; }
    }

    public function destroy($id)
    {
        $this->findOwnedOrAdmin(RealEstateListing::class, $id)->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => '삭제되었습니다']);
    }
}
