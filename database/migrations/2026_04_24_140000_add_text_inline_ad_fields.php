<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('banner_ads', function (Blueprint $table) {
            if (!Schema::hasColumn('banner_ads', 'ad_type')) {
                $table->enum('ad_type', ['image','text'])->default('image')->after('position')->index();
            }
            if (!Schema::hasColumn('banner_ads', 'phone')) {
                $table->string('phone', 30)->nullable()->after('ad_type');
            }
            if (!Schema::hasColumn('banner_ads', 'description')) {
                $table->string('description', 200)->nullable()->after('phone');
            }
        });

        // image_url 을 nullable로 변경 (text 타입 시 비어있어야 함)
        DB::statement('ALTER TABLE banner_ads MODIFY image_url VARCHAR(500) NULL');

        // 샘플 텍스트 인라인 광고 3건 시드 (admin user_id=1 가정, 없으면 첫번째 유저)
        $adminId = DB::table('users')->whereIn('role', ['admin', 'super_admin'])->value('id')
                   ?? DB::table('users')->orderBy('id')->value('id');
        if ($adminId) {
            $samples = [
                ['💈 강남미용실',        '770-555-1234', '한인 전용 $30부터 · 첫방문 20% 할인 · Duluth GA'],
                ['🍜 종로국수집',         '770-555-5678', '수제 칼국수/만두/김치전 · 평일 런치스페셜 $9.99'],
                ['🏠 에이스 부동산',      '678-555-9012', '렌트/매매/상가 전문 15년 경력 · 한국어 상담 무료'],
            ];
            $rows = [];
            foreach ($samples as $i => $s) {
                $rows[] = [
                    'user_id'      => $adminId,
                    'title'        => $s[0],
                    'image_url'    => null,
                    'link_url'     => null,
                    'ad_type'      => 'text',
                    'phone'        => $s[1],
                    'description'  => $s[2],
                    'page'         => 'all',
                    'position'     => 'inline-text',
                    'slot_number'  => 1,
                    'geo_scope'    => 'all',
                    'bid_amount'   => 1000 - $i * 100,
                    'daily_cost'   => 33,
                    'total_cost'   => 1000 - $i * 100,
                    'start_date'   => now()->toDateString(),
                    'end_date'     => now()->addMonth()->toDateString(),
                    'status'       => 'active',
                    'impressions'  => 0,
                    'clicks'       => 0,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }
            DB::table('banner_ads')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::table('banner_ads', function (Blueprint $table) {
            foreach (['ad_type','phone','description'] as $col) {
                if (Schema::hasColumn('banner_ads', $col)) $table->dropColumn($col);
            }
        });
    }
};
