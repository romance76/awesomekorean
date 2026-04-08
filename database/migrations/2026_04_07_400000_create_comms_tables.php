<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1:1 대화방
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_a_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user_b_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['user_a_id', 'user_b_id']);
            $table->index(['user_a_id', 'last_message_at']);
            $table->index(['user_b_id', 'last_message_at']);
        });

        // 메시지
        Schema::create('comm_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->text('body');
            $table->enum('type', ['text', 'image', 'system'])->default('text');
            $table->timestamp('read_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
        });

        // 통화 기록
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->string('room_id', 64)->unique();
            $table->foreignId('caller_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('callee_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['ringing', 'answered', 'ended', 'missed', 'declined', 'failed'])->default('ringing');
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration')->default(0);
            $table->timestamps();
            $table->index(['caller_id', 'created_at']);
            $table->index(['callee_id', 'created_at']);
        });

        // 온라인 상태
        Schema::create('user_presences', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->onDelete('cascade');
            $table->enum('status', ['online', 'busy', 'offline'])->default('offline');
            $table->timestamp('last_seen_at')->nullable();
        });

        // 차단 목록
        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('blocked_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['blocker_id', 'blocked_id']);
        });

        // FCM 토큰
        if (!Schema::hasColumn('users', 'fcm_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('fcm_token')->nullable()->after('remember_token');
                $table->string('push_platform')->nullable()->after('fcm_token');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('user_presences');
        Schema::dropIfExists('calls');
        Schema::dropIfExists('comm_messages');
        Schema::dropIfExists('conversations');
        if (Schema::hasColumn('users', 'fcm_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['fcm_token', 'push_platform']);
            });
        }
    }
};
