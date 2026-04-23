<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PopupBanner;
use App\Traits\CompressesUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PopupBannerController extends Controller
{
    use CompressesUploads;

    /** 공개: 현재 게시 중인 활성 배너 목록 */
    public function publicActive()
    {
        $items = PopupBanner::activeNow()->orderBy('sort_order')->get();
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function index()
    {
        $items = PopupBanner::orderBy('sort_order')->orderByDesc('id')->get();
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            $data['image_url'] = $this->storeCompressedImage($request->file('image'), 'popup-banners', 1200, 82);
        }
        $banner = PopupBanner::create($data);
        $this->clearPublicCache();
        return response()->json(['success' => true, 'data' => $banner]);
    }

    public function update(Request $request, $id)
    {
        $banner = PopupBanner::findOrFail($id);
        $data = $this->validated($request, $banner->id);
        if ($request->hasFile('image')) {
            $data['image_url'] = $this->storeCompressedImage($request->file('image'), 'popup-banners', 1200, 82);
        }
        $banner->update($data);
        $this->clearPublicCache();
        return response()->json(['success' => true, 'data' => $banner->fresh()]);
    }

    public function destroy($id)
    {
        PopupBanner::findOrFail($id)->delete();
        $this->clearPublicCache();
        return response()->json(['success' => true]);
    }

    private function validated(Request $request, $id = null): array
    {
        return $request->validate([
            'title'        => 'required|string|max:100',
            'type'         => 'required|in:image,text',
            'content'      => 'nullable|string',
            'width'        => 'nullable|integer|min:200|max:1200',
            'height'       => 'nullable|integer|min:150|max:900',
            'link_url'     => 'nullable|string|max:500',
            'display_mode' => 'required|in:once_per_day,every_visit',
            'is_active'    => 'nullable|boolean',
            'start_at'     => 'nullable|date',
            'end_at'       => 'nullable|date',
            'sort_order'   => 'nullable|integer',
            'image'        => 'nullable|image|max:10240',
        ]);
    }

    private function clearPublicCache(): void
    {
        foreach ([
            'https://awesomekorean.com/api/popup-banners/active',
            'http://awesomekorean.com/api/popup-banners/active',
        ] as $u) {
            Cache::forget('api_cache_' . md5($u));
        }
    }
}
