<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('job_posts', 'logo')) {
                $table->string('logo')->nullable()->after('company');
            }
            if (!Schema::hasColumn('job_posts', 'job_tags')) {
                $table->json('job_tags')->nullable()->after('category'); // ["cook","server","cashier"]
            }
            if (!Schema::hasColumn('job_posts', 'benefits')) {
                $table->json('benefits')->nullable()->after('job_tags'); // ["health","401k","paid_vacation"]
            }
            if (!Schema::hasColumn('job_posts', 'company_pdf')) {
                $table->string('company_pdf')->nullable()->after('content');
            }
            // 프로모션 필드
            if (!Schema::hasColumn('job_posts', 'promotion_tier')) {
                $table->enum('promotion_tier', ['none', 'sponsored', 'state_plus', 'national'])->default('none')->after('is_active');
            }
            if (!Schema::hasColumn('job_posts', 'promotion_expires_at')) {
                $table->timestamp('promotion_expires_at')->nullable()->after('promotion_tier');
            }
            if (!Schema::hasColumn('job_posts', 'promotion_states')) {
                $table->json('promotion_states')->nullable()->after('promotion_expires_at'); // 내 스테이트 + 인접 스테이트
            }
        });

        // 프로모션 슬롯 관리 테이블
        if (!Schema::hasTable('job_promotions')) {
            Schema::create('job_promotions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('job_post_id')->constrained('job_posts')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->enum('tier', ['national', 'state_plus', 'sponsored']);
                $table->json('states')->nullable(); // state_plus 일 때 적용 주
                $table->unsignedInteger('days');
                $table->unsignedInteger('daily_cost');
                $table->unsignedInteger('total_cost');
                $table->timestamp('starts_at');
                $table->timestamp('expires_at');
                $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
                $table->timestamps();
                $table->index(['tier', 'status', 'expires_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('job_promotions');
    }
};
