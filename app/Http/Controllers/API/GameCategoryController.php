<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class GameCategoryController extends Controller
{
    public function index()
    {
        $categories = DB::table('game_categories')->where('is_active', 1)->orderBy('sort_order')->get();
        foreach ($categories as $cat) {
            $cat->games = DB::table('games')->where('category_id', $cat->id)->where('is_active', 1)->orderBy('sort_order')->get();
        }
        return response()->json($categories);
    }

    public function leaderboard()
    {
        $leaders = DB::table('user_wallets as w')
            ->join('users as u', 'w.user_id', '=', 'u.id')
            ->leftJoin(DB::raw('(SELECT user_id, COUNT(*) as play_count FROM game_sessions GROUP BY user_id) as gs'), 'gs.user_id', '=', 'u.id')
            ->select('u.id', 'u.nickname', 'u.nickname', 'w.coin_balance',
                     'w.lifetime_earned as total_reward',
                     DB::raw('COALESCE(gs.play_count, 0) as play_count'))
            ->orderByDesc('w.lifetime_earned')->limit(20)->get();
        return response()->json($leaders);
    }
}
