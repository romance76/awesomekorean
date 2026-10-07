<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => ['key' => env('OPENAI_API_KEY', '')],
    'youtube' => [
        'api_key' => env('YOUTUBE_API_KEY'),
    ],

    'stripe' => [
        'key'            => env('STRIPE_KEY'),
        'secret'         => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    // 통화 중계 서버(TURN). 직접 연결이 안 되는 네트워크에서만 쓰인다. 값을 바꾸려면 .env 의 TURN_* 를 수정.
    'turn' => [
        'host'     => env('TURN_HOST', '68.183.60.70:3478'),
        'username' => env('TURN_USERNAME', 'awesomekorean'),
        'password' => env('TURN_PASSWORD'),
    ],

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-service-account.json')),
    ],

    'foodsafety' => [
        'api_key' => env('FOODSAFETY_API_KEY', 'e3ffc744a3fb41299c10'),
        'url'     => env('FOODSAFETY_API_URL', 'http://openapi.foodsafetykorea.go.kr/api'),
        'service' => env('FOODSAFETY_SERVICE', 'COOKRCP01'),
    ],

    'realtyapi' => [
        'key' => env('REALTYAPI_KEY'),
    ],

    'ebay' => [
        'client_id' => env('EBAY_CLIENT_ID'),
        'client_secret' => env('EBAY_CLIENT_SECRET'),
    ],

    'info_ingest' => [
        'token' => env('INFO_INGEST_TOKEN'),
    ],

    // Amazon Associates 제휴 태그 — 코드 곳곳에 하드코딩하지 않고 여기 한 곳에서만
    // 참조(App\Support\AmazonLink). 실 서버 .env에 값이 없어도 기본값으로 바로
    // 동작하도록 fallback을 둠.
    'amazon_associates' => [
        'associate_tag' => env('AMAZON_ASSOCIATE_TAG', 'awesomekorean-20'),
    ],

    // Google/Amazon 소셜 로그인 (Laravel Socialite). 키가 비어있으면 해당 공급자의
    // "~로 로그인" 버튼을 눌러도 Google/Amazon이 "앱이 설정되지 않음" 에러를 보여줌 —
    // Google Cloud Console / Login with Amazon 콘솔에서 OAuth 앱을 등록하고 발급받은
    // 값을 .env에 넣어야 동작함(SocialAuthController 참고).
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/auth/google/callback'),
    ],

    'amazon' => [
        'client_id'     => env('AMAZON_CLIENT_ID'),
        'client_secret' => env('AMAZON_CLIENT_SECRET'),
        'redirect'      => env('AMAZON_REDIRECT_URI', env('APP_URL') . '/auth/amazon/callback'),
    ],

    // API 키를 바꿀 때마다 전체 키 현황을 보내는 관리자 메일 (App\Support\KeyReport)
    'admin_report_email' => env('ADMIN_REPORT_EMAIL', 'romance76@gmail.com'),
];
