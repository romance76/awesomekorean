<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 사이트 설정 화면에만 있고 어디서도 읽지 않던 "구글 Analytics ID / 카카오 API 키" 칸을 정리한다.
 *  - 구글 Analytics ID 는 API 키 관리(api_keys, service=google_analytics)로 옮겨 실제 사이트에 연결한다.
 *  - 카카오 API 키는 쓰는 곳이 없어 삭제한다.
 */
return new class extends Migration {
    public function up(): void
    {
        $ga = trim((string) DB::table('site_settings')->where('key', 'google_analytics_id')->value('value'));
        $ga = trim($ga, "\"' ");
        if ($ga !== '' && preg_match('/^G-[A-Z0-9]{6,12}$/', $ga)
            && !DB::table('api_keys')->where('service', 'google_analytics')->exists()) {
            DB::table('api_keys')->insert([
                'name' => '구글 Analytics 측정 ID',
                'service' => 'google_analytics',
                'api_key' => $ga,
                'description' => '방문 통계(GA4). 활성이면 모든 공개 페이지에 추적 코드가 들어갑니다.',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('site_settings')->whereIn('key', ['google_analytics_id', 'kakao_api_key'])->delete();
    }

    public function down(): void
    {
        // 되돌릴 값 없음 (카카오 키는 사용처가 없었고, GA 는 api_keys 에 그대로 남음)
    }
};
