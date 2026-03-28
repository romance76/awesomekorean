<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ShoppingStore;
use App\Models\ShoppingDeal;
use Illuminate\Http\Request;

class ShoppingController extends Controller
{
    // 마트 목록 (지역 필터)
    public function stores(Request $request)
    {
        $q = ShoppingStore::where('is_active', true);
        if ($request->region && $request->region !== 'all') {
            $q->where(function ($q) use ($request) {
                $q->where('region', $request->region)->orWhere('region', 'National');
            });
        }
        if ($request->type) {
            $q->where('type', $request->type);
        }
        return response()->json($q->withCount(['deals' => fn($q) => $q->where('is_active', true)])->get());
    }

    // 딜/세일 목록
    public function deals(Request $request)
    {
        $q = ShoppingDeal::with('store:id,name,logo,type,region')
            ->where('is_active', true)
            ->where(fn($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()->toDateString()));

        if ($request->store_id) {
            $q->where('store_id', $request->store_id);
        }
        if ($request->region && $request->region !== 'all') {
            $q->whereHas('store', fn($q) => $q->where(function($q) use ($request) {
                $q->where('region', $request->region)->orWhere('region', 'National');
            }));
        }
        if ($request->category) {
            $q->where('category', $request->category);
        }
        if ($request->search) {
            $q->where('title', 'like', '%'.$request->search.'%');
        }
        if ($request->featured) {
            $q->where('is_featured', true);
        }

        $deals = $q->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($deals);
    }

    // 딜 상세
    public function showDeal($id)
    {
        $deal = ShoppingDeal::with('store')->findOrFail($id);
        $deal->increment('view_count');

        // 같은 마트 다른 딜
        $related = ShoppingDeal::where('store_id', $deal->store_id)
            ->where('id', '!=', $id)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        return response()->json(['deal' => $deal, 'related' => $related]);
    }

    // 카테고리 목록
    public function categories()
    {
        $cats = ShoppingDeal::where('is_active', true)
            ->whereNotNull('category')
            ->select('category')
            ->distinct()
            ->pluck('category');
        return response()->json($cats);
    }

    // RSS 새로고침 (관리자용)
    public function fetchRSS($storeId)
    {
        $store = ShoppingStore::findOrFail($storeId);
        if (!$store->rss_url) {
            return response()->json(['message' => 'RSS URL이 없습니다.'], 400);
        }

        try {
            $xml = @simplexml_load_file($store->rss_url);
            if (!$xml) {
                return response()->json(['message' => 'RSS 파싱 실패'], 400);
            }
            $count = 0;
            foreach ($xml->channel->item as $item) {
                $title = (string) $item->title;
                $desc  = strip_tags((string) $item->description);
                $link  = (string) $item->link;

                // 이미지 추출
                $img = null;
                if (isset($item->enclosure['url'])) {
                    $img = (string) $item->enclosure['url'];
                } elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', (string) $item->description, $m)) {
                    $img = $m[1];
                }

                ShoppingDeal::firstOrCreate(
                    ['source_url' => $link, 'store_id' => $store->id],
                    [
                        'title'       => mb_substr($title, 0, 200),
                        'description' => mb_substr($desc, 0, 500),
                        'image_url'   => $img,
                        'valid_from'  => now()->toDateString(),
                        'valid_until' => now()->addDays(7)->toDateString(),
                    ]
                );
                $count++;
            }
            return response()->json(['message' => "{$count}개 딜 업데이트 완료"]);
        } catch (\Exception $e) {
            return response()->json(['message' => '오류: ' . $e->getMessage()], 500);
        }
    }
}
