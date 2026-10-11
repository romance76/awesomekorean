<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 관리자 즐겨찾기(자주 쓰는 메뉴) — 로그인한 관리자 본인 것만 읽고 쓴다. 메뉴 주소(/admin/...) 목록만 저장한다.
 * 운영자(moderator)도 쓸 수 있다(AdminTier 'favorites' 규칙 1등급). 표가 아직 없으면 빈 목록을 돌려준다.
 */
class AdminFavoritesController extends Controller
{
    private const MAX = 12;

    public function index(Request $request)
    {
        if (!Schema::hasTable('admin_favorites')) return response()->json(['success' => true, 'data' => []]);
        $raw = DB::table('admin_favorites')->where('user_id', $request->user()->id)->value('items');
        $items = json_decode((string) $raw, true);
        return response()->json(['success' => true, 'data' => is_array($items) ? array_values($items) : []]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'items' => 'present|array|max:' . self::MAX,
            'items.*' => ['string', 'max:80', 'regex:#^/admin(/[A-Za-z0-9_\-]+)*$#'],
        ]);
        if (!Schema::hasTable('admin_favorites')) {
            return response()->json(['success' => false, 'message' => '아직 서버에 저장할 수 없어요. 잠시 뒤 다시 시도해 주세요.'], 503);
        }
        $items = array_values(array_unique($data['items']));
        DB::table('admin_favorites')->updateOrInsert(
            ['user_id' => $request->user()->id],
            ['items' => json_encode($items, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()]
        );
        return response()->json(['success' => true, 'data' => $items]);
    }
}
