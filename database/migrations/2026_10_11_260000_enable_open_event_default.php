<?php

use App\Models\SiteSetting;
use App\Support\OpenEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 소프트 오픈 이벤트(10/15 ~ 11/30, 미국 동부 날짜)를 추천 설정 그대로 "켜 둔" 상태로 만든다 (Kay 지시 2026-10-11).
// 날짜가 되기 전에는 아무것도 바뀌지 않고(상태 '예정'), 11/30 이 지나면 자동으로 원래대로 돌아온다.
// 안전장치: 관리자가 이미 직접 저장해 둔 설정(추천값과 다른 값)이 있으면 절대 덮어쓰지 않는다.
// 끄는 법: 관리자 › 홍보·이벤트 › 오픈 이벤트 에서 '오픈 이벤트 사용'을 끄고 저장.
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('site_settings')) return;

        $defaults = OpenEvent::defaults();
        $row = SiteSetting::where('key', 'open_event')->first();

        if ($row) {
            $saved = json_decode((string) $row->value, true);
            if (!is_array($saved)) $saved = [];
            // 이미 켜져 있거나, 추천값과 다르게 직접 손본 흔적이 있으면 그대로 둔다
            $asDefaultOff = OpenEvent::normalize($saved);
            $untouched = $asDefaultOff === array_merge($defaults, ['enabled' => (bool) $asDefaultOff['enabled']]);
            if (!empty($asDefaultOff['enabled']) || !$untouched) return;
        }

        OpenEvent::save(array_merge($defaults, ['enabled' => true]));

        if (DB::getSchemaBuilder()->hasTable('admin_todos')) {
            $rows = DB::table('admin_todos')->where('title', 'like', '오픈 이벤트 적용%')->where('status', '!=', 'done')->get(['id', 'detail']);
            foreach ($rows as $r) {
                DB::table('admin_todos')->where('id', $r->id)->update([
                    'status' => 'done', 'done_at' => now(), 'updated_at' => now(),
                    'detail' => trim(($r->detail ?? '') . "\n[완료 10/11] 추천 설정(포인트 2배·채팅방 무료·광고 90% 할인 등, 10/15~11/30)으로 자동 적용 상태로 켬. 끄려면 오픈 이벤트 화면에서 '오픈 이벤트 사용'을 끄고 저장."),
                ]);
            }
        }
    }

    public function down(): void
    {
        // 데이터 보호: 설정을 되돌리지 않는다. (끄는 건 관리자 화면에서)
    }
};
