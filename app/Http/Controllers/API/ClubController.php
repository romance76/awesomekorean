<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ClubController extends Controller
{
    /**
     * GET /api/clubs
     * List clubs with type (online/local) filter, category, search
     */
    public function index(Request $request)
    {
        $query = Club::with('user:id,name,nickname,avatar');

        // Online / local filter – clubs use 'type' column
        if ($request->input('filter') === 'online') {
            $query->where('type', 'online');
        } elseif ($request->input('filter') === 'local') {
            $query->where('type', 'local');
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Zipcode filter
        if ($request->filled('zipcode')) {
            $query->where('zipcode', $request->zipcode);
        }

        // Search
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        // Note: clubs table doesn't have lat/lng columns, distance filtering not supported

        return response()->json([
            'success' => true,
            'data'    => $query->orderByDesc('created_at')->paginate(20),
        ]);
    }

    /**
     * GET /api/clubs/{id}
     * Single club with member count, check if current user is member
     */
    public function show($id)
    {
        $club = Club::with([
            'user:id,name,nickname,avatar',
            'members' => function ($q) {
                $q->where('status', 'approved')->with('user:id,name,nickname,avatar');
            },
        ])->findOrFail($id);

        $myMembership = null;
        if (Auth::check()) {
            $myMembership = ClubMember::where('club_id', $id)
                ->where('user_id', Auth::id())
                ->first();
        }

        return response()->json([
            'success'       => true,
            'data'          => [
                'club'          => $club,
                'my_membership' => $myMembership,
            ],
        ]);
    }

    /**
     * POST /api/clubs
     * Create club (creator becomes admin member)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'category'    => 'required|string|max:50',
            'description' => 'nullable|string|max:2000',
            'region'      => 'nullable|string|max:100',
            'cover_image' => 'nullable|image|max:3072',
            'is_approval' => 'nullable|boolean',
            'is_online'   => 'nullable|boolean',
            'zip_code'    => 'nullable|string|max:10',
            'address'     => 'nullable|string|max:255',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
        ]);

        $coverPath = null;
        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('clubs', 'public');
        }

        $isOnline = filter_var($request->is_online, FILTER_VALIDATE_BOOLEAN);
        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $region = $request->region;

        // Geocode zip code for local clubs
        if (!$isOnline && $request->zip_code && !$latitude) {
            $coords = $this->geocodeZipCode($request->zip_code);
            if ($coords) {
                $latitude = $coords['lat'];
                $longitude = $coords['lng'];
                if (!$region && isset($coords['city'])) {
                    $region = $coords['city'] . ($coords['state'] ? ', ' . $coords['state'] : '');
                }
            }
        }

        $club = Club::create([
            'user_id'      => Auth::id(),
            'name'         => $request->name,
            'category'     => $request->category,
            'description'  => $request->description,
            'image'        => $coverPath,
            'type'         => $isOnline ? 'online' : 'local',
            'member_count' => 1,
            'zipcode'      => $request->zip_code,
        ]);

        // Creator becomes owner member
        ClubMember::create([
            'club_id' => $club->id,
            'user_id' => Auth::id(),
            'role'    => 'owner',
            'status'  => 'approved',
        ]);

        return response()->json([
            'success' => true,
            'message' => '동호회가 생성되었습니다.',
            'data'    => $club->load('user:id,name,nickname,avatar'),
        ], 201);
    }

    /**
     * PUT /api/clubs/{id}
     * Update club (admin only)
     */
    public function update(Request $request, $id)
    {
        $club = Club::findOrFail($id);

        // Check ownership: creator or admin member
        $isAdmin = ClubMember::where('club_id', $id)
            ->where('user_id', Auth::id())
            ->whereIn('role', ['owner', 'admin'])
            ->exists();

        if (!$isAdmin && !Auth::user()->is_admin) {
            return response()->json([
                'success' => false,
                'message' => '수정 권한이 없습니다.',
            ], 403);
        }

        $request->validate([
            'name'        => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:2000',
            'region'      => 'nullable|string|max:100',
            'cover_image' => 'nullable|image|max:3072',
        ]);

        if ($request->hasFile('cover_image')) {
            if ($club->image) {
                Storage::disk('public')->delete($club->image);
            }
            $club->image = $request->file('cover_image')->store('clubs', 'public');
        }

        $club->fill($request->only(['name', 'description', 'category']));

        if ($request->has('type')) {
            $club->type = $request->type;
        }

        $club->save();

        return response()->json([
            'success' => true,
            'message' => '수정되었습니다.',
            'data'    => $club,
        ]);
    }

    /**
     * POST /api/clubs/{id}/join
     * Join club, increment member_count
     */
    public function join($id)
    {
        $club = Club::findOrFail($id);

        $existing = ClubMember::where('club_id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if ($existing) {
            $msg = $existing->status === 'pending'
                ? '이미 가입 신청 중입니다.'
                : '이미 가입된 동호회입니다.';
            return response()->json(['success' => false, 'message' => $msg], 400);
        }

        $status = 'approved';

        ClubMember::create([
            'club_id' => $id,
            'user_id' => Auth::id(),
            'role'    => 'member',
            'status'  => $status,
        ]);

        $club->increment('member_count');

        $message = '동호회에 가입되었습니다.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => ['status' => $status],
        ]);
    }

    /**
     * POST /api/clubs/{id}/leave
     * Leave club, decrement member_count
     */
    public function leave($id)
    {
        $member = ClubMember::where('club_id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$member) {
            return response()->json(['success' => false, 'message' => '가입되지 않은 동호회입니다.'], 400);
        }

        if ($member->role === 'owner') {
            return response()->json([
                'success' => false,
                'message' => '방장은 탈퇴할 수 없습니다. 방장을 양도하거나 동호회를 삭제해주세요.',
            ], 400);
        }

        $wasApproved = $member->status === 'approved';
        $member->delete();

        if ($wasApproved) {
            $club = Club::find($id);
            if ($club && $club->member_count > 0) {
                $club->decrement('member_count');
            }
        }

        return response()->json(['success' => true, 'message' => '동호회에서 탈퇴했습니다.']);
    }

    /**
     * GET /api/clubs/{id}/posts
     * List club posts
     */
    public function posts($id)
    {
        Club::findOrFail($id);

        $posts = ClubPost::where('club_id', $id)
            ->with('user:id,name,nickname,avatar')
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $posts]);
    }

    /**
     * POST /api/clubs/{id}/posts
     * Create post in club (members only)
     */
    public function storePost(Request $request, $id)
    {
        Club::findOrFail($id);

        $member = ClubMember::where('club_id', $id)
            ->where('user_id', Auth::id())
            ->where('status', 'approved')
            ->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => '동호회 회원만 게시글을 작성할 수 있습니다.',
            ], 403);
        }

        $request->validate([
            'title'   => 'required|string|max:200',
            'content' => 'required|string|max:10000',
        ]);

        $post = ClubPost::create([
            'club_id' => $id,
            'user_id' => Auth::id(),
            'title'   => $request->title,
            'content' => $request->content,
        ]);

        return response()->json([
            'success' => true,
            'message' => '게시글이 등록되었습니다.',
            'data'    => $post->load('user:id,name,nickname,avatar'),
        ], 201);
    }

    /**
     * GET /api/clubs/my
     * List user's clubs
     */
    public function myClubs()
    {
        $clubIds = ClubMember::where('user_id', Auth::id())
            ->where('status', 'approved')
            ->pluck('club_id');

        $clubs = Club::with('user:id,name,nickname,avatar')
            ->whereIn('id', $clubIds)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $clubs]);
    }

    /**
     * GET /api/clubs/{id}/members
     */
    public function getMembers($id)
    {
        Club::findOrFail($id);

        $members = DB::table('club_members')
            ->join('users', 'users.id', '=', 'club_members.user_id')
            ->where('club_members.club_id', $id)
            ->where('club_members.status', 'approved')
            ->select(
                'users.id', 'users.name', 'users.nickname', 'users.avatar',
                'club_members.role', 'club_members.status',
                'club_members.created_at as joined_at'
            )
            ->orderByRaw("FIELD(club_members.role, 'owner', 'admin', 'member')")
            ->orderBy('club_members.created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $members]);
    }

    /**
     * POST /api/clubs/{clubId}/approve/{userId}
     */
    public function approveMember($clubId, $userId)
    {
        $club = Club::findOrFail($clubId);

        if ($club->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json(['success' => false, 'message' => '방장만 승인할 수 있습니다.'], 403);
        }

        $updated = DB::table('club_members')
            ->where('club_id', $clubId)
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->update(['status' => 'approved', 'updated_at' => now()]);

        if (!$updated) {
            return response()->json(['success' => false, 'message' => '해당 신청을 찾을 수 없습니다.'], 404);
        }

        $club->increment('member_count');

        return response()->json(['success' => true, 'message' => '승인되었습니다.']);
    }

    /**
     * POST /api/clubs/{clubId}/reject/{userId}
     */
    public function rejectMember($clubId, $userId)
    {
        $club = Club::findOrFail($clubId);

        if ($club->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json(['success' => false, 'message' => '방장만 거절할 수 있습니다.'], 403);
        }

        DB::table('club_members')
            ->where('club_id', $clubId)
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->delete();

        return response()->json(['success' => true, 'message' => '거절되었습니다.']);
    }

    /**
     * DELETE /api/clubs/{id}
     */
    public function destroy($id)
    {
        $club = Club::findOrFail($id);

        if ($club->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json(['success' => false, 'message' => '방장만 삭제할 수 있습니다.'], 403);
        }

        ClubMember::where('club_id', $id)->delete();
        ClubPost::where('club_id', $id)->delete();

        if ($club->image) {
            Storage::disk('public')->delete($club->image);
        }

        $club->delete();

        return response()->json(['success' => true, 'message' => '동호회가 삭제되었습니다.']);
    }

    /**
     * POST /api/clubs/geocode-zip
     */
    public function geocodeZip(Request $request)
    {
        $request->validate(['zip_code' => 'required|string|max:10']);
        $result = $this->geocodeZipCode($request->zip_code);

        if ($result) {
            return response()->json(['success' => true, 'data' => $result]);
        }

        return response()->json(['success' => false, 'message' => '유효하지 않은 집코드입니다.'], 422);
    }

    /**
     * Geocode zip code using Zippopotam.us
     */
    private function geocodeZipCode(string $zipCode): ?array
    {
        try {
            $response = Http::timeout(5)->get("https://api.zippopotam.us/us/{$zipCode}");
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'lat'   => (float) $data['places'][0]['latitude'],
                    'lng'   => (float) $data['places'][0]['longitude'],
                    'city'  => $data['places'][0]['place name'] ?? null,
                    'state' => $data['places'][0]['state abbreviation'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            \Log::warning('Zip code geocoding failed: ' . $e->getMessage());
        }
        return null;
    }
}
