<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Amazon\AmazonExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Amazon은 Socialite 코어에 내장돼 있지 않아 socialiteproviders/amazon 패키지가
        // 이 이벤트를 통해 'amazon' 드라이버를 등록함 (Google은 코어 내장이라 등록 불필요).
        Event::listen(SocialiteWasCalled::class, [AmazonExtendSocialite::class, 'handle']);

        $this->configureMail();
    }

    // MAIL_MAILER가 .env에 설정돼 있지 않으면 기본값 'log'로 동작해서 이메일이 실제로
    // 발송되지 않고 로그에만 기록됨(비밀번호 재설정/이메일 인증 메일이 전부 안 가던 원인).
    // RESEND_API_KEY를 .env 우선, 없으면 관리자 페이지 "API 키 관리"의 api_keys 테이블
    // (서비스 코드: resend_api_key)에서 찾아서 Resend 발송으로 전환.
    private function configureMail(): void
    {
        $key = config('services.resend.key');
        if (!$key) {
            try {
                $row = DB::table('api_keys')->where('service', 'resend_api_key')->where('is_active', true)->first();
                if ($row && $row->api_key) $key = $row->api_key;
            } catch (\Exception $e) {}
        }

        if (!$key) return;

        config(['services.resend.key' => $key]);
        if (!env('MAIL_MAILER')) config(['mail.default' => 'resend']);
        if (!env('MAIL_FROM_ADDRESS')) {
            config(['mail.from.address' => 'no-reply@awesomekorean.com', 'mail.from.name' => 'Awesome Korean']);
        }
    }
}
