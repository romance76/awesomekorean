<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2-C 묶음 9: 통계·감사 테이블.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('kpi_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->integer('total_users')->default(0);
            $table->integer('new_users')->default(0);
            $table->integer('dau')->default(0);                      // last_login today
            $table->integer('mau')->default(0);                      // last_login 30d
            $table->integer('posts_count')->default(0);
            $table->integer('comments_count')->default(0);
            $table->integer('market_items_count')->default(0);
            $table->integer('real_estate_count')->default(0);
            $table->integer('jobs_count')->default(0);
            $table->decimal('revenue_usd', 12, 2)->default(0);
            $table->integer('payments_count')->default(0);
            $table->integer('reports_count')->default(0);
            $table->integer('sos_count')->default(0);
            $table->integer('ip_bans_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index('date');
        });

        Schema::create('event_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 100);
            $table->string('target_type', 50)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->index(['user_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
            $table->index('occurred_at');
        });

        Schema::create('admin_audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users');
            $table->string('action', 100);
            $table->string('target_type', 50)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('before_value')->nullable();
            $table->json('after_value')->nullable();
            $table->text('note')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['admin_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        Schema::create('dashboard_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('default_view', 50)->default('executive');
            $table->json('widget_config')->nullable();
            $table->json('saved_filters')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_preferences');
        Schema::dropIfExists('admin_audit_log');
        Schema::dropIfExists('event_log');
        Schema::dropIfExists('kpi_daily');
    }
};
