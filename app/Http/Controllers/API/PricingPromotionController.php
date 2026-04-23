<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PricingPromotion;
use Illuminate\Http\Request;

class PricingPromotionController extends Controller
{
    /** 공개: 현재 유효한 이벤트 (ad/package 각각의 최대 할인%) */
    public function publicActive()
    {
        $ad = PricingPromotion::currentFor('ad');
        $pkg = PricingPromotion::currentFor('package');
        return response()->json([
            'success' => true,
            'data' => [
                'ad' => $ad ? [
                    'title' => $ad->title,
                    'discount_pct' => $ad->discount_pct,
                    'ends_at' => $ad->ends_at,
                ] : null,
                'package' => $pkg ? [
                    'title' => $pkg->title,
                    'discount_pct' => $pkg->discount_pct,
                    'ends_at' => $pkg->ends_at,
                ] : null,
            ],
        ]);
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => PricingPromotion::orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $item = PricingPromotion::create($data);
        return response()->json(['success' => true, 'data' => $item]);
    }

    public function update(Request $request, $id)
    {
        $item = PricingPromotion::findOrFail($id);
        $item->update($this->validated($request));
        return response()->json(['success' => true, 'data' => $item->fresh()]);
    }

    public function destroy($id)
    {
        PricingPromotion::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:100',
            'discount_pct' => 'required|integer|min:1|max:95',
            'applies_to_ads' => 'nullable|boolean',
            'applies_to_packages' => 'nullable|boolean',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after_or_equal:starts_at',
            'is_active' => 'nullable|boolean',
        ]);
    }
}
