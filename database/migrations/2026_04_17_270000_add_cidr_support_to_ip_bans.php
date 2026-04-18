<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2-C Post: IP 차단 CIDR 지원 (Kay #5 완성).
 * ip_address 컬럼을 varchar(50) 으로 확장 (CIDR '1.2.3.0/24' 수용).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ip_bans')) {
            Schema::table('ip_bans', function (Blueprint $table) {
                if (!Schema::hasColumn('ip_bans', 'is_cidr')) {
                    $table->boolean('is_cidr')->default(false)->after('ip_address');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ip_bans', 'is_cidr')) {
            Schema::table('ip_bans', function (Blueprint $table) {
                $table->dropColumn('is_cidr');
            });
        }
    }
};
