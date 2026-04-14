<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Resume;
use Illuminate\Http\Request;

class ResumeController extends Controller
{
    // 공개 이력서 목록
    public function index(Request $request)
    {
        $query = Resume::with('user:id,name,nickname,avatar')
            ->active()
            ->public()
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->orderByDesc('updated_at');

        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    // 이력서 상세
    public function show($id)
    {
        $resume = Resume::with('user:id,name,nickname,avatar')->findOrFail($id);
        $resume->increment('view_count');
        return response()->json(['success' => true, 'data' => $resume]);
    }

    // 내 이력서 조회
    public function myResume()
    {
        $resume = Resume::where('user_id', auth()->id())->first();
        return response()->json(['success' => true, 'data' => $resume]);
    }

    // 이력서 생성/업데이트 (유저당 1개)
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|max:100',
            'name' => 'required|max:50',
        ]);

        $resume = Resume::updateOrCreate(
            ['user_id' => auth()->id()],
            $request->only('title', 'name', 'phone', 'email', 'summary', 'category', 'desired_type', 'desired_salary', 'desired_salary_type', 'city', 'state', 'lat', 'lng', 'experience', 'education', 'skills', 'certifications', 'is_public')
        );

        return response()->json(['success' => true, 'data' => $resume, 'message' => '이력서가 저장되었습니다.']);
    }

    // 이력서 수정
    public function update(Request $request, $id)
    {
        $resume = Resume::where('user_id', auth()->id())->findOrFail($id);
        $resume->update($request->only('title', 'name', 'phone', 'email', 'summary', 'category', 'desired_type', 'desired_salary', 'desired_salary_type', 'city', 'state', 'experience', 'education', 'skills', 'certifications', 'is_public', 'is_active'));
        return response()->json(['success' => true, 'data' => $resume]);
    }

    // 이력서 삭제
    public function destroy($id)
    {
        Resume::where('user_id', auth()->id())->findOrFail($id)->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => '이력서가 삭제되었습니다.']);
    }
}
