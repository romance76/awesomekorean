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

        // 공식 출처 링크(외부 주소, 사진 라이선스 링크 제외)가 하나도 없는 글은 바로 공개하지 않고 초안으로 저장한다.
        // (구글 애드센스 "가치 낮은 콘텐츠" 대응 — 기존 글 53편에 출처를 붙인 뒤 같은 상태가 다시 쌓이지 않게. 관리자 > 정보 관리에서 출처를 붙이고 공개)
        $heldNoSource = false;
        if ($publish && !preg_match('#<a\s[^>]*href=["\']https?://(?!(?:www\.)?awesomekorean\.com|creativecommons\.org)#i', $data['body'])) {
            $publish = false;
            $heldNoSource = true;
            \Log::warning('[정보 글] 출처 링크가 없어 초안으로 저장: ' . mb_substr($data['title'], 0, 80));
        }

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

        return response()->json([
            'success' => true, 'id' => $post->id, 'slug' => $post->slug,
            'published' => (bool) $post->is_published,
            'held_reason' => $heldNoSource ? 'no_source_link' : null,   // 'no_source_link' 이면 초안으로 저장됨 — 공식 출처 링크를 본문에 넣어 다시 보내거나 관리자에서 붙여 공개
        ], 201);
    }

    /** 중복 주제를 피하려고 루틴이 기존 글 제목을 보는 목록 (공개·초안 모두, 카테고리로 좁힐 수 있음) */
    public function titles(Request $request)
    {
        $q = InfoPost::query()->orderByDesc('id');
        $cat = (string) $request->query('category', '');
        if ($cat !== '' && in_array($cat, InfoPost::CATEGORIES, true)) $q->where('category', $cat);
        $limit = min(400, max(1, (int) $request->query('limit', 150)));
        return response()->json(['success' => true, 'data' => $q->limit($limit)->get(['id', 'title', 'slug', 'category', 'is_published', 'published_at'])]);
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
