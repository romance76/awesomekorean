<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 이벤트 RSVP 리마인더가 전혀 없어 참석 신청자가 행사 임박을 알 방법이
// 없던 문제 수정용 — 중복 발송 방지를 위한 발송 여부 컬럼 추가.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_attendees', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('event_attendees', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
