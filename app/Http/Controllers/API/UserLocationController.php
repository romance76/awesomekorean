<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserLocationController extends Controller
{
    /**
     * PUT /api/user/location
     * Update user's latitude/longitude/city/state/zipcode.
     * Users table columns: latitude, longitude, city, state, zipcode
     */
    public function update(Request $request)
    {
        $request->validate([
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'city'      => 'nullable|string|max:100',
            'state'     => 'nullable|string|max:50',
            'zipcode'   => 'nullable|string|max:20',
        ]);

        $user = auth()->user();

        $user->update($request->only(['latitude', 'longitude', 'city', 'state', 'zipcode']));

        return response()->json([
            'success' => true,
            'message' => '위치 정보가 업데이트되었습니다.',
            'data'    => [
                'latitude'  => $user->latitude,
                'longitude' => $user->longitude,
                'city'      => $user->city,
                'state'     => $user->state,
                'zipcode'   => $user->zipcode,
            ],
        ]);
    }
}
