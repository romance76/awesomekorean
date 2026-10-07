<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AmazonProduct;
use App\Models\AmazonProductClick;
use App\Support\AmazonLink;
use App\Traits\CompressesUploads;
use Illuminate\Http\Request;

/**
 * Amazon Associates 제휴 상품 관리자 CRUD. 상품명/가격/설명 등을 Amazon에서
 * 자동으로 긁어오지 않음(제휴 정책상 scraping 금지) — ASIN 또는 Amazon 상품
 * URL만 입력받아 링크를 생성하고, 나머지는 관리자가 직접 입력한다.
 *
 * 이미지는 두 종류로 구분:
 *  - amazon_image_urls: Amazon이 호스팅하는 이미지 URL을 그대로 링크(재호스팅 금지)
 *  - own_image_urls: 관리자가 직접 촬영/제작한 이미지 — Amazon 소유가 아니므로 서버에 저장 가능
 */
class AdminAmazonProductController extends Controller
{
    use CompressesUploads;

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
        // FormData(멀티파트)에서는 boolean이 "true"/"false" 문자열로 오므로 검증 전에 변환
        $request->merge([
            'is_featured' => filter_var($request->is_featured, FILTER_VALIDATE_BOOLEAN),
            'is_active'   => filter_var($request->is_active ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);

        $data = $request->validate([
            'input'               => 'required|string', // ASIN 또는 Amazon 상품 URL
            'title'               => 'required|string|max:255',
            'category'            => 'nullable|string|max:50',
            'price'               => 'nullable|numeric|min:0',
            'amazon_image_urls'   => 'nullable|array',
            'amazon_image_urls.*' => 'nullable|string|max:1000',
            'own_images'          => 'nullable|array|max:10',
            'own_images.*'        => 'nullable|image|max:10240',
            'our_description'     => 'nullable|string',
            'display_order'       => 'nullable|integer',
            'is_featured'         => 'nullable|boolean',
            'is_active'           => 'nullable|boolean',
        ]);

        $asin = AmazonLink::extractAsin($data['input']);
        if (!$asin) {
            return response()->json(['success' => false, 'message' => 'ASIN을 인식할 수 없습니다. ASIN 또는 Amazon 상품 URL을 입력해주세요.'], 422);
        }

        if (AmazonProduct::where('asin', $asin)->exists()) {
            return response()->json(['success' => false, 'message' => '이미 등록된 상품입니다 (ASIN 중복).'], 422);
        }

        $amazonImages = array_values(array_filter($data['amazon_image_urls'] ?? []));
        $ownImages = $request->hasFile('own_images')
            ? $this->storeCompressedImages($request->file('own_images'), 'shopping', 1200, 82)
            : [];

        $product = AmazonProduct::create([
            'asin'              => $asin,
            'amazon_url'        => AmazonLink::amazonUrl($asin),
            'affiliate_url'     => AmazonLink::affiliateUrl($asin),
            'title'             => $data['title'],
            'image_url'         => $amazonImages[0] ?? $ownImages[0] ?? null,
            'category'          => $data['category'] ?? null,
            'price'             => $data['price'] ?? null,
            'amazon_image_urls' => $amazonImages ?: null,
            'own_image_urls'    => $ownImages ?: null,
            'our_description'   => isset($data['our_description'])
                ? \App\Support\HtmlSanitizer::clean($this->extractAndCompressBase64Images($data['our_description'], 'shopping'))
                : null,
            'display_order'     => $data['display_order'] ?? 0,
            'is_featured'       => $data['is_featured'] ?? false,
            'is_active'         => $data['is_active'] ?? true,
        ]);

        return response()->json(['success' => true, 'data' => $product], 201);
    }

    public function update(Request $request, $id)
    {
        $product = AmazonProduct::findOrFail($id);

        if ($request->has('is_featured')) {
            $request->merge(['is_featured' => filter_var($request->is_featured, FILTER_VALIDATE_BOOLEAN)]);
        }
        if ($request->has('is_active')) {
            $request->merge(['is_active' => filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)]);
        }

        $data = $request->validate([
            'title'                    => 'sometimes|string|max:255',
            'category'                 => 'nullable|string|max:50',
            'price'                    => 'nullable|numeric|min:0',
            'amazon_image_urls'        => 'nullable|array',
            'amazon_image_urls.*'      => 'nullable|string|max:1000',
            'keep_own_image_urls'      => 'nullable|array',
            'keep_own_image_urls.*'    => 'nullable|string|max:1000',
            'own_images'               => 'nullable|array|max:10',
            'own_images.*'             => 'nullable|image|max:10240',
            'our_description'          => 'nullable|string',
            'display_order'            => 'nullable|integer',
            'is_featured'              => 'nullable|boolean',
            'is_active'                => 'nullable|boolean',
        ]);

        if (array_key_exists('amazon_image_urls', $data)) {
            $data['amazon_image_urls'] = array_values(array_filter($data['amazon_image_urls'] ?? [])) ?: null;
        }

        if ($request->has('keep_own_image_urls') || $request->hasFile('own_images')) {
            $kept = array_values(array_filter($data['keep_own_image_urls'] ?? []));
            $uploaded = $request->hasFile('own_images')
                ? $this->storeCompressedImages($request->file('own_images'), 'shopping', 1200, 82)
                : [];
            $data['own_image_urls'] = array_values(array_merge($kept, $uploaded)) ?: null;
        }
        unset($data['keep_own_image_urls'], $data['own_images']);

        if (isset($data['our_description'])) {
            $data['our_description'] = \App\Support\HtmlSanitizer::clean($this->extractAndCompressBase64Images($data['our_description'], 'shopping'));
        }

        $product->update($data);

        // 대표 썸네일(image_url)은 Amazon 이미지 → 직접 업로드 이미지 순으로 자동 동기화
        $primary = ($data['amazon_image_urls'] ?? $product->amazon_image_urls ?? [])[0]
            ?? ($data['own_image_urls'] ?? $product->own_image_urls ?? [])[0]
            ?? null;
        if ($primary !== $product->image_url) {
            $product->update(['image_url' => $primary]);
        }

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
