<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\JobPost;
use App\Models\Bookmark;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\HasDistanceFilter;
use Illuminate\Support\Facades\DB;

class JobController extends Controller
{
    use HasDistanceFilter;
    public function index(Request $request)
    {
        $query = JobPost::with('user:id,name,username')
            ->where('status', 'active')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');

        if ($request->region) $query->where('region', 'like', '%'.$request->region.'%');
        if ($request->type)   $query->where('job_type', $request->type);
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%'.$request->search.'%')
                  ->orWhere('company_name', 'like', '%'.$request->search.'%');
            });
        }

        $this->applyDistanceFilter($query, $request, "latitude", "longitude");
        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'   => 'required|string|max:200',
            'content' => 'required|string',
        ]);
        $job = JobPost::create(array_merge($request->only(['title','content','company_name','contact_email','contact_phone','region','address','job_type','salary_range','deadline']), ['user_id' => Auth::id()]));
        return response()->json(['message' => '채용공고가 등록되었습니다.', 'job' => $job], 201);
    }

    public function show(JobPost $job)
    {
        $job->increment('view_count');
        $data = $job->load('user:id,name,username')->toArray();

        // 로그인 사용자 좋아요/북마크 여부
        if (Auth::check()) {
            $data['is_liked'] = DB::table('content_likes')
                ->where('user_id', Auth::id())
                ->where('likeable_type', 'job_post')
                ->where('likeable_id', $job->id)
                ->exists();
            $data['is_bookmarked'] = Bookmark::where('user_id', Auth::id())
                ->where('bookmarkable_type', JobPost::class)
                ->where('bookmarkable_id', $job->id)
                ->exists();
        } else {
            $data['is_liked']      = false;
            $data['is_bookmarked'] = false;
        }

        // 좋아요 수 (content_likes 테이블에서 집계)
        $data['like_count'] = DB::table('content_likes')
            ->where('likeable_type', 'job_post')
            ->where('likeable_id', $job->id)
            ->count();

        // 댓글 불러오기
        $data['comments'] = Comment::where('commentable_type', 'job_post')
            ->where('commentable_id', $job->id)
            ->with('user:id,name,username,avatar')
            ->latest()
            ->get();

        return response()->json($data);
    }

    public function destroy(JobPost $job)
    {
        if ($job->user_id !== Auth::id() && !Auth::user()->is_admin) abort(403);
        $job->delete();
        return response()->json(['message' => '삭제되었습니다.']);
    }

    /** 좋아요 토글 */
    public function like($id)
    {
        JobPost::findOrFail($id);
        $userId = Auth::id();

        $existing = DB::table('content_likes')
            ->where('user_id', $userId)
            ->where('likeable_type', 'job_post')
            ->where('likeable_id', $id)
            ->first();

        if ($existing) {
            DB::table('content_likes')->where('id', $existing->id)->delete();
        } else {
            DB::table('content_likes')->insert([
                'user_id'       => $userId,
                'likeable_type' => 'job_post',
                'likeable_id'   => $id,
                'created_at'    => now(),
            ]);
        }

        $likeCount = DB::table('content_likes')
            ->where('likeable_type', 'job_post')
            ->where('likeable_id', $id)
            ->count();

        return response()->json(['liked' => !$existing, 'like_count' => $likeCount]);
    }

    /** 북마크 토글 */
    public function bookmark($id)
    {
        $job = JobPost::findOrFail($id);
        $userId = Auth::id();

        $existing = Bookmark::where('user_id', $userId)
            ->where('bookmarkable_type', JobPost::class)
            ->where('bookmarkable_id', $id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['bookmarked' => false]);
        }

        Bookmark::create([
            'user_id'           => $userId,
            'bookmarkable_type' => JobPost::class,
            'bookmarkable_id'   => $id,
        ]);
        return response()->json(['bookmarked' => true]);
    }

    /** 댓글 작성 */
    public function comment(Request $request, $id)
    {
        JobPost::findOrFail($id);
        $request->validate(['content' => 'required|string|max:2000']);

        $comment = Comment::create([
            'post_id'          => null,
            'commentable_type' => 'job_post',
            'commentable_id'   => $id,
            'user_id'          => Auth::id(),
            'content'          => $request->content,
        ]);

        // 포인트 지급
        $todayCount = Comment::where('user_id', Auth::id())->whereDate('created_at', today())->count();
        if ($todayCount <= 10) {
            Auth::user()->addPoints(5, 'comment_write', 'earn', $comment->id, '댓글 작성');
        }

        return response()->json([
            'message' => '댓글이 등록되었습니다.',
            'comment' => $comment->load('user:id,name,username,avatar'),
        ], 201);
    }

    public function update(Request $request, JobPost $job)
    {
        if ($job->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json(['message' => '수정 권한이 없습니다.'], 403);
        }
        $job->update($request->only(['title','content','company_name','contact_email','contact_phone','region','address','job_type','salary_range','deadline']));
        return response()->json(['message' => '수정되었습니다.', 'job' => $job]);
    }
}
