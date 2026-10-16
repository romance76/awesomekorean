<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ResumeController extends Controller
{
    private const FILE_MIMES = ['application/pdf', 'image/jpeg', 'image/png'];

    private function isAdmin(): bool
    {
        return in_array(auth()->user()->role ?? '', ['admin', 'super_admin'], true);
    }

    // 공개 이력서 목록 (직접 쓴 이력서 중 공개로 둔 것만 — 파일로 올린 이력서는 목록에 나오지 않음)
    public function index(Request $request)
    {
        $query = Resume::with('user:id,name,nickname,avatar,lifetime_points')
            ->active()
            ->public()
            ->where('kind', 'written')
            ->when($request->category, fn($q, $v) => $q->where('category', $v))
            ->orderByDesc('updated_at');

        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    // 이력서 상세 — 공개로 둔 직접 쓴 이력서만 누구나, 그 밖에는 본인·관리자만
    public function show($id)
    {
        $resume = Resume::with('user:id,name,nickname,avatar,lifetime_points')->findOrFail($id);
        $viewer = auth('api')->user();
        $mine = $viewer && ($viewer->id === $resume->user_id || in_array($viewer->role, ['admin', 'super_admin'], true));
        if (!$mine && !($resume->is_public && $resume->is_active && $resume->kind === 'written')) abort(404);
        if (!$mine) $resume->increment('view_count');
        return response()->json(['success' => true, 'data' => $resume]);
    }

    // 내 이력서 목록 (직접 쓴 것 + 올린 것)
    public function myResumes()
    {
        $list = Resume::where('user_id', auth()->id())->active()->orderByDesc('updated_at')->get();
        return response()->json(['success' => true, 'data' => $list, 'max' => Resume::MAX_PER_USER]);
    }

    // (예전 화면 호환) 내 이력서 하나
    public function myResume()
    {
        $resume = Resume::where('user_id', auth()->id())->active()->orderByDesc('updated_at')->first();
        return response()->json(['success' => true, 'data' => $resume]);
    }

    private function writtenRules(): array
    {
        return [
            'title' => 'required|string|max:100',
            'name' => 'required|string|max:50',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:120',
            'summary' => 'nullable|string|max:3000',
            'category' => 'nullable|string|max:30',
            'desired_type' => 'nullable|string|max:20',
            'desired_pay' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:10',
            'experience' => 'nullable|string|max:6000',
            'education' => 'nullable|string|max:4000',
            'skills' => 'nullable|string|max:3000',
            'certifications' => 'nullable|string|max:3000',
            'is_public' => 'nullable|boolean',
        ];
    }

    private function assertRoom(): ?\Illuminate\Http\JsonResponse
    {
        if (Resume::where('user_id', auth()->id())->active()->count() >= Resume::MAX_PER_USER) {
            return response()->json(['success' => false, 'message' => '이력서는 최대 ' . Resume::MAX_PER_USER . '개까지 둘 수 있어요. 안 쓰는 이력서를 지운 뒤 다시 시도해 주세요.'], 422);
        }
        return null;
    }

    // 직접 쓴 이력서 새로 만들기
    public function store(Request $request)
    {
        $data = $request->validate($this->writtenRules());
        if ($res = $this->assertRoom()) return $res;
        $resume = Resume::create($data + ['user_id' => auth()->id(), 'kind' => 'written', 'is_active' => true]);
        return response()->json(['success' => true, 'data' => $resume, 'message' => '이력서가 저장되었습니다.'], 201);
    }

    // 직접 쓴 이력서 고치기 (파일로 올린 이력서는 이름만 바꿀 수 있음)
    public function update(Request $request, $id)
    {
        $resume = Resume::where('user_id', auth()->id())->active()->findOrFail($id);
        if ($resume->isUploaded()) {
            $data = $request->validate(['title' => 'required|string|max:100']);
        } else {
            $data = $request->validate($this->writtenRules());
        }
        $resume->update($data);
        return response()->json(['success' => true, 'data' => $resume->fresh(), 'message' => '이력서가 저장되었습니다.']);
    }

    // 가지고 있는 이력서 파일 올리기 (PDF · JPG · PNG)
    public function upload(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ], [
            'file.mimes' => 'PDF, JPG, PNG 파일만 올릴 수 있어요. Word 파일은 PDF 로 저장해서 올려 주세요.',
            'file.max' => '파일은 10MB 이하여야 해요.',
        ]);
        if ($res = $this->assertRoom()) return $res;

        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        if (!in_array($mime, self::FILE_MIMES, true)) {
            return response()->json(['success' => false, 'message' => 'PDF, JPG, PNG 파일만 올릴 수 있어요. Word 파일은 PDF 로 저장해서 올려 주세요.'], 422);
        }
        $user = auth()->user();
        $path = Storage::disk('local')->putFile('resumes/' . $user->id, $file);   // 공개 폴더가 아닌 비공개 저장소
        $resume = Resume::create([
            'user_id' => $user->id, 'kind' => 'uploaded', 'title' => $request->title,
            'name' => $user->name ?: ($user->nickname ?: '-'),
            'file_path' => $path, 'file_name' => mb_substr($file->getClientOriginalName(), 0, 190), 'file_mime' => $mime, 'file_size' => $file->getSize(),
            'is_public' => false, 'is_active' => true,
        ]);
        return response()->json(['success' => true, 'data' => $resume, 'message' => '이력서 파일이 올라갔어요.'], 201);
    }

    // 파일 내려받기/미리보기 — 올린 본인·관리자만
    public function file($id)
    {
        $resume = Resume::findOrFail($id);
        if ($resume->user_id !== auth()->id() && !$this->isAdmin()) abort(403);
        return $this->streamFile($resume);
    }

    // 구인 공고 주인이 지원서에 붙은 이력서 파일을 열 때
    public function applicationFile($applicationId)
    {
        $app = JobApplication::with('jobPost:id,user_id')->findOrFail($applicationId);
        if (($app->jobPost->user_id ?? null) !== auth()->id() && !$this->isAdmin()) abort(403);
        $resume = Resume::findOrFail($app->resume_id);
        return $this->streamFile($resume);
    }

    private function streamFile(Resume $resume)
    {
        if (!$resume->isUploaded() || !$resume->file_path || !Storage::disk('local')->exists($resume->file_path)) abort(404);
        return Storage::disk('local')->response($resume->file_path, $resume->file_name ?: 'resume', [
            'Content-Type' => $resume->file_mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    // 이력서 지우기 — 이미 낸 지원서에 붙은 파일은 남겨 둔다 (목록에서만 사라짐)
    public function destroy($id)
    {
        Resume::where('user_id', auth()->id())->findOrFail($id)->update(['is_active' => false]);
        return response()->json(['success' => true, 'message' => '이력서가 삭제되었습니다.']);
    }
}
