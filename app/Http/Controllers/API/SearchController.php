<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\{Post, JobPost, MarketItem, Business, Event, QaPost, RecipePost};
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $q = $request->q;
        if (!$q) return response()->json(['success' => true, 'data' => []]);

        $results = [];
        $results['posts'] = Post::visible()->where('title', 'like', "%{$q}%")->select('id','title','view_count','created_at')->limit(5)->get();
        $results['jobs'] = JobPost::active()->where('title', 'like', "%{$q}%")->select('id','title','company','city','state')->limit(5)->get();
        $results['market'] = MarketItem::where('status','active')->where('title', 'like', "%{$q}%")->select('id','title','price')->limit(5)->get();
        $results['businesses'] = Business::where('name', 'like', "%{$q}%")->select('id','name','category','city')->limit(5)->get();
        $results['events'] = Event::where('title', 'like', "%{$q}%")->select('id','title','start_date','venue')->limit(5)->get();
        $results['qa'] = QaPost::where('title', 'like', "%{$q}%")->select('id','title','bounty_points','is_resolved')->limit(5)->get();
        $results['recipes'] = RecipePost::where('title', 'like', "%{$q}%")->select('id','title','difficulty')->limit(5)->get();

        return response()->json(['success' => true, 'data' => $results]);
    }
}
