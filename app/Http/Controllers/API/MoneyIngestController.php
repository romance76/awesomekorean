<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// 머니+ 글을 외부 루틴(월·수·금)이 올리는 내부용 API. 정보 글과 같은 ingest.auth 토큰으로 보호한다.
// 글은 운영팀 계정(money-ops)으로 올라가며, 공식 출처(sources 블록의 외부 https 링크)가 없으면 숨김(초안) 상태로 저장된다.
class MoneyIngestController extends Controller
{
    private const OPS_EMAIL = 'money-ops@awesomekorean.local';

    // 루틴이 만들 수 있는 블록 — 화면 캡처(shot)·내려받기(downloads)는 사람이 이미지/파일을 준비해야 해서 제외
    private const BLOCK_TYPES = ['h', 'text', 'callout', 'compare', 'faq', 'sources', 'steps', 'stats', 'checklist', 'bars'];

    /** 카페(slug)와 그 안의 주제(카테고리) 이름 목록 + 최근 글 수 */
    public function meta()
    {
        $cats = DB::table('money_categories')->get(['cafe_id', 'name'])->groupBy('cafe_id');
        $counts = DB::table('money_posts')->select('cafe_id', DB::raw('COUNT(*) c'), DB::raw('MAX(created_at) last_at'))->groupBy('cafe_id')->get()->keyBy('cafe_id');
        $out = DB::table('money_cafes')->orderBy('id')->get()->map(fn($c) => [
            'slug' => $c->slug, 'name' => $c->name,
            'topics' => ($cats[$c->id] ?? collect())->pluck('name')->values(),
            'posts' => (int) ($counts[$c->id]->c ?? 0), 'last_post_at' => $counts[$c->id]->last_at ?? null,
        ]);
        return response()->json(['success' => true, 'data' => $out, 'block_types' => self::BLOCK_TYPES]);
    }

    /** 중복 주제를 피하려고 루틴이 보는 기존 제목 (카페로 좁힐 수 있음) */
    public function titles(Request $request)
    {
        $q = DB::table('money_posts')->join('money_cafes', 'money_cafes.id', '=', 'money_posts.cafe_id')
            ->leftJoin('money_categories', 'money_categories.id', '=', 'money_posts.category_id')
            ->orderByDesc('money_posts.id');
        $slug = (string) $request->query('cafe', '');
        if ($slug !== '') $q->where('money_cafes.slug', $slug);
        $limit = min(500, max(1, (int) $request->query('limit', 200)));
        return response()->json(['success' => true, 'data' => $q->limit($limit)->get([
            'money_posts.id', 'money_posts.title', 'money_cafes.slug as cafe', 'money_categories.name as topic', 'money_posts.is_hidden', 'money_posts.created_at',
        ])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cafe' => 'required|string',
            'topic' => 'required|string',
            'kind' => 'nullable|string|in:info,question,talk',
            'title' => 'required|string|max:200',
            'content' => 'required|string|min:40',
            'blocks' => 'required|array|min:3|max:40',
            'blocks.*.type' => 'required|string|in:' . implode(',', self::BLOCK_TYPES),
            'publish' => 'boolean',
        ]);

        $cafe = DB::table('money_cafes')->where('slug', $data['cafe'])->first();
        if (!$cafe) return response()->json(['success' => false, 'message' => '없는 카페(slug)예요. /meta 로 확인하세요.'], 422);
        $cat = DB::table('money_categories')->where('cafe_id', $cafe->id)->where('name', $data['topic'])->first();
        if (!$cat) return response()->json(['success' => false, 'message' => '그 카페에 없는 주제예요. /meta 의 topics 중에서 고르세요.'], 422);
        $title = trim($data['title']);
        if (DB::table('money_posts')->where('cafe_id', $cafe->id)->where('title', $title)->exists()) {
            return response()->json(['success' => false, 'message' => '같은 제목의 글이 이미 있어요.'], 409);
        }

        // 공식 출처: sources 블록에 awesomekorean 이 아닌 https 링크가 하나라도 있어야 공개한다
        $hasSource = false;
        foreach ($data['blocks'] as $b) {
            if (($b['type'] ?? '') !== 'sources') continue;
            foreach (($b['items'] ?? []) as $it) {
                $url = (string) ($it['url'] ?? '');
                if (preg_match('#^https://(?!(?:www\.)?awesomekorean\.com)#i', $url)) { $hasSource = true; break 2; }
            }
        }
        $publish = (bool) ($data['publish'] ?? false);
        $held = null;
        if ($publish && !$hasSource) { $publish = false; $held = 'no_source_link'; }

        $ops = DB::table('users')->where('email', self::OPS_EMAIL)->first();
        if (!$ops) {
            $u = new User();
            $u->forceFill([
                'name' => '어썸코리안', 'nickname' => '어썸코리안', 'email' => self::OPS_EMAIL,
                'password' => Hash::make(Str::random(48)), 'role' => 'user', 'language' => 'ko',
                'allow_friend_request' => false, 'allow_messages' => false, 'email_verified_at' => now(),
            ])->save();
            $opsId = $u->id;
        } else {
            $opsId = $ops->id;
        }

        $now = now();
        $id = DB::table('money_posts')->insertGetId([
            'cafe_id' => $cafe->id, 'category_id' => $cat->id, 'user_id' => $opsId,
            'kind' => $data['kind'] ?? 'info', 'title' => $title, 'content' => $this->toHtml($data['content']),
            'image' => null,
            'blocks' => json_encode($data['blocks'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'view_count' => 0, 'comment_count' => 0, 'is_hidden' => !$publish, 'is_pinned' => false,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        if ($held) \Log::warning('[머니 글] 출처 링크가 없어 숨김으로 저장: ' . mb_substr($title, 0, 80));

        return response()->json(['success' => true, 'id' => $id, 'published' => $publish, 'held_reason' => $held]);
    }

    private function toHtml(string $text): string
    {
        $out = ''; $inList = false;
        foreach (preg_split('/\R/u', trim($text)) as $line) {
            $line = trim(str_replace('**', '', $line));
            if ($line === '') continue;
            if (str_starts_with($line, '- ')) {
                if (!$inList) { $out .= '<ul>'; $inList = true; }
                $out .= '<li>' . e(substr($line, 2)) . '</li>';
            } else {
                if ($inList) { $out .= '</ul>'; $inList = false; }
                $out .= '<p>' . e($line) . '</p>';
            }
        }
        if ($inList) $out .= '</ul>';
        return $out;
    }
}
