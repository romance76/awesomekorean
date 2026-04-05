<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Board;

class BoardController extends Controller
{
    public function index() {
        $boards = Board::where('is_active', true)->withCount('posts')->orderBy('sort_order')->get();
        return response()->json(['success' => true, 'data' => $boards]);
    }
}
