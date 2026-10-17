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

    const PUBLIC_CACHE_KEY = 'popup_banners_public';

    /** 공개: 현재 게시 중인 활성 배너 목록 */
    public function publicActive()
    {
        $items = Cache::remember(self::PUBLIC_CACHE_KEY, 600, function () {
            return PopupBanner::activeNow()->orderBy('sort_order')->get();
        });
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

    /** POST /api/admin/popup-banners/image — 전체창 배너 그림 올리기(PC·휴대폰). 주소를 돌려준다 */
    public function uploadImage(Request $request)
    {
        $request->validate(['image' => 'required|image|max:10240']);
        $url = $this->storeCompressedImage($request->file('image'), 'popup-banners', 2200, 88);
        return response()->json(['success' => true, 'url' => $url]);
    }

    public function destroy($id)
    {
        PopupBanner::findOrFail($id)->delete();
        $this->clearPublicCache();
        return response()->json(['success' => true]);
    }

    private function validated(Request $request, $id = null): array
    {
        // 관리자 화면은 파일 업로드(multipart)로 보내서 extra 가 JSON 글자로 온다 — 풀어서 검사한다
        if (is_string($request->input('extra'))) {
            $decoded = json_decode($request->input('extra'), true);
            $request->merge(['extra' => is_array($decoded) ? $decoded : null]);
        }
        $safeUrl = function ($attr, $value, $fail) {
            if ($value !== null && $value !== '' && !\App\Support\SafeUrl::ok($value)) $fail('링크는 / 로 시작하는 사이트 안 경로나 http(s):// 주소만 쓸 수 있어요');
        };
        return $request->validate([
            'title'        => 'required|string|max:100',
            'type'         => 'required|in:image,text',
            'layout'       => 'nullable|in:modal,fullscreen',
            'extra'                   => 'nullable|array',
            'extra.eyebrow'           => 'nullable|string|max:40',
            'extra.headline'          => 'nullable|string|max:80',
            'extra.subtitle'          => 'nullable|string|max:300',
            'extra.show_open_event'   => 'nullable|boolean',
            'extra.features_title'    => 'nullable|string|max:40',
            'extra.features_text'     => 'nullable|string|max:900',
            'extra.note'              => 'nullable|string|max:300',
            'extra.cta_label'         => 'nullable|string|max:20',
            // 그림형: 완성 그림(PC·휴대폰)과 그 위의 눌리는 영역(그림 크기 대비 %)
            'extra.img_desktop'       => ['nullable', 'string', 'max:500', $safeUrl],
            'extra.img_mobile'        => ['nullable', 'string', 'max:500', $safeUrl],
            'extra.hotspots_desktop'            => 'nullable|array|max:40',
            'extra.hotspots_desktop.*.type'     => 'required|in:close,cta,link,check',
            'extra.hotspots_desktop.*.x'        => 'required|numeric|between:0,100',
            'extra.hotspots_desktop.*.y'        => 'required|numeric|between:0,100',
            'extra.hotspots_desktop.*.w'        => 'required|numeric|between:0,100',
            'extra.hotspots_desktop.*.h'        => 'required|numeric|between:0,100',
            'extra.hotspots_desktop.*.link'     => ['nullable', 'string', 'max:500', $safeUrl],
            'extra.hotspots_desktop.*.label'    => 'nullable|string|max:40',
            'extra.hotspots_mobile'             => 'nullable|array|max:40',
            'extra.hotspots_mobile.*.type'      => 'required|in:close,cta,link,check',
            'extra.hotspots_mobile.*.x'         => 'required|numeric|between:0,100',
            'extra.hotspots_mobile.*.y'         => 'required|numeric|between:0,100',
            'extra.hotspots_mobile.*.w'         => 'required|numeric|between:0,100',
            'extra.hotspots_mobile.*.h'         => 'required|numeric|between:0,100',
            'extra.hotspots_mobile.*.link'      => ['nullable', 'string', 'max:500', $safeUrl],
            'extra.hotspots_mobile.*.label'     => 'nullable|string|max:40',
            'extra.cta_link'          => ['nullable', 'string', 'max:500', $safeUrl],
            'content'      => 'nullable|string',
            'width'        => 'nullable|integer|min:200|max:1200',
            'height'       => 'nullable|integer|min:150|max:900',
            'link_url'     => ['nullable', 'string', 'max:500', function ($attr, $value, $fail) { if (!\App\Support\SafeUrl::ok($value)) $fail('링크는 / 로 시작하는 사이트 안 경로나 http(s):// 주소만 쓸 수 있어요'); }],
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
        Cache::forget(self::PUBLIC_CACHE_KEY);
    }
}
