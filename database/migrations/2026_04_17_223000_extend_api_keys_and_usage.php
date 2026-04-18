<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2-C 묶음 6: API 중앙 관리 확장
 * - api_keys 테이블에 모니터링·정책 컬럼 추가 (기존 테이블 유지, ALTER)
 * - api_usage: 일별 호출 집계
 * - api_logs: 상세 호출·에러 로그
 * - api_key_history: 키 변경 감사 이력
 */
return new class extends Migration {
    public function up(): void
    {
        // 기존 api_keys 테이블 확장 (DROP 금지 규칙 준수)
        if (Schema::hasTable('api_keys')) {
            Schema::table('api_keys', function (Blueprint $table) {
                if (!Schema::hasColumn('api_keys', 'test_mode'))         $table->boolean('test_mode')->default(false)->after('is_active');
                if (!Schema::hasColumn('api_keys', 'quota_limit'))       $table->bigInteger('quota_limit')->unsigned()->default(0)->after('test_mode');
                if (!Schema::hasColumn('api_keys', 'quota_period'))      $table->enum('quota_period', ['daily','monthly'])->default('daily')->after('quota_limit');
                if (!Schema::hasColumn('api_keys', 'last_verified_at'))  $table->timestamp('last_verified_at')->nullable()->after('quota_period');
                if (!Schema::hasColumn('api_keys', 'last_error_at'))     $table->timestamp('last_error_at')->nullable()->after('last_verified_at');
                if (!Schema::hasColumn('api_keys', 'last_error_message')) $table->text('last_error_message')->nullable()->after('last_error_at');
                if (!Schema::hasColumn('api_keys', 'docs_url'))          $table->string('docs_url', 500)->nullable()->after('last_error_message');
                if (!Schema::hasColumn('api_keys', 'feature_list'))      $table->text('feature_list')->nullable()->after('docs_url');
            });
        }

        Schema::create('api_usage', function (Blueprint $table) {
            $table->id();
            $table->string('service', 100);
            $table->date('date');
            $table->integer('success_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->integer('avg_response_ms')->default(0);
            $table->integer('p95_response_ms')->default(0);
            $table->bigInteger('quota_used')->unsigned()->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['service', 'date']);
            $table->index('date');
        });

        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service', 100);
            $table->enum('level', ['info', 'warning', 'error'])->default('info');
            $table->text('message');
            $table->integer('http_status')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->string('trace_id', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['service', 'created_at']);
            $table->index(['level', 'created_at']);
        });

        Schema::create('api_key_history', function (Blueprint $table) {
            $table->id();
            $table->string('service', 100);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('action', ['created', 'updated', 'deleted', 'rotated', 'revealed', 'tested']);
            $table->string('old_value_masked', 100)->nullable();
            $table->string('new_value_masked', 100)->nullable();
            $table->text('note')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['service', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_history');
        Schema::dropIfExists('api_logs');
        Schema::dropIfExists('api_usage');

        if (Schema::hasTable('api_keys')) {
            Schema::table('api_keys', function (Blueprint $table) {
                foreach (['test_mode','quota_limit','quota_period','last_verified_at','last_error_at','last_error_message','docs_url','feature_list'] as $col) {
                    if (Schema::hasColumn('api_keys', $col)) $table->dropColumn($col);
                }
            });
        }
    }
};
