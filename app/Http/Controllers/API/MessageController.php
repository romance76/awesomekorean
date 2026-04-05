<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index() {
        $messages = Message::with('sender:id,name,nickname,avatar')
            ->where('receiver_id', auth()->id())
            ->orderByDesc('created_at')->paginate(20);
        return response()->json(['success'=>true,'data'=>$messages]);
    }

    public function store(Request $request) {
        $request->validate(['receiver_id'=>'required|exists:users,id','content'=>'required']);
        $msg = Message::create(['sender_id'=>auth()->id(),'receiver_id'=>$request->receiver_id,'content'=>$request->content]);
        return response()->json(['success'=>true,'data'=>$msg],201);
    }
}
