<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2-C Post: 공지사항 배너 (사이트 상단 bar).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('message');
            $table->enum('level', ['info', 'success', 'warning', 'danger'])->default('info');
            $table->string('link_url', 500)->nullable();
            $table->string('link_label', 100)->nullable();
            $table->boolean('dismissible')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('audience', 50)->default('all');  // all|logged_in|guest|role:xxx
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
