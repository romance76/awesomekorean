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
];
