<?php

use App\Casts\Secret;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// 외부 서비스 키가 DB 에 평문으로 들어 있던 것을 암호화한다(이미 암호화된 행은 건너뜀 — 여러 번 돌려도 안전).
return new class extends Migration
{
    public function up(): void
    {
        DB::table('api_keys')->orderBy('id')->get(['id', 'api_key'])->each(function ($row) {
            if ($row->api_key === null || $row->api_key === '' || str_starts_with($row->api_key, Secret::PREFIX)) return;
            DB::table('api_keys')->where('id', $row->id)->update(['api_key' => Secret::seal($row->api_key)]);
        });
    }

    public function down(): void
    {
        DB::table('api_keys')->orderBy('id')->get(['id', 'api_key'])->each(function ($row) {
            if ($row->api_key && str_starts_with($row->api_key, Secret::PREFIX)) {
                DB::table('api_keys')->where('id', $row->id)->update(['api_key' => Secret::reveal($row->api_key)]);
            }
        });
    }
};
