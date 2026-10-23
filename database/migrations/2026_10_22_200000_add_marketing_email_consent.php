<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 광고·소식 메일 수신 동의(옵트인). 기본은 모두 "동의 안 함".
// users: 현재 상태(동의 여부·시각·안내를 보여 준 시각) / email_consent_logs: 동의·철회 이력(분쟁 대비 증거)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'marketing_email_consent')) {
                $table->boolean('marketing_email_consent')->default(false)->index();
                $table->timestamp('marketing_consent_at')->nullable();        // 마지막으로 동의한 시각
                $table->timestamp('marketing_consent_asked_at')->nullable();  // 안내 배너에 답했거나 닫은 시각(다시 묻지 않기 위해)
            }
        });

        if (!Schema::hasTable('email_consent_logs')) {
            Schema::create('email_consent_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('action', 10);              // granted | revoked
                $table->string('source', 30);              // register | banner | profile | unsubscribe_link | admin
                $table->string('ip', 45)->nullable();
                $table->string('text_version', 10);        // 동의 문구 버전(문구를 바꾸면 올림)
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_consent_logs');
        Schema::table('users', function (Blueprint $table) {
            foreach (['marketing_email_consent', 'marketing_consent_at', 'marketing_consent_asked_at'] as $c) {
                if (Schema::hasColumn('users', $c)) $table->dropColumn($c);
            }
        });
    }
};
