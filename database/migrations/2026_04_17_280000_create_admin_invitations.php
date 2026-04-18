<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2-C Post: 관리자 초대 시스템.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191);
            $table->string('role', 50);                 // super_admin | manager | moderator
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by')->constrained('users');
            $table->enum('status', ['pending', 'accepted', 'expired', 'revoked'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['email', 'status']);
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_invitations');
    }
};
