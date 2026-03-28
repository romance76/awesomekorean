<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MatchLike;
use App\Models\MatchProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MatchController extends Controller
{
    // 내 매칭 프로필
    public function myProfile()
    {
        $profile = MatchProfile::where('user_id', Auth::id())->first();
        return response()->json($profile);
    }

    // 프로필 생성/수정
    public function saveProfile(Request $request)
    {
        $data = $request->validate([
            'nickname'      => 'required|string|max:30',
            'gender'        => 'required|in:male,female,other',
            'birth_year'    => 'required|integer|min:1950|max:' . (date('Y') - 18),
            'age_range_min' => 'integer|min:18|max:80',
            'age_range_max' => 'integer|min:18|max:80',
            'region'        => 'nullable|string',
            'bio'           => 'nullable|string|max:500',
            'interests'     => 'nullable|array',
            'visibility'    => 'in:public,matches,hidden',
        ]);

        $profile = MatchProfile::updateOrCreate(
            ['user_id' => Auth::id()],
            $data
        );

        return response()->json($profile);
    }

    // 카드 탐색 (Tinder 스타일)
    public function browse(Request $request)
    {
        $myProfile = MatchProfile::where('user_id', Auth::id())->first();

        // 이미 좋아요 한 사람 제외
        $likedIds = MatchLike::where('user_id', Auth::id())->pluck('liked_user_id');

        $query = MatchProfile::where('user_id', '!=', Auth::id())
            ->whereNotIn('user_id', $likedIds)
            ->where('visibility', 'public')
            ->with('user:id,name,username,avatar,region')
            ->inRandomOrder()
            ->limit(20);

        $profiles = $query->get()->map(function ($p) {
            $p->age = $p->birth_year ? (date('Y') - $p->birth_year) : null;
            return $p;
        });

        return response()->json($profiles);
    }

    // 좋아요
    public function like($userId)
    {
        if ((int)$userId === Auth::id()) {
            return response()->json(['message' => '본인에게 좋아요를 할 수 없습니다.'], 400);
        }

        $existing = MatchLike::where('user_id', Auth::id())
            ->where('liked_user_id', $userId)->first();

        if ($existing) {
            return response()->json(['message' => '이미 좋아요 했습니다.'], 409);
        }

        MatchLike::create([
            'user_id'       => Auth::id(),
            'liked_user_id' => $userId,
            'is_match'      => false,
        ]);

        // 상대방도 나를 좋아요 했는지 확인 → 매칭!
        $mutualLike = MatchLike::where('user_id', $userId)
            ->where('liked_user_id', Auth::id())->first();

        $isMatch = false;
        if ($mutualLike) {
            $isMatch = true;
            MatchLike::where('user_id', Auth::id())->where('liked_user_id', $userId)->update(['is_match' => true]);
            $mutualLike->update(['is_match' => true]);
        }

        return response()->json(['is_match' => $isMatch, 'message' => $isMatch ? '매칭되었습니다!' : '좋아요를 보냈습니다.']);
    }

    // 받은/보낸 좋아요 목록
    public function likes(Request $request)
    {
        $type = $request->query('type', 'received'); // received, sent

        if ($type === 'received') {
            $likes = MatchLike::where('liked_user_id', Auth::id())
                ->with('user:id,name,username,avatar,region')
                ->latest()->get();
        } else {
            $likes = MatchLike::where('user_id', Auth::id())
                ->with('likedUser:id,name,username,avatar,region')
                ->latest()->get();
        }

        return response()->json($likes);
    }

    // 매칭된 목록
    public function matches()
    {
        $matches = MatchLike::where('user_id', Auth::id())
            ->where('is_match', true)
            ->with('likedUser:id,name,username,avatar,region')
            ->latest()
            ->get();

        return response()->json($matches);
    }
}
