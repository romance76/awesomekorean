<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 관리자 > 손님 메일(소개 메일): 관리자가 붙여 넣은 주소 목록과, 수신 거부한 주소(영구 제외 목록)
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('outreach_recipients')) {
            Schema::create('outreach_recipients', function (Blueprint $table) {
                $table->id();
                $table->string('email', 190)->unique();          // 소문자로 저장
                $table->string('status', 10)->default('queued'); // queued | sent | failed
                $table->unsignedSmallInteger('sent_count')->default(0);
                $table->timestamp('last_sent_at')->nullable()->index();
                $table->string('error', 200)->nullable();
                $table->timestamps();
                $table->index('status');
            });
        }
        if (!Schema::hasTable('email_suppressions')) {
            Schema::create('email_suppressions', function (Blueprint $table) {
                $table->id();
                $table->string('email', 190)->unique();          // 소문자. 여기 있는 주소에는 소개 메일을 다시는 보내지 않는다
                $table->string('source', 30)->default('unsubscribe_link'); // unsubscribe_link | admin
                $table->string('ip', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_recipients');
        Schema::dropIfExists('email_suppressions');
    }
};
