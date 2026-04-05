<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RealEstateListing;
use App\Models\Bookmark;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\HasDistanceFilter;
use Illuminate\Support\Facades\Storage;

class RealEstateController extends Controller
{
    use HasDistanceFilter;
    public function index(Request $request)
    {
        $query = RealEstateListing::with('user:id,name,username')
            ->where('status', 'active')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');

        if ($request->type)   $query->where('type', $request->type);
        if ($request->region) $query->where('region', 'like', '%'.$request->region.'%');
        if ($request->search) {
            $q = $request->search;
            $query->where(function($qb) use ($q) {
                $qb->where('title',   'like', "%{$q}%")
                   ->orWhere('address','like', "%{$q}%")
                   ->orWhere('region', 'like', "%{$q}%");
            });
        }

        $this->applyDistanceFilter($query, $request, "latitude", "longitude");
        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:200',
            'type'        => 'required|in:렌트,매매,룸메이트,상가,전세',
            'description' => 'nullable|string',
            'address'     => 'required|string',
        ]);

        $photos = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $file) {
                $path = $file->store('realestate', 'public');
                $photos[] = Storage::url($path);
            }
        }

        $listing = RealEstateListing::create(array_merge(
            $request->only([
                'title','description','type','price','deposit',
                'address','region','latitude','longitude',
                'bedrooms','bathrooms','sqft',
                'move_in_date','pet_policy','phone','email',
            ]),
            ['user_id' => Auth::id(), 'photos' => $photos]
        ));

        return response()->json(['message' => '매물이 등록되었습니다.', 'listing' => $listing], 201);
    }

    public function show($id)
    {
        $listing = RealEstateListing::with('user:id,name,username')->findOrFail($id);
        $listing->increment('view_count');
        $data = $listing->toArray();

        if (Auth::check()) {
            $data['is_bookmarked'] = Bookmark::where('user_id', Auth::id())
                ->where('bookmarkable_type', RealEstateListing::class)
                ->where('bookmarkable_id', $id)->exists();
        } else {
            $data['is_bookmarked'] = false;
        }

        $data['comments'] = Comment::where('commentable_type', 'real_estate_listing')
            ->where('commentable_id', $id)
            ->with('user:id,name,username,avatar')
            ->latest()->get();

        return response()->json($data);
    }

    public function update(Request $request, $id)
    {
        $listing = RealEstateListing::findOrFail($id);
        if ($listing->user_id !== Auth::id() && !Auth::user()->is_admin) abort(403);
        $listing->update($request->only([
            'title','description','type','price','deposit',
            'address','region','bedrooms','bathrooms','sqft',
            'move_in_date','pet_policy','phone','email','status',
        ]));
        return response()->json(['message' => '수정되었습니다.', 'listing' => $listing]);
    }

    public function destroy($id)
    {
        $listing = RealEstateListing::findOrFail($id);
        if ($listing->user_id !== Auth::id() && !Auth::user()->is_admin) abort(403);
        $listing->delete();
        return response()->json(['message' => '삭제되었습니다.']);
    }

public function getComments($id)    {        $comments = Comment::where("commentable_type", "real_estate_listing")            ->where("commentable_id", $id)            ->with("user:id,name,username,avatar")            ->latest()->get();        return response()->json($comments);    }
    public function comment(Request $request, $id)
    {
        RealEstateListing::findOrFail($id);
        $request->validate(['content' => 'required|string|max:2000']);

        $comment = Comment::create([
            'post_id'          => null,
            'commentable_type' => 'real_estate_listing',
            'commentable_id'   => $id,
            'user_id'          => Auth::id(),
            'content'          => $request->content,
        ]);

        return response()->json([
            'message' => '댓글이 등록되었습니다.',
            'comment' => $comment->load('user:id,name,username,avatar'),
        ], 201);
    }

    public function bookmark($id)
    {
        RealEstateListing::findOrFail($id);
        $userId = Auth::id();
        $existing = Bookmark::where('user_id', $userId)
            ->where('bookmarkable_type', RealEstateListing::class)
            ->where('bookmarkable_id', $id)->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['bookmarked' => false]);
        }

        Bookmark::create([
            'user_id'           => $userId,
            'bookmarkable_type' => RealEstateListing::class,
            'bookmarkable_id'   => $id,
        ]);
        return response()->json(['bookmarked' => true]);
    }

    /* ─── Admin methods ───────────────────────────────────── */
    public function adminIndex(Request $request)
    {
        $q = RealEstateListing::with('user:id,name')->latest();
        if ($request->search) $q->where('title', 'like', '%'.$request->search.'%');
        if ($request->type)   $q->where('type', $request->type);
        if ($request->status) $q->where('status', $request->status);
        return response()->json($q->paginate(25));
    }

    public function adminDestroy($id)
    {
        RealEstateListing::findOrFail($id)->delete();
        return response()->json(['message' => '매물이 삭제되었습니다.']);
    }

    public function adminToggle($id)
    {
        $listing = RealEstateListing::findOrFail($id);
        $listing->status = ($listing->status === 'active') ? 'closed' : 'active';
        $listing->save();
        return response()->json(['message' => '상태가 변경되었습니다.', 'status' => $listing->status]);
    }
}

