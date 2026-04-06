<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

if (!App\Models\User::where('email', 'test@test.com')->exists()) {
    App\Models\User::create([
        'name' => '테스터',
        'nickname' => 'tester',
        'email' => 'test@test.com',
        'password' => bcrypt('test1234'),
        'role' => 'admin',
        'points' => 9999,
        'language' => 'ko',
        'city' => 'Los Angeles',
        'state' => 'CA',
        'zipcode' => '90010',
        'latitude' => 34.0522,
        'longitude' => -118.2437,
        'bio' => '테스트 관리자 계정',
    ]);
    echo "CREATED: test@test.com / test1234\n";
} else {
    echo "EXISTS\n";
}
