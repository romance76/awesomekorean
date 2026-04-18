<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2-C 묶음 8: 서버 백업 이력.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('server_backups', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['db','storage','config','snapshot']);
            $table->enum('status', ['running','completed','failed'])->default('running');
            $table->string('file_path', 500)->nullable();
            $table->bigInteger('file_size_bytes')->unsigned()->default(0);
            $table->char('md5_hash', 32)->nullable();
            $table->string('do_snapshot_id', 100)->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('triggered_type', ['manual','cron','api'])->default('cron');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_backups');
    }
};
