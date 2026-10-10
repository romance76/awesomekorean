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
        $d = $request->validate([
            'title' => 'required|string|max:100',
            'discount_pct' => 'required|integer|min:0|max:100',
            'applies_to_ads' => 'nullable|boolean',
            'applies_to_packages' => 'nullable|boolean',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after_or_equal:starts_at',
            'is_active' => 'nullable|boolean',
        ]);
        // 포인트 패키지(카드 결제)에 100% 할인은 청구액 $0 이 되어 결제가 깨지므로 95% 까지만
        if (!empty($d['applies_to_packages']) && (int) $d['discount_pct'] > 95) {
            throw \Illuminate\Validation\ValidationException::withMessages(['discount_pct' => ['포인트 패키지 할인은 95% 까지만 가능해요 (결제 금액이 0원이 되지 않게)']]);
        }
        // 날짜만 입력하면(시각 없음) 시작은 그날 0시, 종료는 그날 끝(23:59:59)까지 — 애틀랜타 시간 기준으로 맞춘다
        $ny = 'America/New_York';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $request->input('starts_at')))) {
            $d['starts_at'] = \Carbon\Carbon::parse($request->input('starts_at'), $ny)->startOfDay()->utc();
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $request->input('ends_at')))) {
            $d['ends_at'] = \Carbon\Carbon::parse($request->input('ends_at'), $ny)->endOfDay()->utc();
        }
        return $d;
    }
}
