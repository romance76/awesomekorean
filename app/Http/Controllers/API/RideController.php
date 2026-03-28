<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use App\Models\PointLog;
use App\Models\Ride;
use App\Models\RideReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RideController extends Controller
{
    // 승객: 라이드 요청
    public function request(Request $request)
    {
        $data = $request->validate([
            'pickup_lat'      => 'required|numeric',
            'pickup_lng'      => 'required|numeric',
            'pickup_address'  => 'required|string',
            'dropoff_lat'     => 'required|numeric',
            'dropoff_lng'     => 'required|numeric',
            'dropoff_address' => 'required|string',
            'payment_method'  => 'in:cash,card,points',
        ]);

        // 간단 요금 계산 (Haversine)
        $distance = $this->haversine(
            $data['pickup_lat'],  $data['pickup_lng'],
            $data['dropoff_lat'], $data['dropoff_lng']
        );
        $fare = max(5.00, 3.00 + ($distance * 1.20));

        $ride = Ride::create([
            ...$data,
            'passenger_id'   => Auth::id(),
            'status'         => 'requesting',
            'distance_miles' => round($distance, 2),
            'estimated_fare' => round($fare, 2),
            'platform_fee'   => round($fare * 0.15, 2),
            'requested_at'   => now(),
        ]);

        return response()->json($ride->load('passenger:id,name,nickname,profile_photo'), 201);
    }

    // 라이드 상세
    public function show($id)
    {
        $ride = Ride::with([
            'passenger:id,name,nickname,profile_photo',
            'driver:id,name,nickname,profile_photo',
        ])->findOrFail($id);

        return response()->json($ride);
    }

    // 내 라이드 이력
    public function history()
    {
        $rides = Ride::where('passenger_id', Auth::id())
            ->orWhere('driver_id', Auth::id())
            ->with(['passenger:id,name,nickname','driver:id,name,nickname'])
            ->latest()
            ->paginate(20);

        return response()->json($rides);
    }

    // 승객: 라이드 취소
    public function cancel($id)
    {
        $ride = Ride::findOrFail($id);
        if ($ride->passenger_id !== Auth::id() && $ride->driver_id !== Auth::id()) {
            return response()->json(['message' => '권한이 없습니다.'], 403);
        }
        if (in_array($ride->status, ['completed','cancelled'])) {
            return response()->json(['message' => '이미 종료된 라이드입니다.'], 400);
        }
        $ride->update(['status' => 'cancelled']);
        return response()->json(['message' => '라이드가 취소되었습니다.']);
    }

    // 드라이버: 주변 요청 목록
    public function nearbyRequests(Request $request)
    {
        $lat = $request->query('lat', 34.0522);
        $lng = $request->query('lng', -118.2437);
        $radius = 10; // miles

        $rides = Ride::where('status', 'requesting')
            ->whereRaw("
                (3959 * acos(
                    cos(radians(?)) * cos(radians(pickup_lat)) *
                    cos(radians(pickup_lng) - radians(?)) +
                    sin(radians(?)) * sin(radians(pickup_lat))
                )) < ?",
                [$lat, $lng, $lat, $radius]
            )
            ->with('passenger:id,name,nickname,profile_photo')
            ->latest()
            ->limit(10)
            ->get();

        return response()->json($rides);
    }

    // 드라이버: 라이드 수락
    public function accept($id)
    {
        $ride = Ride::where('id', $id)->where('status', 'requesting')->firstOrFail();
        $ride->update([
            'driver_id'  => Auth::id(),
            'status'     => 'matched',
            'matched_at' => now(),
        ]);
        return response()->json($ride->fresh(['passenger:id,name,nickname,phone', 'driver:id,name,nickname']));
    }

    // 드라이버: 탑승 시작
    public function start($id)
    {
        $ride = Ride::where('id', $id)->where('driver_id', Auth::id())->where('status','matched')->firstOrFail();
        $ride->update(['status' => 'ongoing', 'started_at' => now()]);
        return response()->json($ride);
    }

    // 드라이버: 라이드 완료
    public function complete($id)
    {
        $ride = Ride::where('id', $id)->where('driver_id', Auth::id())->where('status','ongoing')->firstOrFail();
        $ride->update([
            'status'       => 'completed',
            'final_fare'   => $ride->estimated_fare,
            'completed_at' => now(),
        ]);

        // 드라이버 통계 업데이트
        DriverProfile::where('user_id', Auth::id())->increment('total_rides');
        DriverProfile::where('user_id', Auth::id())->increment(
            'total_earnings', $ride->final_fare * 0.85
        );

        // 승객 포인트
        PointLog::create([
            'user_id'     => $ride->passenger_id,
            'type'        => 'earn',
            'amount'      => 20,
            'description' => '라이드 이용 포인트',
        ]);
        User::where('id', $ride->passenger_id)->increment('points', 20);

        return response()->json(['message' => '라이드 완료!', 'ride' => $ride]);
    }

    // 리뷰 작성
    public function review(Request $request, $id)
    {
        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:300',
        ]);

        $ride = Ride::where('status', 'completed')->findOrFail($id);
        $isPassenger = $ride->passenger_id === Auth::id();
        $isDriver    = $ride->driver_id    === Auth::id();

        if (!$isPassenger && !$isDriver) {
            return response()->json(['message' => '권한이 없습니다.'], 403);
        }

        $reviewedId = $isPassenger ? $ride->driver_id : $ride->passenger_id;

        $review = RideReview::create([
            'ride_id'     => $ride->id,
            'reviewer_id' => Auth::id(),
            'reviewed_id' => $reviewedId,
            'rating'      => $request->rating,
            'comment'     => $request->comment,
        ]);

        // 드라이버 평균 평점 업데이트
        if ($isPassenger && $ride->driver_id) {
            $avg = RideReview::where('reviewed_id', $ride->driver_id)->avg('rating');
            DriverProfile::where('user_id', $ride->driver_id)->update(['rating_avg' => round($avg, 2)]);
        }

        return response()->json($review, 201);
    }

    // Haversine 공식 (miles)
    private function haversine($lat1, $lng1, $lat2, $lng2): float
    {
        $R    = 3959; // Earth radius in miles
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat/2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
