<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Friend;
use Illuminate\Http\Request;

class FriendController extends Controller
{
    public function index() {
        $friends = Friend::with('friend:id,name,nickname,avatar')
            ->where('user_id', auth()->id())->where('status','accepted')->get();
        return response()->json(['success'=>true,'data'=>$friends]);
    }

    public function sendRequest($userId) {
        if (Friend::where('user_id',auth()->id())->where('friend_id',$userId)->exists())
            return response()->json(['success'=>false,'message'=>'이미 요청함'],400);
        Friend::create(['user_id'=>auth()->id(),'friend_id'=>$userId,'status'=>'pending']);
        return response()->json(['success'=>true]);
    }

    public function accept($id) {
        $req = Friend::where('friend_id',auth()->id())->where('id',$id)->firstOrFail();
        $req->update(['status'=>'accepted']);
        Friend::create(['user_id'=>auth()->id(),'friend_id'=>$req->user_id,'status'=>'accepted']);
        return response()->json(['success'=>true]);
    }

    public function block($userId) {
        Friend::updateOrCreate(['user_id'=>auth()->id(),'friend_id'=>$userId],['status'=>'blocked']);
        return response()->json(['success'=>true]);
    }

    public function remove($id) {
        Friend::where('user_id',auth()->id())->where('id',$id)->delete();
        return response()->json(['success'=>true]);
    }
}
