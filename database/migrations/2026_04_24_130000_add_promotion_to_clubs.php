<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            if (!Schema::hasColumn('clubs', 'state')) {
                $table->string('state', 10)->nullable()->after('zipcode')->index();
            }
            if (!Schema::hasColumn('clubs', 'city')) {
                $table->string('city', 80)->nullable()->after('zipcode');
            }
            if (!Schema::hasColumn('clubs', 'promotion_tier')) {
                $table->enum('promotion_tier', ['none','sponsored','state_plus','national'])->default('none')->after('is_public')->index();
            }
            if (!Schema::hasColumn('clubs', 'promotion_expires_at')) {
                $table->timestamp('promotion_expires_at')->nullable()->after('promotion_tier');
            }
            if (!Schema::hasColumn('clubs', 'promotion_states')) {
                $table->json('promotion_states')->nullable()->after('promotion_expires_at');
            }
            if (!Schema::hasColumn('clubs', 'boosted_until')) {
                $table->timestamp('boosted_until')->nullable()->after('promotion_states')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            foreach (['state','city','promotion_tier','promotion_expires_at','promotion_states','boosted_until'] as $col) {
                if (Schema::hasColumn('clubs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
