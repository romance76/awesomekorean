<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show($id)
    {
        $user = User::select('id','name','nickname','avatar','bio','city','state','points','created_at')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $user]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $user->update($request->only('name','nickname','bio','phone','address','city','state','zipcode','language'));

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar' => '/storage/' . $path]);
        }

        return response()->json(['success' => true, 'data' => $user->fresh()]);
    }

    public function posts($id)
    {
        $posts = \App\Models\Post::where('user_id', $id)->visible()->orderByDesc('created_at')->paginate(20);
        return response()->json(['success' => true, 'data' => $posts]);
    }
}
