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

        $isBlockedByMe = auth()->id() && (int) auth()->id() !== (int) $id
            ? \App\Models\UserBlock::isBlocked(auth()->id(), $id)
            : false;

        // 다른 회원에게는 "등급(레벨)"만 공개한다 — 포인트·거주지·가입일·소개·뱃지·등급 진행도는 본인/관리자만.
        $viewer = auth()->user();
        $isSelf = $viewer && (int) $viewer->id === (int) $id;
        $isAdmin = $viewer && in_array($viewer->role, ['admin', 'super_admin'], true);
        $grade = \App\Support\MemberGrade::forPoints((int) $user->lifetime_points);

        if (!$isSelf && !$isAdmin) {
            return response()->json(['success' => true, 'data' => [
                'id' => $user->id,
                'name' => $user->display_name,
                'nickname' => $user->nickname,
                'avatar' => $user->avatar,
                'grade_level' => $grade['level'],
                'grade' => ['level' => $grade['level'], 'label' => $grade['label'], 'icon' => $grade['icon']],
                'allow_friend_request' => (bool) $user->allow_friend_request,
                'is_blocked_by_me' => $isBlockedByMe,
            ]]);
        }

        $data = $user->toArray();
        $data['grade'] = $grade;
        $data['badges'] = $badges;
        $data['is_blocked_by_me'] = $isBlockedByMe;

        return response()->json(['success' => true, 'data' => $data]);
    }

    // "큰 글씨로 보기" 설정 저장 (크기|진하게|바꾼 시각) — 다른 기기에서도 같은 크기로 열리게
    public function saveEasyView(Request $request)
    {
        $d = $request->validate([
            'level' => 'required|in:md,lg,xl',
            'hc' => 'required|boolean',
            'ts' => 'required|integer|min:0',
        ]);
        \Illuminate\Support\Facades\DB::table('users')->where('id', $request->user()->id)->update([
            'easy_view_prefs' => $d['level'] . '|' . ($d['hc'] ? 1 : 0) . '|' . $d['ts'],
        ]);
        return response()->json(['success' => true]);
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
            'free_public_room_id' => 'nullable|integer|exists:chat_rooms,id',
        ]);

        // 무료 채팅방은 공개방만 지정 가능 (DM/그룹방 ID를 넣는 것을 방지)
        if ($request->filled('free_public_room_id')) {
            $isPublic = \App\Models\ChatRoom::where('id', $request->free_public_room_id)->where('type', 'public')->exists();
            if (!$isPublic) {
                return response()->json(['success' => false, 'message' => '공개 채팅방만 무료 채팅방으로 지정할 수 있습니다'], 422);
            }
        }

        $user = auth()->user();
        $user->update($request->only('name','nickname','bio','phone','address1','address2','city','state','zipcode','default_radius','language','allow_friend_request','allow_messages','allow_elder_service','free_public_room_id'));

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
        // 다른 회원이 쓴 글 목록은 모아서 보여주지 않는다(본인/관리자만) — 글은 각 게시판에서 그대로 볼 수 있음
        $viewer = auth()->user();
        $allowed = $viewer && ((int) $viewer->id === (int) $id || in_array($viewer->role, ['admin', 'super_admin'], true));
        if (!$allowed) {
            return response()->json(['success' => true, 'data' => []]);
        }
        $posts = \App\Models\Post::with('board:id,slug,name')
            ->where('user_id', $id)->visible()->orderByDesc('created_at')->paginate(20);
        return response()->json(['success' => true, 'data' => $posts]);
    }
}
