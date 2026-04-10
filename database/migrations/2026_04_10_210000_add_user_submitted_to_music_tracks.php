<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('music_tracks', function (Blueprint $table) {
            if (!Schema::hasColumn('music_tracks', 'is_user_submitted')) {
                $table->boolean('is_user_submitted')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('music_tracks', function (Blueprint $table) {
            if (Schema::hasColumn('music_tracks', 'is_user_submitted')) {
                $table->dropColumn('is_user_submitted');
            }
        });
    }
};
