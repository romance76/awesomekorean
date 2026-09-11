<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 업소 소유권 클레임 인증 메일(ClaimVerificationMail)이 코드는 있지만 어디서도
// 호출되지 않아 실제로는 발송된 적이 없던 문제 수정용 — 인증 상태를 기록할 컬럼 추가.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_claims', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('business_claims', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
