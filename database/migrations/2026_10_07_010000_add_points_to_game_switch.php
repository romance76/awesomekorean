<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 포인트 → 포커 칩 / 게임머니 전환 스위치 (point_settings). 기본값 0 = 막음.
 * 포인트는 광고·끌어올리기 등 사이트 서비스 이용에만 쓰도록 하고, 포인트가 다른
 * 화폐로 바뀌어 게임 자금이 되는 경로를 닫는다. 이미 가진 칩/게임머니를 포인트로
 * 되돌리는 건 그대로 허용(보유 자산이 갇히지 않게). 필요하면 관리자가 1로 되돌림.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('point_settings')->updateOrInsert(
            ['key' => 'points_to_game_enabled'],
            [
                'category' => 'spend',
                'label' => '포인트→포커 칩/게임머니 전환 허용',
                'value' => '0',
                'description' => '0 = 막음(기본), 1 = 허용. 이미 가진 칩/게임머니를 포인트로 되돌리는 것은 이 값과 무관하게 가능',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        // no-op
    }
};
