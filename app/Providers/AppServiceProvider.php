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

        // 가입 보너스("매 N번째 가입"): 새 회원이 만들어진 직후 확인한다. 실패해도 가입 자체에는 영향을 주지 않는다.
        \App\Models\User::created(function ($user) {
            try {
                \App\Support\SweepstakesAutomation::onSignup((int) $user->id);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    // 운영 서버 .env에 MAIL_MAILER=log가 명시적으로 박혀있어서(단순 미설정이 아님)
    // "값이 없을 때만 전환"하는 이전 로직으로는 Resend 키를 등록해도 계속 log
    // 드라이버로만 동작해 메일이 전혀 발송되지 않았음(Resend 쪽 발송 로그 자체가
    // 0건인 것으로 확인) — .env가 명시적으로 resend를 가리키는 경우가 아니라면
    // 항상 Resend로 강제 전환하도록 수정.
    // RESEND_API_KEY를 .env 우선, 없으면 관리자 페이지 "API 키 관리"의 api_keys 테이블
    // (서비스 코드: resend_api_key)에서 찾아서 Resend 발송으로 전환.
    private function configureMail(): void
    {
        $key = config('services.resend.key');
        if (!$key) {
            try {
                $row = DB::table('api_keys')->where('service', 'resend_api_key')->where('is_active', true)->first();
                if ($row && $row->api_key) $key = \App\Casts\Secret::reveal($row->api_key);
            } catch (\Exception $e) {}
        }

        if (!$key) return;

        config(['services.resend.key' => $key]);
        if (env('MAIL_MAILER') !== 'resend') config(['mail.default' => 'resend']);
        if (!env('MAIL_FROM_ADDRESS')) {
            config(['mail.from.address' => 'no-reply@awesomekorean.com', 'mail.from.name' => 'Awesome Korean']);
        }
    }
}
