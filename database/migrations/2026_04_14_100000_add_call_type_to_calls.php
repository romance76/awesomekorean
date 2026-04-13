<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            if (!Schema::hasColumn('calls', 'call_type')) {
                $table->string('call_type', 20)->default('friend')->after('callee_id');
                // friend = 친구 통화, elder = 안심 서비스
            }
        });

        // 기존 answered 상태인데 ended_at 없는 건 → 자동 종료 처리
        \DB::table('calls')
            ->whereIn('status', ['answered', 'ringing'])
            ->whereNull('ended_at')
            ->update([
                'status' => 'ended',
                'ended_at' => now(),
                'duration' => \DB::raw('TIMESTAMPDIFF(SECOND, COALESCE(answered_at, created_at), NOW())'),
            ]);
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn('call_type');
        });
    }
};
