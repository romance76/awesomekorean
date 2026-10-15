<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 한인 업소록에 섞여 있는 한인이 아닌 업체(대형 체인·타 민족 상점)를 공개 화면에서 숨긴다 (2026-10-14 사이트 점검, 사용자 결정 C).
// 삭제가 아니다: businesses.hidden_at 에 시각을 적어 둘 뿐이고, 그 칸을 비우면 다시 보인다. 리뷰·클레임 등 연결된 데이터도 그대로다.
// 업체 이름이 아래 적어 둔 이름과 정확히 같을 때만 숨긴다(번호가 다른 업체를 가리키게 되는 사고 방지). 여러 번 돌려도 안전하다.
return new class extends Migration
{
    /** id => 점검 때 확인한 이름 */
    private const TARGETS = [
        2693 => 'Southeast Asian Market',
        21908 => 'Nguyen Complete Auto Repair',
        16895 => '99 Ranch Market', 6625 => '99 Ranch Market', 3168 => '99 Ranch Market', 20893 => '99 Ranch Market',
        6865 => 'Daido Market Houston',
        12085 => 'Bavarian Collision',
        4048 => 'Flushing Fei Long Market',
        21731 => 'Izbushka Spa',
        12830 => 'Saigon Oriental Market',
        13997 => 'Kroger', 11785 => 'Kroger',
        21218 => 'Desert Oasis European Auto Service & Repair',
        6614 => 'Mitsuwa Marketplace - Texas Plano', 3127 => 'Mitsuwa Marketplace',
        5842 => 'Japanese American Society-Nj',
        11528 => 'Tomato Japanese Grocery',
        9232 => 'European Optical',
        17024 => 'Safeway', 15665 => 'Safeway',
        18259 => 'Daiso - Japanese Household Goods',
        21095 => 'Walgreens',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('businesses')) return;
        if (!Schema::hasColumn('businesses', 'hidden_at')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->timestamp('hidden_at')->nullable()->after('promotion_states');
            });
        }
        Cache::forever('biz_hidden_col', true);   // App\Models\Business::scopeVisible 이 컬럼 유무를 확인할 때 쓰는 표시

        $now = now();
        foreach (self::TARGETS as $id => $name) {
            DB::table('businesses')->where('id', $id)->where('name', $name)->whereNull('hidden_at')->update(['hidden_at' => $now]);
        }
    }

    public function down(): void
    {
        // 숨김은 되돌리지 않는다(데이터 보호). 다시 보이게 하려면 hidden_at 을 비운다.
    }
};
