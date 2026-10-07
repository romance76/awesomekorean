<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 통화 기록에 "왜 끊겼는지 / 어떤 기기·연결이었는지"를 남겨서 관리자 통화 로그에서 문제를 찾을 수 있게 한다.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $t) {
            if (!Schema::hasColumn('calls', 'end_reason'))   $t->string('end_reason', 24)->nullable()->after('status');   // completed/declined/cancelled/no_answer/offline/busy/failed/stale
            if (!Schema::hasColumn('calls', 'ended_by'))     $t->unsignedBigInteger('ended_by')->nullable()->after('end_reason');
            if (!Schema::hasColumn('calls', 'caller_device')) $t->string('caller_device', 16)->nullable();
            if (!Schema::hasColumn('calls', 'callee_device')) $t->string('callee_device', 16)->nullable();
            if (!Schema::hasColumn('calls', 'conn_type'))    $t->string('conn_type', 12)->nullable();    // direct / relay
            if (!Schema::hasColumn('calls', 'rtt_ms'))       $t->unsignedInteger('rtt_ms')->nullable();   // 연결 왕복 지연(ms)
            if (!Schema::hasColumn('calls', 'failure_note')) $t->string('failure_note', 255)->nullable();
            if (!Schema::hasColumn('calls', 'caller_ua'))    $t->string('caller_ua', 160)->nullable();
            if (!Schema::hasColumn('calls', 'callee_ua'))    $t->string('callee_ua', 160)->nullable();
            $t->index(['status', 'created_at'], 'calls_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $t) {
            $t->dropIndex('calls_status_created_idx');
            $t->dropColumn(['end_reason', 'ended_by', 'caller_device', 'callee_device', 'conn_type', 'rtt_ms', 'failure_note', 'caller_ua', 'callee_ua']);
        });
    }
};
