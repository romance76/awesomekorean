<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show($id)
    {
        $user = User::select('id','name','nickname','avatar','bio','city','state','points','allow_friend_request','last_active_at','created_at')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $user]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $user->update($request->only('name','nickname','bio','phone','address1','address2','city','state','zipcode','default_radius','language','allow_friend_request','allow_messages','allow_elder_service'));

        // 우편번호로 도시/주/좌표 자동 채우기
        $zipcode = $request->zipcode ?: $user->zipcode;
        if ($zipcode && (!$user->latitude || $request->has('zipcode'))) {
            try {
                $response = @file_get_contents("https://api.zippopotam.us/us/{$zipcode}");
                if ($response) {
                    $geo = json_decode($response, true);
                    $place = $geo['places'][0] ?? null;
                    if ($place) {
                        $updates = [
                            'latitude' => $place['latitude'],
                            'longitude' => $place['longitude'],
                        ];
                        if (!$user->city) $updates['city'] = $place['place name'];
                        if (!$user->state) $updates['state'] = $place['state abbreviation'];
                        $user->update($updates);
                    }
                }
            } catch (\Exception $e) {}
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar' => '/storage/' . $path]);
        }

        return response()->json(['success' => true, 'data' => $user->fresh()]);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|image|max:2048']);
        $user = auth()->user();
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => '/storage/' . $path]);
        return response()->json(['success' => true, 'data' => $user->fresh(), 'message' => '프로필 사진이 변경되었습니다']);
    }

    public function deleteAccount()
    {
        $user = auth()->user();
        // 관련 데이터 소프트 삭제 (실제로는 is_active = false 처리)
        $user->update(['is_banned' => true, 'ban_reason' => '회원 자발적 탈퇴', 'email' => 'deleted_' . $user->id . '@deleted.com']);
        try { \Tymon\JWTAuth\Facades\JWTAuth::invalidate(\Tymon\JWTAuth\Facades\JWTAuth::getToken()); } catch (\Exception $e) {}
        return response()->json(['success' => true, 'message' => '회원 탈퇴가 완료되었습니다']);
    }

    public function posts($id)
    {
        $posts = \App\Models\Post::where('user_id', $id)->visible()->orderByDesc('created_at')->paginate(20);
        return response()->json(['success' => true, 'data' => $posts]);
    }
}
