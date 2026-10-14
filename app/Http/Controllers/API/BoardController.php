<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Board;

class BoardController extends Controller
{
    public function index() {
        $since = now()->subHours(48);
        $boards = Board::where('is_active', true)->withCount(['posts', 'posts as new_posts_count' => fn ($q) => $q->where('created_at', '>=', $since)->where('is_hidden', false)])
            ->orderBy('sort_order')->get()->each(fn ($b) => $b->setAttribute('has_new', $b->new_posts_count > 0));
        return response()->json(['success' => true, 'data' => $boards]);
    }
}
