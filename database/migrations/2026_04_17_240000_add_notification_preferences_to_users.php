<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2-C Post: MyNotificationSettings 영속화.
 * users.notification_preferences JSON 컬럼 추가.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'notification_preferences')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('notification_preferences')->nullable()->after('push_platform');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'notification_preferences')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('notification_preferences');
            });
        }
    }
};
