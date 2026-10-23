<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 전면광고 광고주 특권 메일(자동 발송): 회원에게 어떤 광고를 어느 "주 마지막 날(due_date)" 몫으로 보냈는지 기록한다.
// 같은 광고·같은 회원·같은 날짜 몫은 한 번만 보내고, 회원당 주 1통 상한도 이 기록으로 센다.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_flyer_sends')) {
            Schema::create('email_flyer_sends', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flyer_ad_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->date('due_date');                       // 그 광고의 "주 마지막 날" 몫
                $table->string('status', 10)->default('sent'); // sent | failed
                $table->timestamp('sent_at')->useCurrent();
                $table->unique(['flyer_ad_id', 'user_id', 'due_date'], 'flyer_send_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_flyer_sends');
    }
};
