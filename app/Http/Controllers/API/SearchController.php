<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Event;
use App\Models\JobPost;
use App\Models\MarketItem;
use App\Models\Post;
use App\Models\QaPost;
use App\Models\RecipePost;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * GET /api/search
     * Search across posts, jobs, market, businesses, events, qa, recipes.
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $q    = $request->q;
        $type = $request->type ?? 'all';
        $limit = $type === 'all' ? 5 : 20;

        $result = [];

        if (in_array($type, ['all', 'posts'])) {
            $result['posts'] = Post::with(['user:id,name,nickname,avatar', 'board:id,name,slug'])
                ->where('is_hidden', false)
                ->where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                          ->orWhere('content', 'like', "%{$q}%");
                })
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        }

        if (in_array($type, ['all', 'jobs'])) {
            $result['jobs'] = JobPost::with('user:id,name,nickname')
                ->where('is_active', true)
                ->where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                          ->orWhere('content', 'like', "%{$q}%")
                          ->orWhere('company', 'like', "%{$q}%");
                })
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        }

        if (in_array($type, ['all', 'market'])) {
            $result['market'] = MarketItem::with('user:id,name,nickname')
                ->whereIn('status', ['active', 'reserved'])
                ->where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                          ->orWhere('content', 'like', "%{$q}%");
                })
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        }

        if (in_array($type, ['all', 'businesses'])) {
            $result['businesses'] = Business::where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                          ->orWhere('description', 'like', "%{$q}%")
                          ->orWhere('category', 'like', "%{$q}%");
                })
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        }

        if (in_array($type, ['all', 'events'])) {
            $result['events'] = Event::where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                          ->orWhere('description', 'like', "%{$q}%");
                })
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        }

        if (in_array($type, ['all', 'qa'])) {
            $result['qa'] = QaPost::with('user:id,name,nickname')
                ->where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                          ->orWhere('content', 'like', "%{$q}%");
                })
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        }

        if (in_array($type, ['all', 'recipes'])) {
            $result['recipes'] = RecipePost::with('user:id,name,nickname')
                ->where('is_hidden', false)
                ->where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%");
                })
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'query'   => $q,
                'type'    => $type,
                'results' => $result,
            ],
        ]);
    }
}
