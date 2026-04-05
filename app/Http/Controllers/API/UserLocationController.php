<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UserLocationController extends Controller
{
    public function get(Request $request)
    {
        $user = $request->user();
        
        if ($user->zip_code) {
            try {
                $response = Http::timeout(3)->get("https://api.zippopotam.us/us/{$user->zip_code}");
                if ($response->ok()) {
                    $place = $response->json()["places"][0];
                    return response()->json([
                        "zip_code" => $user->zip_code,
                        "city" => [
                            "name"  => $place["place name"],
                            "state" => $place["state abbreviation"],
                            "lat"   => (float) $place["latitude"],
                            "lng"   => (float) $place["longitude"],
                        ]
                    ]);
                }
            } catch (\Exception $e) {}
        }

        if ($user->city) {
            return response()->json([
                "city" => [
                    "name"  => $user->city,
                    "state" => $user->state,
                    "lat"   => (float) $user->lat,
                    "lng"   => (float) $user->lng,
                ]
            ]);
        }

        return response()->json(["city" => null]);
    }
}
