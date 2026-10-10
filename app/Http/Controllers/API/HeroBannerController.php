<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HeroBanner;
use App\Traits\CompressesUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HeroBannerController extends Controller
{
    use CompressesUploads;

    const PUBLIC_CACHE_KEY = 'hero_banners_public';

    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => HeroBanner::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['title' => 'required|string|max:200']);
        $data = $this->payload($request);
        if ($request->hasFile('image')) {
            $data['image_url'] = $this->storeCompressedImage($request->file('image'), 'hero-banners', 1600, 85);
        }
        if ($request->hasFile('image_en')) {
            $data['image_url_en'] = $this->storeCompressedImage($request->file('image_en'), 'hero-banners', 1600, 85);
        }
        $banner = HeroBanner::create($data);
        $this->clearPublicCache();
        return response()->json(['success' => true, 'data' => $banner]);
    }

    public function update(Request $request, $id)
    {
        $banner = HeroBanner::findOrFail($id);
        $data = $this->payload($request);
        if ($request->hasFile('image')) {
            $data['image_url'] = $this->storeCompressedImage($request->file('image'), 'hero-banners', 1600, 85);
        }
        if ($request->hasFile('image_en')) {
            $data['image_url_en'] = $this->storeCompressedImage($request->file('image_en'), 'hero-banners', 1600, 85);
        }
        $banner->update($data);
        $this->clearPublicCache();
        return response()->json(['success' => true, 'data' => $banner->fresh()]);
    }

    public function destroy($id)
    {
        HeroBanner::findOrFail($id)->delete();
        $this->clearPublicCache();
        return response()->json(['success' => true]);
    }

    private function payload(Request $request): array
    {
        // multipart 로 오면 문자열 'true'/'false'/'1'/'0' 등을 적절히 캐스팅
        $data = $request->except(['image', 'image_en', '_method']);
        // javascript: 같은 위험한 주소가 링크로 저장되지 않게 (사이트 안 경로 또는 http(s) 만 허용)
        if (array_key_exists('link_url', $data) && !\App\Support\SafeUrl::ok($data['link_url'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['link_url' => ['링크는 / 로 시작하는 사이트 안 경로나 http(s):// 주소만 쓸 수 있어요']]);
        }
        foreach (['is_active', 'image_only'] as $boolField) {
            if (array_key_exists($boolField, $data)) {
                $data[$boolField] = filter_var($data[$boolField], FILTER_VALIDATE_BOOLEAN);
            }
        }
        foreach (['sort_order', 'event_id'] as $intField) {
            if (isset($data[$intField]) && $data[$intField] !== '') {
                $data[$intField] = (int) $data[$intField];
            } elseif (isset($data[$intField]) && $data[$intField] === '') {
                $data[$intField] = null;
            }
        }
        return $data;
    }

    private function clearPublicCache(): void
    {
        Cache::forget(self::PUBLIC_CACHE_KEY);
    }
}
