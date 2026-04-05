<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\GroupBuy;
use Illuminate\Http\Request;

class GroupBuyController extends Controller
{
    public function index(Request $request) {
        $query = GroupBuy::with('user:id,name,nickname')
            ->when($request->status, fn($q,$v) => $q->where('status', $v))
            ->when($request->search, fn($q,$v) => $q->where('title', 'like', "%{$v}%"))
            ->orderByDesc('created_at');
        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    public function show($id) {
        $item = GroupBuy::with('user:id,name,nickname,avatar')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $item]);
    }

    public function store(Request $request) {
        $request->validate(['title' => 'required', 'content' => 'required', 'min_participants' => 'required|integer|min:2']);
        $item = GroupBuy::create(array_merge($request->only('title','content','product_url','original_price','group_price','min_participants','max_participants','lat','lng','city','state','deadline'), ['user_id' => auth()->id(), 'current_participants' => 1]));
        return response()->json(['success' => true, 'data' => $item], 201);
    }

    public function join($id) {
        $gb = GroupBuy::findOrFail($id);
        if ($gb->max_participants && $gb->current_participants >= $gb->max_participants) return response()->json(['success'=>false,'message'=>'정원 초과'],400);
        $gb->increment('current_participants');
        return response()->json(['success' => true]);
    }
}
