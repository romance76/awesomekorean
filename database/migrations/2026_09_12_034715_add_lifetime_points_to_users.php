<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// 회원 등급 시스템: users.points는 상위노출/홀드결제 등으로 깎이는 "지갑"이라
// 등급 기준으로 그대로 쓰면 포인트를 쓰자마자 강등되는 문제가 생김.
// 그래서 차감과 무관하게 누적 획득량만 쌓이는 lifetime_points를 별도로 둠
// (User::addPoints()가 양수 지급 시에만 함께 증가시킴). 기존 point_logs의
// 양수 합계로 1회 백필해 지금까지의 실제 활동이 등급에 바로 반영되게 함.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'lifetime_points')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('lifetime_points')->default(0)->after('points');
            });
        }

        DB::statement("
            UPDATE users
            SET lifetime_points = COALESCE((
                SELECT SUM(amount) FROM point_logs
                WHERE point_logs.user_id = users.id AND point_logs.amount > 0
            ), 0)
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'lifetime_points')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('lifetime_points');
            });
        }
    }
};
