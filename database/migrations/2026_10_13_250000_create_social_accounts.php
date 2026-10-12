<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 사이트 공식 SNS 계정(인스타그램·스레드) 정보 — 관리자 › 사이트 설정 › SNS 계정에서 관리.
// 앱 시크릿·액세스 토큰은 모델에서 암호화(encrypted cast)해 저장하고 화면에는 끝 4자리만 보여 준다.
// 계정 비밀번호는 저장하지 않는다(자동 게시는 Meta 액세스 토큰으로만). 계획서 qa/sns_autopost_plan.md
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('social_accounts')) {
            Schema::create('social_accounts', function (Blueprint $t) {
                $t->id();
                $t->string('platform', 20)->unique();          // instagram | threads
                $t->string('handle', 100)->nullable();          // @아이디
                $t->string('profile_url', 300)->nullable();
                $t->string('login_email', 200)->nullable();     // 계정에 연결된 이메일(참고용)
                $t->string('account_id', 100)->nullable();      // 인스타 비즈니스 계정 ID / 스레드 사용자 ID
                $t->string('page_id', 100)->nullable();         // 인스타에 연결된 페이스북 페이지 ID
                $t->string('app_id', 100)->nullable();          // Meta 앱 ID
                $t->text('app_secret')->nullable();             // 암호화
                $t->text('access_token')->nullable();           // 암호화 (장기 토큰, 60일)
                $t->timestamp('token_expires_at')->nullable();
                $t->text('notes')->nullable();
                $t->boolean('is_active')->default(true);
                $t->boolean('autopost_enabled')->default(false); // 자동 게시 켜기 (게시 기능 만들기 전까지 꺼 둠)
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
            });
        }
        $now = now();
        foreach (['instagram', 'threads'] as $p) {
            if (!DB::table('social_accounts')->where('platform', $p)->exists()) {
                DB::table('social_accounts')->insert(['platform' => $p, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        // 표를 지우지 않음(운영 규칙: DROP 금지)
    }
};
