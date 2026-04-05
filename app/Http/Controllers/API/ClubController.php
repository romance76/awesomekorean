<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubMember;
use Illuminate\Http\Request;

class ClubController extends Controller
{
    public function index(Request $request)
    {
        $query = Club::with('user:id,name,nickname')
            ->where('is_active', true)
            ->when($request->type, fn($q,$v) => $q->where('type', $v))
            ->when($request->category, fn($q,$v) => $q->where('category', $v))
            ->when($request->search, fn($q,$v) => $q->where('name', 'like', "%{$v}%"))
            ->orderByDesc('member_count');
        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    public function show($id)
    {
        $club = Club::with('user:id,name,nickname')->findOrFail($id);
        $isMember = auth()->check() ? ClubMember::where('club_id',$id)->where('user_id',auth()->id())->exists() : false;
        return response()->json(['success' => true, 'data' => $club, 'is_member' => $isMember]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|max:100', 'category' => 'required', 'type' => 'required|in:online,local']);
        $club = Club::create(array_merge($request->only('name','description','category','type','zipcode'), ['user_id' => auth()->id(), 'member_count' => 1]));
        ClubMember::create(['club_id' => $club->id, 'user_id' => auth()->id(), 'role' => 'admin', 'joined_at' => now()]);
        return response()->json(['success' => true, 'data' => $club], 201);
    }

    public function join($id)
    {
        if (ClubMember::where('club_id',$id)->where('user_id',auth()->id())->exists())
            return response()->json(['success' => false, 'message' => '이미 가입됨'], 400);
        ClubMember::create(['club_id'=>$id,'user_id'=>auth()->id(),'role'=>'member','joined_at'=>now()]);
        Club::find($id)?->increment('member_count');
        return response()->json(['success' => true]);
    }

    public function leave($id)
    {
        ClubMember::where('club_id',$id)->where('user_id',auth()->id())->delete();
        Club::find($id)?->decrement('member_count');
        return response()->json(['success' => true]);
    }

    public function posts($id) { return response()->json(['success'=>true,'data'=>\App\Models\ClubPost::with('user:id,name,nickname')->where('club_id',$id)->orderByDesc('created_at')->paginate(20)]); }
}
