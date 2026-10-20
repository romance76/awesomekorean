<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 공식 지역 행사 피드(관광청·시청·카운티) 출처 목록.
 * 매주 일요일 자동으로 돌며, 한인이 많이 사는 곳의 큰 행사만(주 10 · 카운티 3 · 도시 2) 골라 events 에 넣는다.
 * type: tribe(WordPress Events Calendar API) / socrata_nycparks / ics / sitemap(사이트맵+행사 페이지의 JSON-LD) / microdata
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_feed_sources', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 80);
            $table->string('state', 2);
            $table->string('county', 40)->nullable();   // 카운티 몫(3개) 계산용. 주 전체 출처는 비움(도시 기준)
            $table->string('city', 60)->nullable();     // 행사에 도시 정보가 없을 때 기본값
            $table->string('type', 20);
            $table->json('config')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('last_candidates')->default(0);
            $table->unsignedInteger('last_selected')->default(0);
            $table->string('last_error', 300)->nullable();
            $table->timestamps();
        });

        $rows = [
            ['il_chicago', '시카고 관광청 (Choose Chicago)', 'IL', 'Cook', 'Chicago', 'tribe', ['base' => 'https://www.choosechicago.com']],
            ['ny_nyc_parks', '뉴욕시 공원 행사 (NYC Parks)', 'NY', 'New York City', 'New York', 'socrata_nycparks', ['url' => 'https://data.cityofnewyork.us/resource/w3wp-dpdi.json?$limit=1000']],
            ['wa_seattle_city', '시애틀 시청 (seattle.gov)', 'WA', 'King', 'Seattle', 'ics', ['url' => 'https://www.trumba.com/calendars/seattlegov-city-wide.ics']],
            ['wa_visit_bellevue', 'Visit Bellevue (벨뷰 관광)', 'WA', 'King', 'Bellevue', 'sitemap', ['base' => 'https://visitbellevuewa.com']],
            ['nj_bergen', '버겐 카운티청 (Bergen County)', 'NJ', 'Bergen', null, 'sitemap', ['sitemaps' => ['https://bergencountynj.gov/wp-sitemap-posts-mec-events-1.xml']]],
            ['va_visit_fairfax', 'Visit Fairfax (페어팩스 관광)', 'VA', 'Fairfax', null, 'sitemap', ['base' => 'https://www.fxva.com']],
            ['ga_explore_gwinnett', 'Explore Gwinnett (귀넷 관광)', 'GA', 'Gwinnett', null, 'sitemap', ['base' => 'https://www.exploregwinnett.org']],
            ['md_visit_montgomery', 'Visit Montgomery (몽고메리 관광)', 'MD', 'Montgomery', null, 'sitemap', ['sitemaps' => ['https://visitmontgomery.com/sitemap_index.xml'], 'child_regex' => 'gd_event-sitemap']],
            ['md_visit_howard', 'Visit Howard County (하워드 관광)', 'MD', 'Howard', null, 'sitemap', ['base' => 'https://www.visithowardcounty.com']],
            ['tx_visit_dallas', 'Visit Dallas (달라스 관광)', 'TX', 'Dallas', 'Dallas', 'sitemap', ['base' => 'https://www.visitdallas.com']],
            ['ca_visit_california', 'Visit California (캘리포니아 관광)', 'CA', null, null, 'sitemap', ['base' => 'https://www.visitcalifornia.com']],
            ['ca_visit_buena_park', 'Visit Buena Park (부에나파크 관광)', 'CA', 'Orange', 'Buena Park', 'microdata', ['base' => 'https://www.visitbuenapark.com', 'url_regex' => '#/events/\d{4}/[^/?\#]+#i']],
        ];
        $now = now();
        foreach ($rows as $i => [$key, $name, $state, $county, $city, $type, $config]) {
            DB::table('event_feed_sources')->insert([
                'key' => $key, 'name' => $name, 'state' => $state, 'county' => $county, 'city' => $city, 'type' => $type,
                'config' => json_encode($config, JSON_UNESCAPED_SLASHES), 'enabled' => true, 'sort_order' => $i + 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_feed_sources');
    }
};
