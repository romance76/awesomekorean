<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameSetting;
use Illuminate\Http\Request;

class GameController extends Controller
{
    /** 공개: 활성 게임 목록 (유저 GameLobby 용)
     *  - group_slug 가 있는 게임(ex: casino 의 하위 포커/홀덤/고스톱/블랙잭)은 메인 로비에서 제외.
     *    해당 게임들은 카지노 대기실에서 접근.
     *  - ?include_group=1 이면 전부 반환 (관리자 미리보기용) */
    public function publicIndex(Request $request)
    {
        $q = Game::active()->orderBy('sort_order');
        if (!$request->boolean('include_group')) {
            $q->whereNull('group_slug');
        }
        return response()->json(['success' => true, 'data' => $q->get()]);
    }

    /** 공개: 특정 그룹에 속한 게임들 (예: group_slug=casino) */
    public function groupGames($group)
    {
        $games = Game::active()->where('group_slug', $group)->orderBy('sort_order')->get();
        return response()->json(['success' => true, 'data' => $games]);
    }

    /** 관리자: 전체 게임 목록 */
    public function index()
    {
        $games = Game::orderBy('sort_order')->get();
        return response()->json(['success' => true, 'data' => $games]);
    }

    /** 관리자: 게임 수정 (이름/설명/아이콘/카테고리/순서/활성) */
    public function update(Request $request, $id)
    {
        $game = Game::findOrFail($id);
        $data = $request->validate([
            'name'        => 'nullable|string|max:100',
            'description' => 'nullable|string|max:200',
            'icon'        => 'nullable|string|max:20',
            'category'    => 'nullable|in:card,brain,arcade,word,education',
            'group_slug'  => 'nullable|string|max:50',
            'is_active'   => 'nullable|boolean',
            'sort_order'  => 'nullable|integer',
        ]);
        $game->update($data);
        return response()->json(['success' => true, 'data' => $game->fresh()]);
    }

    /** 관리자: 활성 토글 */
    public function toggle($id)
    {
        $game = Game::findOrFail($id);
        $game->is_active = !$game->is_active;
        $game->save();
        return response()->json(['success' => true, 'data' => $game]);
    }

    /** 관리자: 순서 일괄 재조정 (id 배열 순서대로) */
    public function reorder(Request $request)
    {
        $ids = $request->input('ids', []);
        foreach ($ids as $i => $id) {
            Game::where('id', $id)->update(['sort_order' => $i]);
        }
        return response()->json(['success' => true]);
    }

    /** 관리자: 특정 게임 + 그 게임 설정 전체 */
    public function show($slug)
    {
        $game = Game::where('slug', $slug)->firstOrFail();
        $settings = GameSetting::where('game_type', $slug)->orderBy('key')->get();
        return response()->json(['success' => true, 'data' => [
            'game' => $game,
            'settings' => $settings,
        ]]);
    }

    /** 관리자: 게임별 key/value 설정 저장 */
    public function saveSettings(Request $request, $slug)
    {
        $game = Game::where('slug', $slug)->firstOrFail();
        $settings = $request->input('settings', []);
        foreach ($settings as $s) {
            if (empty($s['key'])) continue;
            GameSetting::updateOrCreate(
                ['game_type' => $game->slug, 'key' => $s['key']],
                ['value' => (string) ($s['value'] ?? '')],
            );
        }
        // 요청에 없는 기존 키는 유지 (삭제는 별도 엔드포인트)
        return response()->json(['success' => true]);
    }

    /** 관리자: 설정 key 삭제 */
    public function deleteSetting($slug, $key)
    {
        GameSetting::where('game_type', $slug)->where('key', $key)->delete();
        return response()->json(['success' => true]);
    }
}
