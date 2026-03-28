<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add location fields to existing tables if columns don't exist
        foreach (['posts', 'job_posts', 'market_items', 'match_profiles'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'latitude'))  $table->decimal('latitude', 10, 7)->nullable();
                if (!Schema::hasColumn($tableName, 'longitude')) $table->decimal('longitude', 10, 7)->nullable();
                if (!Schema::hasColumn($tableName, 'address'))   $table->string('address')->nullable();
            });
        }

        // businesses table already might have address, add lat/lng if missing
        if (!Schema::hasColumn('businesses', 'latitude')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
            });
        }

        // Add default_radius to users table for user preference
        if (!Schema::hasColumn('users', 'default_radius')) {
            Schema::table('users', function (Blueprint $table) {
                $table->integer('default_radius')->default(30); // default 30 miles
            });
        }
    }

    public function down(): void
    {
        foreach (['posts', 'job_posts', 'market_items', 'match_profiles'] as $tableName) {
            if (Schema::hasColumn($tableName, 'latitude')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn(['latitude', 'longitude', 'address']);
                });
            }
        }

        if (Schema::hasColumn('businesses', 'latitude')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropColumn(['latitude', 'longitude']);
            });
        }

        if (Schema::hasColumn('users', 'default_radius')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('default_radius');
            });
        }
    }
};
