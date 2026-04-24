<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;

class QuizQuestionController extends Controller
{
    /** 공개: 특정 게임 + 레벨의 활성 문제 반환 (옵션: limit, level<=) */
    public function publicIndex(Request $request, string $slug)
    {
        $level = (int) $request->input('level', 1);
        $levelMode = $request->input('level_mode', 'lte'); // lte: <= level, eq: = level
        $limit = min(200, (int) $request->input('limit', 50));

        $q = QuizQuestion::active()->forGame($slug);
        if ($levelMode === 'eq') { $q->where('level', $level); }
        else { $q->where('level', '<=', $level); }

        // 매번 다른 문제가 나오도록 DB 레벨에서 랜덤 샘플링 (이전엔 sort_order 고정 순으로
        // 같은 문제만 반복되던 버그)
        $items = $q->inRandomOrder()->limit($limit)->get()->map(function ($q) {
            return [
                'id'     => $q->id,
                'level'  => $q->level,
                'answer' => $q->answer,
                'image'  => $q->resolved_image,
                'hint'   => $q->hint,
                'sound'  => $q->sound,
                'wrongs' => $q->wrong_array,
            ];
        });
        return response()->json(['success' => true, 'data' => $items]);
    }

    /** 관리자: 특정 게임 문제 목록 (페이지네이션) */
    public function index(Request $request, string $slug)
    {
        $q = QuizQuestion::forGame($slug);
        if ($request->filled('level')) $q->where('level', (int) $request->input('level'));
        if ($request->filled('search')) $q->where('answer', 'like', '%' . $request->input('search') . '%');
        return response()->json([
            'success' => true,
            'data'    => $q->orderBy('sort_order')->orderBy('id')->paginate(50),
        ]);
    }

    public function store(Request $request, string $slug)
    {
        $data = $request->validate([
            'level'        => 'required|integer|min:1|max:5',
            'answer'       => 'required|string|max:100',
            'wrong_answers'=> 'nullable|string',
            'image_url'    => 'nullable|string|max:500',
            'emoji_hex'    => 'nullable|string|max:20',
            'hint'         => 'nullable|string|max:200',
            'sound'        => 'nullable|string|max:100',
            'is_active'    => 'nullable|boolean',
            'sort_order'   => 'nullable|integer',
        ]);
        $data['game_slug'] = $slug;
        $q = QuizQuestion::create($data);
        return response()->json(['success' => true, 'data' => $q]);
    }

    public function update(Request $request, string $slug, $id)
    {
        $q = QuizQuestion::forGame($slug)->findOrFail($id);
        $data = $request->validate([
            'level'        => 'nullable|integer|min:1|max:5',
            'answer'       => 'nullable|string|max:100',
            'wrong_answers'=> 'nullable|string',
            'image_url'    => 'nullable|string|max:500',
            'emoji_hex'    => 'nullable|string|max:20',
            'hint'         => 'nullable|string|max:200',
            'sound'        => 'nullable|string|max:100',
            'is_active'    => 'nullable|boolean',
            'sort_order'   => 'nullable|integer',
        ]);
        $q->update($data);
        return response()->json(['success' => true, 'data' => $q->fresh()]);
    }

    public function destroy(string $slug, $id)
    {
        QuizQuestion::forGame($slug)->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }
}
