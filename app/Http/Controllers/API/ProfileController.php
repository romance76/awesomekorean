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
            'username' => ['sometimes', 'string', 'max:30', 'alpha_dash', Rule::unique('users')->ignore($user->id)],
            'bio'      => 'nullable|string|max:300',
            'region'   => 'nullable|string|max:100',
            'lang'     => 'nullable|in:ko,en',
            'avatar'   => 'nullable|image|max:2048',
        ]);

        $data = $request->only(['name', 'username', 'bio', 'region', 'lang']);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) Storage::disk('public')->delete($user->avatar);
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        return response()->json([
            'message' => '프로필이 수정되었습니다.',
            'user'    => [
                'id'       => $user->id,
                'name'     => $user->name,
                'username' => $user->username,
                'avatar'   => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'bio'      => $user->bio,
                'region'   => $user->region,
                'lang'     => $user->lang,
            ],
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
