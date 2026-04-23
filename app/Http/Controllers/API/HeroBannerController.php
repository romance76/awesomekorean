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

    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => HeroBanner::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->payload($request);
        if ($request->hasFile('image')) {
            $data['image_url'] = $this->storeCompressedImage($request->file('image'), 'hero-banners', 1600, 85);
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
        $data = $request->except(['image', '_method']);
        foreach (['is_active'] as $boolField) {
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
        foreach ([
            'https://awesomekorean.com/api/hero-banners',
            'http://awesomekorean.com/api/hero-banners',
        ] as $u) {
            Cache::forget('api_cache_' . md5($u));
        }
    }
}
