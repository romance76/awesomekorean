<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverController extends Controller
{
    // 드라이버 프로필 조회
    public function profile()
    {
        $profile = DriverProfile::firstOrCreate(
            ['user_id' => Auth::id()],
            ['verified' => false, 'is_online' => false]
        );
        return response()->json($profile);
    }

    // 드라이버 등록/업데이트
    public function register(Request $request)
    {
        $data = $request->validate([
            'license_number' => 'required|string',
            'car_make'       => 'required|string',
            'car_model'      => 'required|string',
            'car_year'       => 'required|digits:4',
            'car_color'      => 'required|string',
            'car_plate'      => 'required|string',
        ]);

        $profile = DriverProfile::updateOrCreate(
            ['user_id' => Auth::id()],
            [...$data, 'verified' => false]
        );

        return response()->json([
            'message' => '드라이버 등록 신청이 완료되었습니다. 관리자 검토 후 승인됩니다.',
            'profile' => $profile,
        ]);
    }

    // 온라인/오프라인 전환
    public function toggleOnline(Request $request)
    {
        $profile = DriverProfile::where('user_id', Auth::id())->firstOrFail();

        if (!$profile->verified) {
            return response()->json(['message' => '드라이버 인증이 완료되지 않았습니다.'], 403);
        }

        $isOnline = !$profile->is_online;
        $profile->update(['is_online' => $isOnline]);

        return response()->json([
            'is_online' => $isOnline,
            'message'   => $isOnline ? '온라인 상태입니다. 콜을 받을 수 있습니다.' : '오프라인 전환되었습니다.',
        ]);
    }

    // 위치 업데이트
    public function updateLocation(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        DriverProfile::where('user_id', Auth::id())->update([
            'current_lat'     => $request->lat,
            'current_lng'     => $request->lng,
            'last_location_at'=> now(),
        ]);

        return response()->json(['message' => 'ok']);
    }

    // 주변 온라인 드라이버 목록
    public function nearbyDrivers(Request $request)
    {
        $lat    = $request->query('lat', 34.0522);
        $lng    = $request->query('lng', -118.2437);
        $radius = 15; // miles

        $drivers = DriverProfile::where('is_online', true)
            ->where('verified', true)
            ->whereNotNull('current_lat')
            ->whereRaw("
                (3959 * acos(
                    cos(radians(?)) * cos(radians(current_lat)) *
                    cos(radians(current_lng) - radians(?)) +
                    sin(radians(?)) * sin(radians(current_lat))
                )) < ?",
                [$lat, $lng, $lat, $radius]
            )
            ->with('user:id,name,nickname,profile_photo')
            ->get(['user_id','current_lat','current_lng','car_make','car_model','car_color','rating_avg']);

        return response()->json($drivers);
    }

    // 드라이버 수입 현황
    public function earnings()
    {
        $profile = DriverProfile::where('user_id', Auth::id())->firstOrFail();
        return response()->json([
            'total_earnings' => $profile->total_earnings,
            'total_rides'    => $profile->total_rides,
            'rating_avg'     => $profile->rating_avg,
        ]);
    }
}
