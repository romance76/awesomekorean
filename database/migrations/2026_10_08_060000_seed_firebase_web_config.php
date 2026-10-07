<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 푸시 알림(쪽지/1:1 대화/안심 통화): 서비스 워커(public/sw.js)에 이미 들어 있는 Firebase 웹 설정(공개 값)을
// 관리자 > API 키 관리 > Firebase 칸에 미리 채워 둔다. 비어 있을 때만 채우고, 이미 입력한 값은 건드리지 않는다.
// VAPID 키는 Firebase 콘솔에서 직접 복사해야 해서 비워 둔다 (입력하면 바로 켜짐).
return new class extends Migration {
    public function up(): void
    {
        $defaults = [
            'firebase_api_key'    => 'AIzaSyAOfIdUvVXqblgb7NrmPGWViIawuZNpDTA',
            'firebase_project_id' => 'awesomekorean-c9430',
            'firebase_sender_id'  => '430136797121',
            'firebase_app_id'     => '1:430136797121:web:768cffa39c96a35e81f140',
        ];
        foreach ($defaults as $key => $value) {
            $row = DB::table('site_settings')->where('key', $key)->first();
            if (!$row) {
                DB::table('site_settings')->insert(['key' => $key, 'value' => $value, 'group' => 'firebase', 'created_at' => now(), 'updated_at' => now()]);
            } elseif (trim((string) $row->value) === '') {
                DB::table('site_settings')->where('key', $key)->update(['value' => $value, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void {}
};
