<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\News;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * 뉴스 "AI 해설" 파이프라인 전용 (ingest.auth 토큰으로 보호).
 *  GET  /api/news-ingest/pending  : 해설할 후보 기사 목록 (아직 해설 안 한 최근 기사)
 *  POST /api/news-ingest/summary  : 한 기사의 해설 저장 (또는 건너뜀 표시)
 * 하루 처리 한도(애틀랜타 날짜 기준)는 서버가 지킨다 — services.news_ai.daily_cap (기본 120).
 */
class NewsIngestController extends Controller
{
    private function dayStart(): Carbon
    {
        return Carbon::now('America/New_York')->startOfDay()->setTimezone('UTC');
    }

    private function doneToday(): int
    {
        return News::where('ai_status', 'done')->where('ai_summarized_at', '>=', $this->dayStart())->count();
    }

    private function cap(): int
    {
        return max(0, (int) config('services.news_ai.daily_cap', 120));
    }

    public function pending(Request $request)
    {
        $limit = max(1, min(100, (int) $request->input('limit', 40)));
        $hours = max(1, min(120, (int) $request->input('hours', 36)));
        $done = $this->doneToday();
        $remaining = max(0, $this->cap() - $done);

        $rows = $remaining <= 0 ? collect() : News::with('category:id,name')
            ->where('is_active', true)
            ->whereNull('ai_status')
            ->whereNotNull('source_url')
            ->where('published_at', '>=', now()->subHours($hours))
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get(['id', 'title', 'summary', 'source', 'source_url', 'category_id', 'view_count', 'published_at'])
            ->map(fn ($n) => [
                'id' => $n->id, 'title' => $n->title, 'source' => $n->source, 'source_url' => $n->source_url,
                'category' => $n->category?->name, 'views' => $n->view_count,
                'published_at' => $n->published_at?->toIso8601String(),
                'summary' => mb_substr(preg_replace('/!\[[^\]]*\]\([^)]*\)/u', '', (string) $n->summary), 0, 200),
            ]);

        return response()->json(['success' => true, 'done_today' => $done, 'daily_cap' => $this->cap(), 'remaining_today' => $remaining, 'count' => $rows->count(), 'data' => $rows->values()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|integer|exists:news,id',
            'status' => 'required|in:done,skipped',
            'ai_summary' => 'required_if:status,done|nullable|string|min:120|max:1800',
        ]);

        $news = News::findOrFail($data['id']);
        if ($news->ai_status) {
            return response()->json(['success' => true, 'message' => '이미 처리된 기사예요.', 'already' => true]);
        }

        if ($data['status'] === 'done') {
            if ($this->cap() - $this->doneToday() <= 0) {
                return response()->json(['success' => false, 'message' => '오늘 처리 한도에 도달했어요.'], 429);
            }
            // 줄바꿈은 살리고 태그/마크다운 링크/연속 공백은 정리 (본문은 일반 글로만 표시)
            $text = strip_tags($data['ai_summary']);
            $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $text);
            $text = preg_replace("/[ \t]+/u", ' ', $text);
            $text = trim(preg_replace("/\n{3,}/u", "\n\n", $text));
            if (mb_strlen($text) < 120) {
                return response()->json(['success' => false, 'message' => '해설이 너무 짧아요.'], 422);
            }
            // 원문(RSS 본문)을 그대로 붙여 넣은 해설은 거절: 40자 이상 같은 문장이 들어 있으면 복사로 본다
            $orig = (string) $news->content;
            foreach (preg_split('/[\n。.!?]+/u', $text) as $sentence) {
                $sentence = trim($sentence);
                if (mb_strlen($sentence) >= 40 && $orig !== '' && mb_strpos($orig, $sentence) !== false) {
                    return response()->json(['success' => false, 'message' => '원문 문장이 그대로 들어 있어요. 자기 말로 다시 정리해 주세요.'], 422);
                }
            }
            $news->update(['ai_summary' => $text, 'ai_status' => 'done', 'ai_summarized_at' => now()]);
        } else {
            $news->update(['ai_status' => 'skipped', 'ai_summarized_at' => now()]);
        }

        return response()->json(['success' => true, 'done_today' => $this->doneToday()]);
    }
}
