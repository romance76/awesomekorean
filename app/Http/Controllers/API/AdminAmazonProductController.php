<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AmazonProduct;
use App\Models\AmazonProductClick;
use App\Support\AmazonLink;
use Illuminate\Http\Request;

/**
 * Amazon Associates 제휴 상품 관리자 CRUD. 상품명/이미지/가격 등을 Amazon에서
 * 자동으로 긁어오지 않음(제휴 정책상 scraping 금지) — ASIN 또는 Amazon 상품
 * URL만 입력받아 링크를 생성하고, 상품명/이미지/설명 등은 관리자가 직접 입력한다.
 */
class AdminAmazonProductController extends Controller
{
    public function index(Request $request)
    {
        $query = AmazonProduct::query();

        if ($request->category) {
            $query->where('category', $request->category);
        }
        if ($request->search) {
            $query->where('title', 'LIKE', '%' . $request->search . '%');
        }

        $products = $query->orderByDesc('display_order')->orderByDesc('id')->paginate(20);

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'input'           => 'required|string', // ASIN 또는 Amazon 상품 URL
            'title'           => 'required|string|max:255',
            'image_url'       => 'nullable|string|max:500',
            'category'        => 'nullable|string|max:50',
            'our_description' => 'nullable|string',
            'display_order'   => 'nullable|integer',
            'is_featured'     => 'nullable|boolean',
            'is_active'       => 'nullable|boolean',
        ]);

        $asin = AmazonLink::extractAsin($data['input']);
        if (!$asin) {
            return response()->json(['success' => false, 'message' => 'ASIN을 인식할 수 없습니다. ASIN 또는 Amazon 상품 URL을 입력해주세요.'], 422);
        }

        if (AmazonProduct::where('asin', $asin)->exists()) {
            return response()->json(['success' => false, 'message' => '이미 등록된 상품입니다 (ASIN 중복).'], 422);
        }

        $product = AmazonProduct::create([
            'asin'            => $asin,
            'amazon_url'      => AmazonLink::amazonUrl($asin),
            'affiliate_url'   => AmazonLink::affiliateUrl($asin),
            'title'           => $data['title'],
            'image_url'       => $data['image_url'] ?? null,
            'category'        => $data['category'] ?? null,
            'our_description' => $data['our_description'] ?? null,
            'display_order'   => $data['display_order'] ?? 0,
            'is_featured'     => $data['is_featured'] ?? false,
            'is_active'       => $data['is_active'] ?? true,
        ]);

        return response()->json(['success' => true, 'data' => $product], 201);
    }

    public function update(Request $request, $id)
    {
        $product = AmazonProduct::findOrFail($id);

        $data = $request->validate([
            'title'           => 'sometimes|string|max:255',
            'image_url'       => 'nullable|string|max:500',
            'category'        => 'nullable|string|max:50',
            'our_description' => 'nullable|string',
            'display_order'   => 'nullable|integer',
            'is_featured'     => 'nullable|boolean',
            'is_active'       => 'nullable|boolean',
        ]);

        $product->update($data);

        return response()->json(['success' => true, 'data' => $product]);
    }

    public function destroy($id)
    {
        AmazonProduct::where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    // 통계 카드용 — 상품 수/클릭 수는 자체 트래킹 데이터일 뿐, 실제 Amazon 구매/커미션은
    // Amazon Associates Central에서 확인해야 함(혼동 방지를 위해 프론트에서 안내 문구 표시).
    public function stats()
    {
        return response()->json(['success' => true, 'data' => [
            'total'           => AmazonProduct::count(),
            'active'          => AmazonProduct::where('is_active', true)->count(),
            'featured'        => AmazonProduct::where('is_featured', true)->count(),
            'clicks_total'    => (int) AmazonProduct::sum('clicks'),
            'clicks_today'    => AmazonProductClick::whereDate('clicked_at', today())->count(),
        ]]);
    }
}
