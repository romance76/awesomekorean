<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Post;
use App\Models\QaCategory;
use App\Models\QaPost;
use App\Support\AutoSeedPosts;
use Illuminate\Http\Request;

// 커뮤니티 게시판·Q&A 자동 게시 루틴 전용 (같은 ingest.auth 토큰). 규칙은 App\Support\AutoSeedPosts 참고.
// 루틴은 status 로 "지금 올릴 개수(due)"를 보고, 그 수만큼 레딧·쿼라의 한국 관련 새 글(한 달 이내)을 한국어로 옮겨 올린다.
// 글쓴이는 서버가 더미 회원 중에서 돌려가며 고른다.
class SeedIngestController extends Controller
{
    public function status()
    {
        $c = AutoSeedPosts::config();
        $s = AutoSeedPosts::state();
        $block = AutoSeedPosts::blockReason($c);
        $due = [];
        foreach (AutoSeedPosts::KINDS as $k) $due[$k] = $block ? 0 : AutoSeedPosts::due($s, $k);

        return response()->json([
            'success' => true,
            'active' => $block === null,
            'stopped_reason' => $block,            // disabled(관리자가 끔) | member_cap(회원 수 상한 초과)
            'due' => $due,                         // 지금 올려도 되는 개수
            'today' => collect($s['plan'])->map(fn ($p) => ['target' => $p['target'], 'done' => $p['done']]),
            'boards' => Board::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'qa_categories' => QaCategory::orderBy('sort_order')->get(['id', 'name', 'slug']),
            'recent_titles' => [
                'community' => Post::orderByDesc('id')->limit(80)->pluck('title'),
                'qa' => QaPost::orderByDesc('id')->limit(80)->pluck('title'),
            ],
            'rules' => [
                'source' => '레딧·쿼라 글을 바탕으로 했다면 원문 주소(source_url)를 넣는다(한 달 미만, 이미 올린 출처는 409). 레딧을 열 수 없으면 source_url 없이 직접 지어 쓴 글을 올려도 된다 — 미국 사는 한국 사람이 궁금해할 질문·고민·정보·경험담을 자연스러운 구어체로, 이미 있는 제목과 겹치지 않게',
                'topic' => '한국 이야기만이 아니라, 미국 사람이 미국에서 올렸더라도 미국에 사는 한국 사람(교민·유학생·주재원)이 관심 가질 만한 글 — 이민·비자·생활·직장·교육·세금·의료·문화·한미 관계·한국 음식·K콘텐츠·여행 등',
                'style' => '글자 그대로 번역하지 말 것. 내용을 이해한 뒤 한국 사람이 평소에 이야기하듯 자연스러운 말투로 다시 써서(재구성) 올린다. 번역투(~에 의해, ~하는 것이다 등)와 원문 문장 순서를 따라가지 않고, 원문 문장을 그대로 옮기지 않는다. 글쓴이 개인정보·사용자 이름은 빼고, 커뮤니티 글은 편하게 말 거는 투, Q&A는 질문과 답변을 각각 자연스러운 구어체로.',
            ],
        ]);
    }

    public function community(Request $request)
    {
        $data = $request->validate([
            'board_id' => 'required|integer|exists:boards,id',
            'title' => 'required|string|max:200',
            'content' => 'required|string|min:20|max:20000',
            'source_url' => 'nullable|url|max:500',
        ]);
        if ($deny = $this->gate('community', $data['source_url'] ?? null)) return $deny;
        if (!Board::where('id', $data['board_id'])->where('is_active', true)->exists()) {
            return response()->json(['success' => false, 'message' => '쓸 수 없는 게시판이에요. status 의 boards 중에서 고르세요.'], 422);
        }
        $title = trim($data['title']);
        if (Post::where('board_id', $data['board_id'])->where('title', $title)->exists()) {
            return response()->json(['success' => false, 'message' => '같은 제목의 글이 이미 있어요.'], 409);
        }
        $author = $this->author();
        if (!$author) return response()->json(['success' => false, 'message' => '글쓴이로 쓸 더미 회원이 없어요.'], 422);

        $post = AutoSeedPosts::createCommunity($author, (int) $data['board_id'], $title, $this->withSource($data['content'], $data['source_url'] ?? null));
        AutoSeedPosts::record('community', $post->id, $author, $title, $data['source_url'] ?? null, '/community/' . (Board::where('id', $post->board_id)->value('slug') ?: $post->board_id) . '/' . $post->id);
        return response()->json(['success' => true, 'id' => $post->id, 'author_id' => $author], 201);
    }

    public function qa(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|integer|exists:qa_categories,id',
            'title' => 'required|string|max:200',
            'content' => 'required|string|min:10|max:20000',
            'source_url' => 'nullable|url|max:500',
            'answers' => 'nullable|array|max:6',
            'answers.*' => 'string|max:8000',
        ]);
        if ($deny = $this->gate('qa', $data['source_url'] ?? null)) return $deny;
        $title = trim($data['title']);
        if (QaPost::where('title', $title)->exists()) {
            return response()->json(['success' => false, 'message' => '같은 제목의 질문이 이미 있어요.'], 409);
        }
        $author = $this->author();
        if (!$author) return response()->json(['success' => false, 'message' => '글쓴이로 쓸 더미 회원이 없어요.'], 422);

        $post = AutoSeedPosts::createQa($author, (int) $data['category_id'], $title, $this->withSource($data['content'], $data['source_url'] ?? null), $data['answers'] ?? []);
        AutoSeedPosts::record('qa', $post->id, $author, $title, $data['source_url'] ?? null, '/qa/' . $post->id);
        return response()->json(['success' => true, 'id' => $post->id, 'author_id' => $author, 'answers' => (int) $post->answer_count], 201);
    }

    /** 꺼짐·회원 수 상한·슬롯·중복 출처를 서버가 다시 확인 */
    private function gate(string $kind, ?string $sourceUrl)
    {
        if ($why = AutoSeedPosts::blockReason()) {
            return response()->json(['success' => false, 'stopped_reason' => $why, 'message' => $why === 'member_cap' ? '전체 회원 수가 상한을 넘어 자동 게시가 멈췄어요.' : '관리자가 자동 게시를 꺼 두었어요.'], 423);
        }
        if (AutoSeedPosts::due(AutoSeedPosts::state(), $kind) < 1) {
            return response()->json(['success' => false, 'message' => '지금은 올릴 차례가 아니에요(오늘 목표를 채웠거나 아직 시각이 안 됐어요).'], 429);
        }
        if ($sourceUrl && AutoSeedPosts::isSeen($sourceUrl)) {
            return response()->json(['success' => false, 'message' => '이미 올린 출처예요. 다른 글을 고르세요.'], 409);
        }
        return null;
    }

    private function author(): ?int
    {
        return AutoSeedPosts::pickAuthor(AutoSeedPosts::state()['recent_authors'] ?? []);
    }

    /** 번역 글 맨 아래에 원문 출처를 남긴다 */
    private function withSource(string $content, ?string $url): string
    {
        if (!$url) return $content;
        return rtrim($content) . "\n\n출처(원문): " . $url;
    }
}
