<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\CompressesUploads;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use CompressesUploads;

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
            // 아바타는 400px 정도면 충분 (큰 이미지 업로드 시 서버 용량 절약)
            $user->update(['avatar' => $this->storeCompressedImage($request->file('avatar'), 'avatars', 400, 85)]);
        }

        // 프로필 완성 보너스 +30P (최초 1회)
        $user = $user->fresh();
        if (!$user->profile_bonus_given && $user->phone && $user->address1 && $user->city && $user->state && $user->zipcode) {
            $bonus = (int) (\DB::table('point_settings')->where('key', 'profile_complete')->value('value') ?? 30);
            $user->addPoints($bonus, '프로필 완성 보너스', 'earn');
            $user->update(['profile_bonus_given' => true]);
        }

        return response()->json(['success' => true, 'data' => $user->fresh()]);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|image|max:10240']); // 10MB
        $user = auth()->user();
        $user->update(['avatar' => $this->storeCompressedImage($request->file('avatar'), 'avatars', 400, 85)]);
        return response()->json(['success' => true, 'data' => $user->fresh(), 'message' => '프로필 사진이 변경되었습니다']);
    }

    public function deleteAccount()
    {
        $user = auth()->user();
        // 관련 데이터 소프트 삭제 (실제로는 is_active = false 처리)
        // Issue #6: is_banned/ban_reason 은 forceFill 로 명시 설정
        $user->forceFill(['is_banned' => true, 'ban_reason' => '회원 자발적 탈퇴'])->save();
        $user->update(['email' => 'deleted_' . $user->id . '@deleted.com']);
        try { \Tymon\JWTAuth\Facades\JWTAuth::invalidate(\Tymon\JWTAuth\Facades\JWTAuth::getToken()); } catch (\Exception $e) {}
        return response()->json(['success' => true, 'message' => '회원 탈퇴가 완료되었습니다']);
    }

    public function posts($id)
    {
        $posts = \App\Models\Post::with('board:id,slug,name')
            ->where('user_id', $id)->visible()->orderByDesc('created_at')->paginate(20);
        return response()->json(['success' => true, 'data' => $posts]);
    }
}
