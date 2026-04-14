<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\JobPost;
use App\Models\JobApplication;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $query = JobPost::with('user:id,name,nickname')
            ->active()
            ->when($request->post_type, fn($q, $v) => $q->where('post_type', $v))
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->when($request->type, fn($q, $v) => $q->where('type', $v))
            ->when($request->search, fn($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->when($request->state, fn($q, $v) => $q->where('state', $v));

        if ($request->lat && $request->lng) {
            $query->nearby($request->lat, $request->lng, $request->radius ?? 50);
        } else {
            $query->orderByDesc('created_at');
        }

        return response()->json(['success' => true, 'data' => $query->paginate($request->per_page ?? 20)]);
    }

    public function show($id)
    {
        $job = JobPost::with('user:id,name,nickname,avatar')->findOrFail($id);
        $job->increment('view_count');
        return response()->json(['success' => true, 'data' => $job]);
    }

    public function store(Request $request)
    {
        $postType = $request->post_type ?? 'hiring';

        $rules = [
            'title' => 'required|max:200',
            'content' => 'required',
            'category' => 'required',
            'type' => 'required|in:full,part,contract',
            'post_type' => 'sometimes|in:hiring,seeking',
        ];

        // 구인은 회사명 필수, 구직은 선택
        if ($postType === 'hiring') {
            $rules['company'] = 'required|max:100';
        } else {
            $rules['company'] = 'nullable|max:100';
        }

        $request->validate($rules);

        $job = JobPost::create(array_merge(
            $request->only('post_type', 'title', 'company', 'content', 'category', 'type', 'salary_min', 'salary_max', 'salary_type', 'lat', 'lng', 'city', 'state', 'zipcode', 'contact_email', 'contact_phone', 'expires_at'),
            ['user_id' => auth()->id()]
        ));

        return response()->json(['success' => true, 'data' => $job], 201);
    }

    public function update(Request $request, $id)
    {
        $job = JobPost::where('user_id', auth()->id())->findOrFail($id);
        $job->update($request->only('post_type', 'title', 'company', 'content', 'category', 'type', 'salary_min', 'salary_max', 'salary_type', 'city', 'state', 'zipcode', 'contact_email', 'contact_phone', 'expires_at', 'is_active'));
        return response()->json(['success' => true, 'data' => $job]);
    }

    public function destroy($id)
    {
        JobPost::where('user_id', auth()->id())->findOrFail($id)->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => '삭제되었습니다']);
    }

    // 이력서로 지원하기
    public function apply(Request $request, $id)
    {
        $job = JobPost::findOrFail($id);

        // 자기 공고에 지원 불가
        if ($job->user_id === auth()->id()) {
            return response()->json(['success' => false, 'message' => '본인 공고에는 지원할 수 없습니다'], 422);
        }

        // 중복 지원 체크
        $exists = JobApplication::where('job_post_id', $id)
            ->where('user_id', auth()->id())
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => '이미 지원한 공고입니다'], 422);
        }

        $application = JobApplication::create([
            'job_post_id' => $id,
            'user_id' => auth()->id(),
            'resume_id' => $request->resume_id,
            'message' => $request->message,
        ]);

        return response()->json(['success' => true, 'data' => $application, 'message' => '지원이 완료되었습니다']);
    }

    // 내 지원 내역
    public function myApplications()
    {
        $apps = JobApplication::with(['jobPost:id,title,company,city,state,category', 'resume:id,title'])
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $apps]);
    }

    // 내 공고에 들어온 지원자 목록
    public function applicants($id)
    {
        $job = JobPost::where('user_id', auth()->id())->findOrFail($id);

        $apps = JobApplication::with(['user:id,name,nickname,avatar', 'resume'])
            ->where('job_post_id', $id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $apps]);
    }
}
