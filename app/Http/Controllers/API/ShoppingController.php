<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AmazonProduct;
use App\Models\AmazonProductClick;
use Illuminate\Http\Request;

class ShoppingController extends Controller
{
    // 공개: Amazon 제휴 상품 목록 (카테고리/검색 필터 + 페이지네이션)
    public function index(Request $request)
    {
        $query = AmazonProduct::where('is_active', true);

        if ($request->category) {
            $query->where('category', $request->category);
        }
        if ($request->search) {
            $query->where('title', 'LIKE', '%' . $request->search . '%');
        }
        if ($request->featured) {
            $query->where('is_featured', true);
        }

        $products = $query->orderByDesc('is_featured')
            ->orderBy('display_order')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $products]);
    }

    // 공개: 상품 상세 (추천 문구 + Amazon 링크 버튼이 있는 블로그 스타일 페이지용)
    public function show($id)
    {
        $product = AmazonProduct::where('is_active', true)->find($id);
        if (!$product) {
            return response()->json(['success' => false, 'message' => '상품을 찾을 수 없습니다'], 404);
        }

        return response()->json(['success' => true, 'data' => $product]);
    }

    // 공개: 클릭 기록 후 Amazon 제휴 링크로 리다이렉트 (routes/web.php에 등록 — SPA 캐치올보다 먼저)
    public function go($id)
    {
        $product = AmazonProduct::find($id);
        if (!$product) {
            abort(404);
        }

        $product->increment('clicks');

        AmazonProductClick::create([
            'amazon_product_id' => $product->id,
            'asin'              => $product->asin,
            'category'          => $product->category,
            'user_id'           => auth('api')->id(),
            'page'              => request()->header('referer'),
            'clicked_at'        => now(),
        ]);

        return redirect()->away($product->affiliate_url);
    }
}
