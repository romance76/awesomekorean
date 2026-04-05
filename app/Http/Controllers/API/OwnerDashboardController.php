<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class OwnerDashboardController extends Controller
{
    private function getOwnedBusiness()
    {
        return DB::table('businesses')
            ->where('owner_id', Auth::id())
            ->where('is_verified', true)
            ->first();
    }

    // GET /api/owner/business
    public function myBusiness()
    {
        $biz = $this->getOwnedBusiness();
        if (!$biz) return response()->json(['error' => '등록된 업소가 없습니다'], 404);

        $biz->events = DB::table('business_events')
            ->where('business_id', $biz->id)
            ->where('is_active', true)
            ->orderByDesc('created_at')->limit(5)->get();

        $biz->recent_reviews = DB::table('business_reviews as r')
            ->join('users as u', 'r.user_id', '=', 'u.id')
            ->select('r.*', 'u.nickname', 'u.avatar')
            ->where('r.business_id', $biz->id)
            ->where('r.is_visible', true)
            ->orderByDesc('r.created_at')->limit(5)->get();

        // Stats summary (last 30 days)
        $stats = DB::table('business_stats')
            ->where('business_id', $biz->id)
            ->where('stat_date', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('SUM(views) as total_views, SUM(phone_clicks) as total_phone, SUM(direction_clicks) as total_directions, SUM(website_clicks) as total_website')
            ->first();
        $biz->stats_30d = $stats;

        return response()->json($biz);
    }

    // PUT /api/owner/business
    public function update(Request $req)
    {
        $biz = $this->getOwnedBusiness();
        if (!$biz) return response()->json(['error' => '등록된 업소가 없습니다'], 404);

        $allowed = ['name', 'name_ko', 'name_en', 'category', 'address', 'phone', 'website',
                    'owner_description_ko', 'owner_description_en', 'hours', 'temp_closed', 'temp_closed_note'];
        $data = $req->only($allowed);
        $data['updated_at'] = now();

        DB::table('businesses')->where('id', $biz->id)->update($data);
        return response()->json(['success' => true, 'message' => '업소 정보가 업데이트되었습니다']);
    }

    // POST /api/owner/business/photos
    public function uploadPhotos(Request $req)
    {
        $biz = $this->getOwnedBusiness();
        if (!$biz) return response()->json(['error' => '없음'], 404);

        $isPremium = $biz->is_premium;
        $maxPhotos = $isPremium ? 20 : 5;

        $req->validate(['photos' => 'required|array', 'photos.*' => 'image|max:5120']);

        $existing = json_decode($biz->owner_photos ?? '[]', true);
        $remaining = $maxPhotos - count($existing);
        if ($remaining <= 0) return response()->json(['error' => "최대 {$maxPhotos}장까지 업로드 가능합니다"], 422);

        $uploaded = [];
        foreach (array_slice($req->file('photos'), 0, $remaining) as $file) {
            $path = $file->store("businesses/{$biz->id}/photos", 'public');
            $uploaded[] = Storage::url($path);
        }

        $all = array_merge($existing, $uploaded);
        DB::table('businesses')->where('id', $biz->id)->update(['owner_photos' => json_encode($all), 'updated_at' => now()]);

        return response()->json(['success' => true, 'photos' => $all, 'count' => count($all), 'max' => $maxPhotos]);
    }

    // PUT /api/owner/business/photos/reorder
    public function reorderPhotos(Request $req)
    {
        $biz = $this->getOwnedBusiness();
        if (!$biz) return response()->json(['error' => '없음'], 404);
        $req->validate(['photos' => 'required|array']);
        DB::table('businesses')->where('id', $biz->id)->update(['owner_photos' => json_encode($req->input('photos')), 'updated_at' => now()]);
        return response()->json(['success' => true]);
    }

    // DELETE /api/owner/business/photos/{index}
    public function deletePhoto(Request $req, $index)
    {
        $biz = $this->getOwnedBusiness();
        if (!$biz) return response()->json(['error' => '없음'], 404);
        $photos = json_decode($biz->owner_photos ?? '[]', true);
        if (!isset($photos[$index])) return response()->json(['error' => '사진 없음'], 404);
        array_splice($photos, $index, 1);
        DB::table('businesses')->where('id', $biz->id)->update(['owner_photos' => json_encode($photos), 'updated_at' => now()]);
        return response()->json(['success' => true]);
    }

    // POST /api/owner/business/menu-item
    public function upsertMenuItem(Request $req)
    {
        $biz = $this->getOwnedBusiness();
        if (!$biz) return response()->json(['error' => '없음'], 404);
        $req->validate(['name' => 'required|string|max:100']);
        $items = json_decode($biz->menu_items ?? '[]', true);
        $newItem = [
            'id' => uniqid(),
            'name' => $req->input('name'),
            'name_ko' => $req->input('name_ko', ''),
            'price' => $req->input('price', ''),
            'description' => $req->input('description', ''),
            'image_url' => $req->input('image_url', ''),
        ];
        $items[] = $newItem;
        DB::table('businesses')->where('id', $biz->id)->update(['menu_items' => json_encode($items), 'updated_at' => now()]);
        return response()->json(['success' => true, 'item' => $newItem]);
    }

    // DELETE /api/owner/business/menu-item/{itemId}
    public function deleteMenuItem($itemId)
    {
        $biz = $this->getOwnedBusiness();
        if (!$biz) return response()->json(['error' => '없음'], 404);
        $items = json_decode($biz->menu_items ?? '[]', true);
        $items = array_values(array_filter($items, fn($i) => $i['id'] !== $itemId));
        DB::table('businesses')->where('id', $biz->id)->update(['menu_items' => json_encode($items), 'updated_at' => now()]);
        return response()->json(['success' => true]);
    }

    // GET /api/owner/reviews
    public function myReviews(Request $req)
    {
        $biz = $this->getOwnedBusiness();
        if (!$biz) return response()->json(['error' => '없음'], 404);
        $reviews = DB::table('business_reviews as r')
            ->join('users as u', 'r.user_id', '=', 'u.id')
            ->select('r.*', 'u.nickname', 'u.avatar')
            ->where('r.business_id', $biz->id)
            ->when($req->query('no_reply'), fn($q) => $q->whereNull('r.owner_reply'))
            ->when($req->query('reported'), fn($q) => $q->where('r.report_count', '>', 0))
            ->orderByDesc('r.created_at')
            ->paginate(20);
        return response()->json($reviews);
    }

    // POST /api/owner/reviews/{id}/reply
    public function replyReview(Request $req, $reviewId)
    {
        $biz = $this->getOwnedBusiness();
        if (!$biz) return response()->json(['error' => '없음'], 404);
        $review = DB::table('business_reviews')->where('id', $reviewId)->where('business_id', $biz->id)->first();
        if (!$review) return response()->json(['error' => '리뷰를 찾을 수 없습니다'], 404);
        if ($review->owner_reply) return response()->json(['error' => '이미 답글을 작성했습니다'], 409);
        $req->validate(['reply' => 'required|string|max:1000']);
        DB::table('business_reviews')->where('id', $reviewId)->update([
            'owner_reply' => $req->input('reply'),
            'owner_replied_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['success' => true]);
    }

    // POST /api/owner/reviews/{id}/report
    public function reportReview(Request $req, $reviewId)
    {
        $biz = $this->getOwnedBusiness();
        if (!$biz) return response()->json(['error' => '없음'], 404);
        DB::table('business_reviews')->where('id', $reviewId)->where('business_id', $biz->id)
            ->increment('report_count');
        return response()->json(['success' => true]);
    }

    // GET /api/owner/events
    public function myEvents()
    {
        $biz = $this->getOwnedBusiness();
        if (!$biz) return response()->json(['error' => '없음'], 404);
        $events = DB::table('business_events')->where('business_id', $biz->id)->orderByDesc('created_at')->get();
        return response()->json($events);
    }

    // POST /api/owner/events
    public function createEvent(Request $req)
    {
        $biz = $this->getOwnedBusiness();
        if (!$biz) return response()->json(['error' => '없음'], 404);
        if (!$biz->is_premium) return response()->json(['error' => '프리미엄 업소만 이벤트를 등록할 수 있습니다', 'upgrade_required' => true], 403);
        $req->validate(['title' => 'required|string|max:200']);
        $id = DB::table('business_events')->insertGetId([
            'business_id' => $biz->id,
            'title' => $req->input('title'),
            'content' => $req->input('content'),
            'image_url' => $req->input('image_url'),
            'starts_at' => $req->input('starts_at'),
            'expires_at' => $req->input('expires_at'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['success' => true, 'id' => $id]);
    }

    // DELETE /api/owner/events/{id}
    public function deleteEvent($eventId)
    {
        $biz = $this->getOwnedBusiness();
        if (!$biz) return response()->json(['error' => '없음'], 404);
        DB::table('business_events')->where('id', $eventId)->where('business_id', $biz->id)->delete();
        return response()->json(['success' => true]);
    }

    // GET /api/owner/stats
    public function stats(Request $req)
    {
        $biz = $this->getOwnedBusiness();
        if (!$biz) return response()->json(['error' => '없음'], 404);
        $days = (int)($req->query('days', 30));
        if (!$biz->is_premium && $days > 7) $days = 7; // Free: 7 days only

        $stats = DB::table('business_stats')
            ->where('business_id', $biz->id)
            ->where('stat_date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('stat_date')
            ->get();

        $totals = [
            'views' => $stats->sum('views'),
            'phone_clicks' => $stats->sum('phone_clicks'),
            'direction_clicks' => $stats->sum('direction_clicks'),
            'website_clicks' => $stats->sum('website_clicks'),
            'bookmark_adds' => $stats->sum('bookmark_adds'),
        ];

        $review_count = DB::table('business_reviews')->where('business_id', $biz->id)->where('is_visible', true)->count();
        $avg_rating = DB::table('business_reviews')->where('business_id', $biz->id)->where('is_visible', true)->avg('rating');

        return response()->json([
            'daily' => $stats,
            'totals' => $totals,
            'review_count' => $review_count,
            'avg_rating' => round($avg_rating, 1),
            'period_days' => $days,
            'is_premium' => (bool)$biz->is_premium,
        ]);
    }
}
