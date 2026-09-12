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
        $user = User::select('id','name','nickname','avatar','bio','city','state','points','lifetime_points','allow_friend_request','last_active_at','created_at')->findOrFail($id);

        // 가입 기념일 뱃지는 별도 배치job 없이 프로필 조회 시점에 판정
        \App\Services\BadgeService::checkAnniversary($user);

        $earned = \App\Models\UserBadge::where('user_id', $id)->pluck('earned_at', 'badge_key');
        $badges = collect(\App\Services\BadgeService::DEFS)->map(function ($def, $key) use ($earned) {
            return array_merge($def, [
                'key' => $key,
                'earned' => $earned->has($key),
                'earned_at' => $earned->get($key),
            ]);
        })->values();

        $data = $user->toArray();
        $data['grade'] = \App\Support\MemberGrade::forPoints((int) $user->lifetime_points);
        $data['badges'] = $badges;
        $data['is_blocked_by_me'] = auth()->id() && (int) auth()->id() !== (int) $id
            ? \App\Models\UserBlock::isBlocked(auth()->id(), $id)
            : false;

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function update(Request $request)
    {
        // 서버측 검증이 전혀 없어 글자수 제한 없는 값이 그대로 저장되던 문제 수정
        $request->validate([
            'name' => 'nullable|string|max:50',
            'nickname' => 'nullable|string|max:50',
            'bio' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'address1' => 'nullable|string|max:200',
            'address2' => 'nullable|string|max:200',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:50',
            'zipcode' => 'nullable|string|max:10',
            'default_radius' => 'nullable|integer|min:1|max:500',
        ]);

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
            $bonus = (int) (\DB::table('point_settings')->where('key', 'profile_complete_bonus')->value('value') ?? 30);
            $user->addPoints($bonus, '프로필 완성 보너스', 'earn');
            // profile_bonus_given은 $fillable에서 제외돼 있어 update()로는 저장 안 됨
            // (실측 확인: 같은 요청을 반복할 때마다 +30P가 무한 지급되는 버그였음) — forceFill 사용
            $user->forceFill(['profile_bonus_given' => true])->save();
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

        // 기존엔 이메일만 스크램블하고 이름·닉네임·전화·주소·프로필사진 등
        // 개인정보가 전부 그대로 남아있어(탈퇴해도 본인이 쓴 모든 글에 실명·
        // 연락처가 계속 노출) 실질적 탈퇴가 아니었던 문제 수정. 계정 자체와
        // 작성한 글/댓글 등은 그대로 유지하되(사용자 결정 — 다른 이용자에게도
        // 유용한 정보라 삭제하지 않음) User 레코드의 개인정보만 익명화.
        // Issue #6: is_banned/ban_reason 은 forceFill 로 명시 설정
        $user->forceFill(['is_banned' => true, 'ban_reason' => '회원 자발적 탈퇴'])->save();

        if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
            try { \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar); } catch (\Exception $e) {}
        }

        $user->update([
            'name' => '탈퇴한 회원',
            'nickname' => '탈퇴한 회원',
            'email' => 'deleted_' . $user->id . '@deleted.com',
            'avatar' => null,
            'bio' => null,
            'phone' => null,
            'address' => null,
            'address1' => null,
            'address2' => null,
            'city' => null,
            'state' => null,
            'zipcode' => null,
            'latitude' => null,
            'longitude' => null,
            'provider' => null,
            'provider_id' => null,
            'fcm_token' => null,
            'push_platform' => null,
            'allow_friend_request' => false,
            'allow_messages' => false,
            'allow_elder_service' => false,
        ]);

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
