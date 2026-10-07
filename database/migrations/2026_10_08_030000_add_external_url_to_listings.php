<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 임시로 가져온 중고장터(eBay)·부동산 매물에 "원본 매물 보기" 링크를 달기 위한 칸.
// 회원이 직접 올린 매물(source=user)에는 쓰지 않는다.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['market_items', 'real_estate_listings'] as $table) {
            if (!Schema::hasColumn($table, 'external_url')) {
                Schema::table($table, fn ($t) => $t->string('external_url', 600)->nullable()->after('external_id'));
            }
        }

        // 이미 들어와 있는 eBay 매물: external_id 가 "v1|123456789012|0" 형태 → https://www.ebay.com/itm/123456789012
        DB::table('market_items')->where('source', 'scraped')->where('external_source', 'ebay')->whereNull('external_url')
            ->orderBy('id')->select('id', 'external_id')->chunkById(500, function ($rows) {
                foreach ($rows as $r) {
                    $parts = explode('|', (string) $r->external_id);
                    $num = $parts[1] ?? $parts[0] ?? '';
                    if (preg_match('/^\d{9,14}$/', $num)) {
                        DB::table('market_items')->where('id', $r->id)->update(['external_url' => 'https://www.ebay.com/itm/' . $num]);
                    }
                }
            });

        // 이미 들어와 있는 부동산 매물: 원본 주소를 따로 받아 두지 않았으므로, 주소로 Zillow 검색 링크를 만들어 둔다
        DB::table('real_estate_listings')->where('source', 'scraped')->whereNull('external_url')
            ->orderBy('id')->select('id', 'address', 'city', 'state', 'zipcode')->chunkById(500, function ($rows) {
                foreach ($rows as $r) {
                    $q = trim(implode(' ', array_filter([$r->address, $r->city, $r->state, $r->zipcode])));
                    if ($q === '') continue;
                    $slug = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $q), '-');
                    DB::table('real_estate_listings')->where('id', $r->id)->update(['external_url' => 'https://www.zillow.com/homes/' . $slug . '_rb/']);
                }
            });
    }

    public function down(): void
    {
        foreach (['market_items', 'real_estate_listings'] as $table) {
            if (Schema::hasColumn($table, 'external_url')) Schema::table($table, fn ($t) => $t->dropColumn('external_url'));
        }
    }
};
