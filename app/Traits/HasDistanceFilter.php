<?php
namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait HasDistanceFilter
{
    protected function applyDistanceFilter($query, $request, $latCol = "latitude", $lngCol = "longitude")
    {
        if ($request->boolean("national")) {
            return $query;
        }

        $lat = $request->input("lat");
        $lng = $request->input("lng");
        $radius = $request->input("radius");

        if ((!$lat || !$lng) && Auth::check()) {
            $user = Auth::user();
            if ($user->lat && $user->lng) {
                $lat = $user->lat;
                $lng = $user->lng;
                $radius = $radius ?: ($user->default_radius ?: 30);
            }
        }

        if ($lat && $lng && $radius) {
            $radius = (float)$radius;
            $query->whereNotNull($latCol)
                  ->whereNotNull($lngCol)
                  ->whereRaw("(3959 * acos(cos(radians(?)) * cos(radians($latCol)) * cos(radians($lngCol) - radians(?)) + sin(radians(?)) * sin(radians($latCol)))) <= ?", [$lat, $lng, $lat, $radius]);
        }

        return $query;
    }
}
