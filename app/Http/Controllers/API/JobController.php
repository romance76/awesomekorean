<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\JobPost;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $query = JobPost::with('user:id,name,nickname')
            ->active()
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
        $request->validate([
            'title' => 'required|max:200',
            'company' => 'required|max:100',
            'content' => 'required',
            'category' => 'required',
            'type' => 'required|in:full,part,contract',
        ]);

        $job = JobPost::create(array_merge(
            $request->only('title', 'company', 'content', 'category', 'type', 'salary_min', 'salary_max', 'salary_type', 'lat', 'lng', 'city', 'state', 'zipcode', 'contact_email', 'contact_phone', 'expires_at'),
            ['user_id' => auth()->id()]
        ));

        return response()->json(['success' => true, 'data' => $job], 201);
    }

    public function update(Request $request, $id)
    {
        $job = JobPost::where('user_id', auth()->id())->findOrFail($id);
        $job->update($request->only('title', 'company', 'content', 'category', 'type', 'salary_min', 'salary_max', 'salary_type', 'contact_email', 'contact_phone', 'is_active'));
        return response()->json(['success' => true, 'data' => $job]);
    }

    public function destroy($id)
    {
        JobPost::where('user_id', auth()->id())->findOrFail($id)->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => '삭제되었습니다']);
    }
}
