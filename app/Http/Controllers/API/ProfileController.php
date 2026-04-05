<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    // 공개 프로필 조회
    public function show(string $username)
    {
        $user = User::where('username', $username)->where('status', 'active')->firstOrFail();

        return response()->json([
            'id'       => $user->id,
            'name'     => $user->name,
            'username' => $user->username,
            'avatar'   => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'bio'      => $user->bio,
            'level'    => $user->level,
            'points'   => $user->points_total,
            'region'   => $user->region,
            'joined'   => $user->created_at->format('Y-m'),
            'post_count'    => $user->posts()->where('status', 'active')->count(),
            'comment_count' => $user->comments()->where('status', 'active')->count(),
        ]);
    }

    // 내 프로필 수정
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name'     => 'sometimes|string|max:50',
            'nickname' => 'nullable|string|max:50',
            'username' => ['sometimes', 'string', 'max:30', 'alpha_dash', Rule::unique('users')->ignore($user->id)],
            'bio'      => 'nullable|string|max:500',
            'phone'    => 'nullable|string|max:20',
            'address'  => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'city'     => 'nullable|string|max:100',
            'state'    => 'nullable|string|max:50',
            'zip_code' => 'nullable|string|max:20',
            'region'   => 'nullable|string|max:100',
            'lang'     => 'nullable|in:ko,en,both',
            'default_radius' => 'nullable|integer|min:0|max:500',
            'avatar'   => 'nullable|image|max:2048',
        ]);

        $data = $request->only(['name', 'nickname', 'username', 'bio', 'phone', 'address', 'address2', 'city', 'state', 'zip_code', 'region', 'lang', 'default_radius']);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) Storage::disk('public')->delete($user->avatar);
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        // Auto-set lat/lng from city
        $cityCoords = [
            'Los Angeles'=>[34.0522,-118.2437],'Koreatown'=>[34.0577,-118.3005],
            'New York'=>[40.7128,-74.0060],'Flushing'=>[40.7654,-73.8318],
            'Fort Lee'=>[40.8509,-73.9701],'Atlanta'=>[33.749,-84.388],
            'Duluth'=>[34.0029,-84.1446],'Suwanee'=>[34.0515,-84.0713],
            'Dallas'=>[32.7767,-96.797],'Houston'=>[29.7604,-95.3698],
            'Chicago'=>[41.8781,-87.6298],'Seattle'=>[47.6062,-122.3321],
            'San Francisco'=>[37.7749,-122.4194],'Annandale'=>[38.8304,-77.1961],
            'Boston'=>[42.3601,-71.0589],'San Diego'=>[32.7157,-117.1611],
            'Irvine'=>[33.6846,-117.8265],'Denver'=>[39.7392,-104.9903],
            'Philadelphia'=>[39.9526,-75.1652],
        ];
        if (!empty($data['city'])) {
            foreach ($cityCoords as $cn => $co) {
                if (stripos($data['city'], $cn) !== false) {
                    $data['lat'] = $co[0]; $data['lng'] = $co[1]; break;
                }
            }
        }

        $user->update($data);

        return response()->json([
            'message' => '프로필이 수정되었습니다.',
            'user'    => $user->fresh()->makeHidden(['password','remember_token'])->toArray(),
        ]);
    }

    // 아바타 전용 업로드
    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|image|max:3072']);
        $user = Auth::user();
        if ($user->avatar) Storage::disk('public')->delete($user->avatar);
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);
        $url = asset('storage/' . $path);
        return response()->json(['avatar' => $url, 'message' => '프로필 사진이 변경되었습니다.']);
    }

    // 비밀번호 변경
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => '현재 비밀번호가 올바르지 않습니다.'], 400);
        }

        $user->update(['password' => $request->password]);
        return response()->json(['message' => '비밀번호가 변경되었습니다.']);
    }

    // 북마크 목록
    public function bookmarks(Request $request)
    {
        $bookmarks = Bookmark::where('user_id', Auth::id())
            ->with('bookmarkable')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($bookmarks);
    }

    // 북마크 추가/제거 (토글)
    public function toggleBookmark(Request $request)
    {
        $request->validate([
            'type' => 'required|in:post,market_item,job_post',
            'id'   => 'required|integer',
        ]);

        $typeMap = [
            'post'        => \App\Models\Post::class,
            'market_item' => \App\Models\MarketItem::class,
            'job_post'    => \App\Models\JobPost::class,
        ];

        $existing = Bookmark::where('user_id', Auth::id())
            ->where('bookmarkable_type', $typeMap[$request->type])
            ->where('bookmarkable_id', $request->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['bookmarked' => false]);
        }

        Bookmark::create([
            'user_id'           => Auth::id(),
            'bookmarkable_type' => $typeMap[$request->type],
            'bookmarkable_id'   => $request->id,
        ]);

        return response()->json(['bookmarked' => true]);
    }

    // 내 게시글 목록
    public function myPosts(Request $request)
    {
        $posts = Auth::user()->posts()
            ->with('board:id,name,slug')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($posts);
    }

    // 내 댓글 목록
    public function myComments(Request $request)
    {
        $comments = Auth::user()->comments()
            ->with('post:id,title,board_id')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($comments);
    }
}
