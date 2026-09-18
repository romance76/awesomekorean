<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ExternalHeadline;
use Illuminate\Http\Request;

class ExternalHeadlineController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) ($request->per_page ?? 20), 40);
        return response()->json([
            'success' => true,
            'data' => ExternalHeadline::whereNotNull('image_url')
                ->orderByDesc('published_at')
                ->limit($perPage)
                ->get(),
        ]);
    }
}
