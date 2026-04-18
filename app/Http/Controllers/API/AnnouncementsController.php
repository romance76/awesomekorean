<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnnouncementsController extends Controller
{
    /** 공개 — 활성 공지 조회 (프론트 배너용) */
    public function active()
    {
        $rows = Cache::remember('announcements.active', 60, function () {
            return DB::table('announcements')
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                })
                ->orderByDesc('created_at')
                ->get();
        });

        // audience 필터 (클라이언트 측 user 정보 기반)
        $user = auth()->user();
        $filtered = $rows->filter(function ($a) use ($user) {
            $aud = $a->audience ?? 'all';
            if ($aud === 'all') return true;
            if ($aud === 'logged_in') return $user !== null;
            if ($aud === 'guest') return $user === null;
            if (str_starts_with($aud, 'role:') && $user) {
                $role = substr($aud, 5);
                return ($user->role === $role) || (method_exists($user, 'hasRole') && $user->hasRole($role));
            }
            return true;
        })->values();

        return response()->json(['success' => true, 'data' => $filtered]);
    }

    // ─── 관리자 CRUD ───
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => DB::table('announcements')->orderByDesc('created_at')->limit(100)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = auth()->id();
        $data['created_at'] = $data['updated_at'] = now();
        $id = DB::table('announcements')->insertGetId($data);
        Cache::forget('announcements.active');
        return response()->json(['success' => true, 'id' => $id]);
    }

    public function update(Request $request, $id)
    {
        $data = $this->validated($request, true);
        $data['updated_at'] = now();
        DB::table('announcements')->where('id', $id)->update($data);
        Cache::forget('announcements.active');
        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        DB::table('announcements')->where('id', $id)->delete();
        Cache::forget('announcements.active');
        return response()->json(['success' => true]);
    }

    protected function validated(Request $request, bool $partial = false): array
    {
        $rules = [
            'title'       => ($partial ? 'sometimes|' : '') . 'required|string|max:255',
            'message'     => ($partial ? 'sometimes|' : '') . 'required|string',
            'level'       => 'nullable|in:info,success,warning,danger',
            'link_url'    => 'nullable|string|max:500',
            'link_label'  => 'nullable|string|max:100',
            'dismissible' => 'boolean',
            'is_active'   => 'boolean',
            'audience'    => 'nullable|string|max:50',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date',
        ];
        return $request->validate($rules);
    }
}
