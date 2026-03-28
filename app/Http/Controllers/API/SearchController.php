<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\JobPost;
use App\Models\MarketItem;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);

        $q    = $request->q;
        $type = $request->type ?? 'all'; // all | posts | jobs | market | businesses | users

        $result = [];

        if (in_array($type, ['all', 'posts'])) {
            $result['posts'] = Post::with(['user:id,name,username,avatar', 'board:id,name,slug'])
                ->where('status', 'active')
                ->where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                          ->orWhere('content', 'like', "%{$q}%");
                })
                ->orderByDesc('created_at')
                ->limit($type === 'all' ? 5 : 20)
                ->get();
        }

        if (in_array($type, ['all', 'jobs'])) {
            $result['jobs'] = JobPost::with('user:id,name,username')
                ->where('status', 'active')
                ->where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                          ->orWhere('content', 'like', "%{$q}%")
                          ->orWhere('company_name', 'like', "%{$q}%");
                })
                ->orderByDesc('created_at')
                ->limit($type === 'all' ? 3 : 20)
                ->get();
        }

        if (in_array($type, ['all', 'market'])) {
            $result['market'] = MarketItem::with('user:id,name,username')
                ->where('status', 'active')
                ->where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                          ->orWhere('description', 'like', "%{$q}%");
                })
                ->orderByDesc('created_at')
                ->limit($type === 'all' ? 3 : 20)
                ->get();
        }

        if (in_array($type, ['all', 'businesses'])) {
            $result['businesses'] = Business::where('status', 'active')
                ->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                          ->orWhere('description', 'like', "%{$q}%")
                          ->orWhere('category', 'like', "%{$q}%");
                })
                ->orderByDesc('is_sponsored')
                ->limit($type === 'all' ? 3 : 20)
                ->get();
        }

        if (in_array($type, ['all', 'users'])) {
            $result['users'] = User::where('status', 'active')
                ->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                          ->orWhere('username', 'like', "%{$q}%");
                })
                ->select('id', 'name', 'username', 'avatar', 'level', 'region')
                ->limit($type === 'all' ? 3 : 20)
                ->get()
                ->map(function ($u) {
                    $u->avatar = $u->avatar ? asset('storage/' . $u->avatar) : null;
                    return $u;
                });
        }

        return response()->json([
            'query'  => $q,
            'type'   => $type,
            'result' => $result,
        ]);
    }
}
