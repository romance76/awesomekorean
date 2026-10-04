<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InfoKeyword;
use App\Models\InfoPost;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// info.awesomekorean.com의 ingest API와 같은 계약을 유지하는 내부용 엔드포인트.
// 자동 콘텐츠 생성 루틴(외부 AI 파이프라인)이 Authorization: Bearer <토큰>으로 호출한다.
// 일반 사용자/관리자 인증과는 완전히 분리된 ingest.auth 미들웨어로 보호됨.
class InfoIngestController extends Controller
{
    private const SETTING_KEY = 'info_generation_status';

    public function keywords()
    {
        return response()->json(InfoKeyword::orderBy('id')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'body' => 'required|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'keyword_term' => 'nullable|string|max:255',
            'cover_image_url' => 'nullable|string|max:2048',
            'category' => 'required|string|in:' . implode(',', InfoPost::CATEGORIES),
            'publish' => 'boolean',
        ]);

        $slug = $this->slugify($data['title']);
        if (!$slug) $slug = Str::slug(Str::random(10));
        $base = $slug;
        $i = 2;
        while (InfoPost::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        $publish = (bool) ($data['publish'] ?? false);

        $post = InfoPost::create([
            'title' => $data['title'],
            'slug' => $slug,
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'meta_title' => $data['meta_title'] ?? $data['title'],
            'meta_description' => $data['meta_description'] ?? ($data['excerpt'] ?? null),
            'keyword_term' => $data['keyword_term'] ?? null,
            'cover_image_url' => $data['cover_image_url'] ?? null,
            'category' => $data['category'],
            'is_published' => $publish,
            'published_at' => $publish ? now() : null,
        ]);

        if (!empty($data['keyword_term'])) {
            InfoKeyword::where('term', $data['keyword_term'])->update(['status' => 'used']);
        }

        if ($publish) {
            $this->bumpProgress();
        }

        return response()->json(['success' => true, 'id' => $post->id, 'slug' => $post->slug], 201);
    }

    public function generationStatus()
    {
        return response()->json($this->readStatus());
    }

    public function generationComplete(Request $request)
    {
        $status = $this->readStatus();
        $status['status'] = 'done';
        $status['message'] = $request->input('message');
        $status['completed_at'] = now()->toIso8601String();
        $this->writeStatus($status);

        return response()->json(['success' => true]);
    }

    private function readStatus(): array
    {
        $raw = SiteSetting::where('key', self::SETTING_KEY)->value('value');
        $status = $raw ? json_decode($raw, true) : null;

        return $status ?: ['status' => 'idle'];
    }

    private function writeStatus(array $status): void
    {
        SiteSetting::updateOrCreate(['key' => self::SETTING_KEY], ['value' => json_encode($status), 'group' => 'info']);
    }

    private function bumpProgress(): void
    {
        $status = $this->readStatus();
        if (($status['status'] ?? null) !== 'running') return;

        $status['completed'] = ($status['completed'] ?? 0) + 1;
        $this->writeStatus($status);
    }

    // Str::slug()는 ASCII만 남기고 한글을 전부 제거해버려서(구 info.awesomekorean.com
    // 사이트맵에서 확인된 "/articles/고금리-저축예금-HYSA-vs-..." 형태의 URL과 달리
    // 빈 슬러그가 되어버림) — 공백을 하이픈으로 바꾸고 한글/영문/숫자/하이픈만 남긴다.
    private function slugify(string $title): string
    {
        $slug = preg_replace('/\s+/u', '-', trim($title));
        $slug = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}
