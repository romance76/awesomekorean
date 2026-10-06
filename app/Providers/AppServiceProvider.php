<?php

namespace App\Providers;

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
    }
}
