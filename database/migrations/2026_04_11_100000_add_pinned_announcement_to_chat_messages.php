<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_messages', 'pinned_until')) {
                $table->timestamp('pinned_until')->nullable()->after('is_read');
                $table->index(['chat_room_id', 'pinned_until']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (Schema::hasColumn('chat_messages', 'pinned_until')) {
                $table->dropIndex(['chat_room_id', 'pinned_until']);
                $table->dropColumn('pinned_until');
            }
        });
    }
};
