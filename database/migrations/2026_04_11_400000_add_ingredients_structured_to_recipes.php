<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('recipe_posts', 'ingredients_structured')) {
                $table->json('ingredients_structured')->nullable()->after('ingredients_en');
            }
            if (!Schema::hasColumn('recipe_posts', 'translated_at')) {
                $table->timestamp('translated_at')->nullable()->after('ingredients_structured');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recipe_posts', function (Blueprint $table) {
            if (Schema::hasColumn('recipe_posts', 'ingredients_structured')) {
                $table->dropColumn('ingredients_structured');
            }
            if (Schema::hasColumn('recipe_posts', 'translated_at')) {
                $table->dropColumn('translated_at');
            }
        });
    }
};
