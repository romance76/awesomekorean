<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ClubController extends Controller
{
    /**
     * 모임 목록 (카테고리 필터, 이름 검색)
     */
    public function index(Request $request)
    {
        $query = Club::with('creator:id,name,username,avatar');

        if ($request->category) {
            $query->where('category', $request->category);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->region) {
            $query->where('region', $request->region);
        }

        $clubs = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($clubs);
    }

    /**
     * 모임 상세 (멤버 포함)
     */
    public function show($id)
    {
        $club = Club::with([
            'creator:id,name,username,avatar',
            'members' => function ($q) {
                $q->where('status', 'approved')->with('user:id,name,username,avatar');
            },
        ])->findOrFail($id);

        // 현재 사용자의 멤버 상태 확인
        $myMembership = null;
        if (Auth::check()) {
            $myMembership = ClubMember::where('club_id', $id)
                ->where('user_id', Auth::id())
                ->first();
        }

        return response()->json([
            'club' => $club,
            'my_membership' => $myMembership,
        ]);
    }

    /**
     * 모임 생성
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:100',
            'category'    => 'required|string|max:50',
            'description' => 'nullable|string|max:2000',
            'region'      => 'nullable|string|max:100',
            'cover_image' => 'nullable|image|max:3072',
            'is_approval' => 'nullable',
            'address'     => 'nullable|string|max:255',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $coverPath = null;
        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('clubs', 'public');
        }

        $club = Club::create([
            'creator_id'  => Auth::id(),
            'name'        => $request->name,
            'category'    => $request->category,
            'description' => $request->description,
            'region'      => $request->region,
            'cover_image' => $coverPath,
            'is_approval' => filter_var($request->is_approval, FILTER_VALIDATE_BOOLEAN),
            'member_count' => 1,
            'address'     => $request->address,
            'latitude'    => $request->latitude,
            'longitude'   => $request->longitude,
        ]);

        // 생성자를 owner로 자동 등록
        ClubMember::create([
            'club_id' => $club->id,
            'user_id' => Auth::id(),
            'role'    => 'owner',
            'status'  => 'approved',
        ]);

        return response()->json([
            'message' => '모임이 생성되었습니다.',
            'club'    => $club->load('creator'),
        ], 201);
    }

    /**
     * 모임 가입 (승인제인 경우 pending)
     */
    public function join($id)
    {
        $club = Club::findOrFail($id);

        // 이미 멤버인지 확인
        $existing = ClubMember::where('club_id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if ($existing) {
            if ($existing->status === 'pending') {
                return response()->json(['message' => '이미 가입 신청 중입니다.'], 400);
            }
            return response()->json(['message' => '이미 가입된 모임입니다.'], 400);
        }

        $status = $club->is_approval ? 'pending' : 'approved';

        ClubMember::create([
            'club_id' => $id,
            'user_id' => Auth::id(),
            'role'    => 'member',
            'status'  => $status,
        ]);

        // 승인제가 아닌 경우 member_count 증가
        if (!$club->is_approval) {
            $club->increment('member_count');
        }

        $message = $club->is_approval
            ? '가입 신청이 완료되었습니다. 승인을 기다려주세요.'
            : '모임에 가입되었습니다.';

        return response()->json(['message' => $message, 'status' => $status]);
    }

    /**
     * 모임 탈퇴
     */
    public function leave($id)
    {
        $member = ClubMember::where('club_id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$member) {
            return response()->json(['message' => '가입되지 않은 모임입니다.'], 400);
        }

        if ($member->role === 'owner') {
            return response()->json(['message' => '모임장은 탈퇴할 수 없습니다. 모임장을 양도하거나 모임을 삭제해주세요.'], 400);
        }

        $member->delete();

        $club = Club::find($id);
        if ($club && $club->member_count > 0) {
            $club->decrement('member_count');
        }

        return response()->json(['message' => '모임에서 탈퇴했습니다.']);
    }

    /**
     * 내 모임 목록
     */
    public function myClubs()
    {
        $clubIds = ClubMember::where('user_id', Auth::id())
            ->where('status', 'approved')
            ->pluck('club_id');

        $clubs = Club::with('creator:id,name,username,avatar')
            ->whereIn('id', $clubIds)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($clubs);
    }
}
