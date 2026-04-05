<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\HasDistanceFilter;

class BusinessController extends Controller
{
    use HasDistanceFilter;
    public function index(Request $request)
    {
        $query = Business::where('status', 'active');

        // Category filter - exact match or LIKE for partial matches
        if ($request->category) {
            $query->where(function ($q) use ($request) {
                $q->where('category', $request->category)
                  ->orWhere('category', 'like', '%'.$request->category.'%');
            });
        }

        // Region filter
        if ($request->region) {
            $query->where('region', 'like', '%'.$request->region.'%');
        }

        // State filter - match region to cities in the state
        if ($request->state && !$request->region) {
            $stateCities = [
                'CA'=>['Los Angeles','LA','San Francisco','San Diego'],
                'NY'=>['New York','NY','Flushing'], 'TX'=>['Houston','Dallas'],
                'WA'=>['Seattle'], 'IL'=>['Chicago'], 'GA'=>['Atlanta'],
                'DC'=>['Washington'], 'NV'=>['Las Vegas'], 'FL'=>['Miami'],
                'MA'=>['Boston'], 'HI'=>['Honolulu'], 'CO'=>['Denver'],
                'NJ'=>['Fort Lee'], 'VA'=>['Annandale'], 'OR'=>['Portland'],
                'MN'=>['Minneapolis'], 'MI'=>['Detroit'], 'AZ'=>['Phoenix'],
                'MD'=>['Baltimore'], 'PA'=>['Philadelphia'],
            ];
            $s = strtoupper($request->state);
            if (isset($stateCities[$s])) {
                $query->where(function ($q) use ($stateCities, $s) {
                    foreach ($stateCities[$s] as $city) {
                        $q->orWhere('region', 'like', '%'.$city.'%');
                    }
                });
            }
        }

        // Search filter - search name, name_ko, name_en, description
        if ($request->search) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%'.$keyword.'%')
                  ->orWhere('name_ko', 'like', '%'.$keyword.'%')
                  ->orWhere('name_en', 'like', '%'.$keyword.'%')
                  ->orWhere('description', 'like', '%'.$keyword.'%');
            });
        }

        // Distance filter using Haversine formula
        $userLat = $request->input('lat');
        $userLng = $request->input('lng');
        $radius  = $request->input('radius'); // in miles, 0 = all

        if ($userLat && $userLng && $radius && $radius > 0) {
            $lat = (float) $userLat;
            $lng = (float) $userLng;
            $r   = (float) $radius;

            // Haversine formula for distance in miles
            $haversine = "(3959 * acos(
                cos(radians({$lat}))
                * cos(radians(COALESCE(lat, latitude, 0)))
                * cos(radians(COALESCE(lng, longitude, 0)) - radians({$lng}))
                + sin(radians({$lat}))
                * sin(radians(COALESCE(lat, latitude, 0)))
            ))";

            $query->selectRaw("*, {$haversine} AS distance")
                  ->havingRaw("distance <= ?", [$r])
                  ->orderBy('distance');
        }

        // Default ordering: sponsored first, then by rating
        $query->orderByDesc('is_sponsored')
              ->orderByDesc('rating_avg');

        $perPage = min((int)($request->per_page ?? 20), 50);
        $this->applyDistanceFilter($query, $request, "latitude", "longitude");
        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'category' => 'required|string',
            'address'  => 'required|string',
        ]);
        $business = Business::create(array_merge($request->only(['name','category','description','address','lat','lng','phone','website','hours','region']), ['owner_id' => Auth::id()]));
        return response()->json(['message' => '업소가 등록되었습니다.', 'business' => $business], 201);
    }

    public function show(Business $business)
    {
        return response()->json($business->load(['owner:id,name,username', 'reviews.user:id,name,username,avatar']));
    }

    public function trackStat(Request $request, $id)
    {
        $type = $request->route('type'); // phone, direction, website, view
        $col = match($type) {
            'phone' => 'phone_clicks',
            'direction' => 'direction_clicks',
            'website' => 'website_clicks',
            default => 'views'
        };
        $today = now()->toDateString();
        \DB::table('business_stats')->updateOrInsert(
            ['business_id' => $id, 'stat_date' => $today],
            [$col => \DB::raw($col . ' + 1'), 'updated_at' => now()]
        );
        return response()->json(['ok' => true]);
    }

    public function toggleBookmark(Request $request, $id)
    {
        $userId = Auth::id();
        $key = 'biz_bm_'.$id.'_'.$userId;
        $exists = \DB::table('bookmarks')->where('user_id', $userId)->where('bookmarkable_type', 'App\Models\Business')->where('bookmarkable_id', $id)->exists();
        if ($exists) {
            \DB::table('bookmarks')->where('user_id', $userId)->where('bookmarkable_type', 'App\Models\Business')->where('bookmarkable_id', $id)->delete();
            return response()->json(['bookmarked' => false]);
        } else {
            \DB::table('bookmarks')->insert(['user_id' => $userId, 'bookmarkable_type' => 'App\Models\Business', 'bookmarkable_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            return response()->json(['bookmarked' => true]);
        }
    }

    public function review(Request $request, Business $business)
    {
        $request->validate(['rating' => 'required|integer|min:1|max:5', 'content' => 'nullable|string|max:500']);
        $review = BusinessReview::updateOrCreate(
            ['business_id' => $business->id, 'user_id' => Auth::id()],
            ['rating' => $request->rating, 'content' => $request->content]
        );
        // 평점 업데이트
        $avg = $business->reviews()->avg('rating');
        $business->update(['rating_avg' => $avg, 'review_count' => $business->reviews()->count()]);
        return response()->json(['message' => '리뷰가 등록되었습니다.', 'review' => $review], 201);
    }
}
