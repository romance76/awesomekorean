<?php

// 우리 사이트(웹)에서만 API 를 브라우저로 호출할 수 있게 한다. (예전엔 모든 사이트 '*' 허용)
// 모바일 앱/다른 도메인을 붙일 때는 .env 의 CORS_ALLOWED_ORIGINS 에 쉼표로 추가.
$extra = array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))));
$app = rtrim((string) env('APP_URL', ''), '/');
$origins = array_values(array_unique(array_filter(array_merge(
    [$app, 'https://awesomekorean.com', 'https://www.awesomekorean.com'],
    env('APP_ENV') === 'local' ? ['http://localhost:5173', 'http://127.0.0.1:5173', 'http://localhost:8000', 'http://127.0.0.1:8000', 'http://127.0.0.1:8123'] : [],
    $extra
))));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization', 'Content-Type', 'X-Requested-With', 'Accept', 'X-XSRF-TOKEN', 'X-CSRF-TOKEN'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
