<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MoneyCafe;
use App\Models\MoneyGuide;
use App\Models\MoneyPost;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;

/**
 * 관리자 › MONEY 관리 — 카페(이름·아이콘·한 줄·대문·순서·켜기), 머니 가이드(쓰기·공개), 카페 글(숨김·복구).
 * 지우는 기능은 두지 않는다(카페는 끄기, 가이드는 비공개, 글은 숨김). 권한: 관리자 이상(AdminTier 기본).
 */
class AdminMoneyController extends Controller
{
    public function cafes()
    {
        $rows = MoneyCafe::orderBy('sort_order')
            ->withCount(['posts as post_count', 'posts as hidden_count' => fn ($q) => $q->where('is_hidden', true)])
            ->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function saveCafe(Request $request, $id = null)
    {
        $cafe = $id ? MoneyCafe::findOrFail($id) : new MoneyCafe();
        $data = $request->validate([
            'slug' => [$id ? 'sometimes' : 'required', 'string', 'max:60', 'regex:/^[a-z0-9\-]+$/', 'unique:money_cafes,slug' . ($id ? ',' . $id : '')],
            'name' => 'required|string|max:60',
            'icon' => 'nullable|string|max:16',
            'summary' => 'nullable|string|max:200',
            'intro' => 'nullable|string|max:60000',
            'sort_order' => 'nullable|integer|min:0|max:10000',
            'is_active' => 'nullable|boolean',
            'manager_user_id' => 'nullable|integer|exists:users,id',
        ], [
            'slug.regex' => '주소는 영문 소문자·숫자·- 만 쓸 수 있어요.', 'slug.unique' => '이미 쓰고 있는 주소예요.',
            'name.required' => '카페 이름을 적어 주세요.', 'manager_user_id.exists' => '없는 회원 번호예요.',
        ]);
        if (array_key_exists('intro', $data)) $data['intro'] = HtmlSanitizer::clean($data['intro']);
        if (!$id && !isset($data['sort_order'])) $data['sort_order'] = (int) MoneyCafe::max('sort_order') + 1;
        $cafe->fill($data);
        if ($id && $request->has('slug')) $cafe->slug = $data['slug'];
        $cafe->save();
        return response()->json(['success' => true, 'message' => '저장했어요', 'data' => $cafe]);
    }

    public function guides()
    {
        return response()->json(['success' => true, 'data' => MoneyGuide::orderBy('sort_order')->get()]);
    }

    public function saveGuide(Request $request, $id = null)
    {
        $g = $id ? MoneyGuide::findOrFail($id) : new MoneyGuide();
        $data = $request->validate([
            'slug' => [$id ? 'sometimes' : 'required', 'string', 'max:80', 'regex:/^[a-z0-9\-]+$/', 'unique:money_guides,slug' . ($id ? ',' . $id : '')],
            'title' => 'required|string|max:200',
            'icon' => 'nullable|string|max:16',
            'summary' => 'nullable|string|max:300',
            'content' => 'nullable|string|max:200000',
            'is_published' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:10000',
        ], [
            'slug.regex' => '주소는 영문 소문자·숫자·- 만 쓸 수 있어요.', 'slug.unique' => '이미 쓰고 있는 주소예요.', 'title.required' => '제목을 적어 주세요.',
        ]);
        if (array_key_exists('content', $data)) $data['content'] = HtmlSanitizer::clean($data['content']);
        if (!$id && !isset($data['sort_order'])) $data['sort_order'] = (int) MoneyGuide::max('sort_order') + 1;
        $g->fill($data);
        $g->save();
        return response()->json(['success' => true, 'message' => '저장했어요', 'data' => $g]);
    }

    public function posts(Request $request)
    {
        $q = MoneyPost::with('user:id,name,nickname,email', 'cafe:id,name,icon')
            ->when($request->cafe_id, fn ($x, $v) => $x->where('cafe_id', (int) $v))
            ->when($request->status === 'hidden', fn ($x) => $x->where('is_hidden', true))
            ->when($request->status === 'visible', fn ($x) => $x->where('is_hidden', false))
            ->when($request->search, fn ($x, $v) => $x->where('title', 'like', '%' . addcslashes($v, '%_\\') . '%'))
            ->orderByDesc('created_at');
        return response()->json(['success' => true, 'data' => $q->paginate(30, ['id', 'cafe_id', 'user_id', 'kind', 'title', 'view_count', 'comment_count', 'is_hidden', 'created_at'])]);
    }

    public function togglePost($id)
    {
        $p = MoneyPost::findOrFail($id);
        $p->forceFill(['is_hidden' => !$p->is_hidden])->save();
        return response()->json(['success' => true, 'message' => $p->is_hidden ? '숨겼어요' : '다시 보이게 했어요', 'data' => ['is_hidden' => $p->is_hidden]]);
    }
}
