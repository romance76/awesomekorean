<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('music_categories', function (Blueprint $table) {
            $table->boolean('auto_fetch')->default(true)->after('pop_queries');
            $table->boolean('is_active')->default(true)->after('auto_fetch');
        });
    }

    public function down(): void
    {
        Schema::table('music_categories', function (Blueprint $table) {
            $table->dropColumn(['auto_fetch', 'is_active']);
        });
    }
};
