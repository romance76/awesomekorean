<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InfoPost;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

// 관리자 페이지 "정보 관리" 탭 — '정보' 탭 글 목록/수정/삭제/발행토글 +
// "🚀 지금 자동 생성" 버튼(진행률은 InfoIngestController가 올림).
class AdminInfoController extends Controller
{
    private const SETTING_KEY = 'info_generation_status';

    public function index(Request $request)
    {
        $query = InfoPost::query()
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->when($request->search, fn($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->orderByDesc('created_at');

        return response()->json(['success' => true, 'data' => $query->paginate($request->per_page ?? 20)]);
    }

    public function update(Request $request, $id)
    {
        $post = InfoPost::findOrFail($id);
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'excerpt' => 'sometimes|nullable|string|max:500',
            'body' => 'sometimes|required|string',
            'meta_title' => 'sometimes|nullable|string|max:255',
            'meta_description' => 'sometimes|nullable|string|max:500',
            'cover_image_url' => 'sometimes|nullable|string|max:2048',
            'category' => 'sometimes|required|string|in:' . implode(',', InfoPost::CATEGORIES),
        ]);
        $post->update($data);
        return response()->json(['success' => true, 'data' => $post]);
    }

    public function destroy($id)
    {
        InfoPost::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => '삭제되었습니다']);
    }

    public function toggle($id)
    {
        $post = InfoPost::findOrFail($id);
        $publishing = !$post->is_published;
        $post->update([
            'is_published' => $publishing,
            'published_at' => $publishing ? ($post->published_at ?? now()) : $post->published_at,
        ]);
        return response()->json(['success' => true, 'data' => $post]);
    }

    public function generationStatus()
    {
        $raw = SiteSetting::where('key', self::SETTING_KEY)->value('value');
        return response()->json($raw ? json_decode($raw, true) : ['status' => 'idle']);
    }

    // "🚀 지금 자동 생성" — 상태를 running/completed:0으로 세팅해두면
    // 예약된 체크인(hourly trigger)이 이를 보고 실제 생성을 수행한다.
    public function triggerGeneration(Request $request)
    {
        $status = [
            'status' => 'running',
            'completed' => 0,
            'target' => 10,
            'requested_by' => $request->user()?->nickname ?? $request->user()?->name,
            'started_at' => now()->toIso8601String(),
        ];
        SiteSetting::updateOrCreate(['key' => self::SETTING_KEY], ['value' => json_encode($status), 'group' => 'info']);

        return response()->json(['success' => true, 'data' => $status]);
    }
}
