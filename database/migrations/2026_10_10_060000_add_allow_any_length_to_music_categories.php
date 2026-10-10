<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 경음악(클래식·재즈 등) 카테고리는 곡 길이 제한(2분30초~5분)을 풀고 1분~30분까지 보여주고 수집한다.
// 카테고리마다 관리자 화면(카테고리 관리)에서 켜고 끌 수 있다. 기존 곡은 삭제·수정하지 않고, 보이는 범위만 바뀐다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('music_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('music_categories', 'allow_any_length')) {
                $table->boolean('allow_any_length')->default(false)->after('auto_fetch');
            }
        });
        DB::table('music_categories')->whereIn('slug', ['jazz', 'classic'])->update(['allow_any_length' => true]);
    }

    public function down(): void
    {
        Schema::table('music_categories', function (Blueprint $table) {
            if (Schema::hasColumn('music_categories', 'allow_any_length')) {
                $table->dropColumn('allow_any_length');
            }
        });
    }
};