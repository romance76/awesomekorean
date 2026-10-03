<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Sweepstakes용 Entry 시스템 — 기존 Point(point_logs/point_settings)와는
// 완전히 분리된 두 번째 자산. 교환/전환 경로가 생기지 않도록 테이블도,
// 지갑 컬럼도, 설정 테이블도 모두 별도로 둠.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('entries')->default(0)->after('lifetime_points');
            // 출석 progress: 0~(checkin_required_count-1) 사이를 오가며,
            // 필요 횟수에 도달하면 1 Entry 지급 후 0으로 리셋.
            $table->unsignedTinyInteger('entry_checkin_progress')->default(0)->after('entries');
        });

        // 출석 기록 — user_daily_spins와 동일한 패턴(하루 1회를 DB UNIQUE
        // 제약으로 강제, 애플리케이션 레벨 카운트 체크에 의존하지 않음).
        Schema::create('entry_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('checkin_date');
            $table->timestamps();
            $table->unique(['user_id', 'checkin_date'], 'entry_checkin_unique');
        });

        // Entry 트랜잭션 로그 — point_logs의 하우스 컨벤션을 그대로 따름
        // (user_id/amount/balance_after/type/reason 패턴), 테이블만 분리.
        Schema::create('entry_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->unsignedInteger('balance_before');
            $table->unsignedInteger('balance_after');
            $table->string('transaction_type', 30); // SIGNUP_BONUS, CHECKIN_REWARD, SWEEPSTAKES_ENTRY, ADMIN_ADJUSTMENT, REFUND
            $table->string('source', 100)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            // 중복 클릭/네트워크 재시도로 동일 요청이 두 번 들어와도 중복 차감되지
            // 않도록 — 프론트가 액션마다 UUID를 발급해 넘기면, 먼저 처리된 행이
            // 있을 때 서비스 레이어가 그 결과를 그대로 반환하고 새로 차감하지 않음.
            $table->string('idempotency_key', 64)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->unique(['user_id', 'idempotency_key'], 'entry_tx_idempotency_unique');
        });

        // Entry 전용 설정 — point_settings와 동일한 key/value 구조를
        // 그대로 복제하되 완전히 별도 테이블(관리자 화면도 분리됨).
        Schema::create('entry_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('label');
            $table->string('value');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        DB::table('entry_settings')->insert([
            ['key' => 'signup_bonus', 'label' => '회원가입 Entry 보너스', 'value' => '1', 'description' => '신규 회원가입 시 자동 지급 (Point 보너스와 별도)', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'checkin_required_count', 'label' => 'Entry 획득에 필요한 출석 횟수', 'value' => '5', 'description' => '출석 도장이 이 숫자에 도달하면 Entry 1개 지급 후 0부터 다시 시작', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'checkin_daily_max', 'label' => '하루 최대 출석 횟수', 'value' => '1', 'description' => '하루에 도장은 최대 1개만 받을 수 있음', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'expiry_enabled', 'label' => 'Entry 만료 여부', 'value' => '0', 'description' => '0 = 만료 없음, 1 = expiry_days 이후 만료', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'expiry_days', 'label' => 'Entry 만료 기간(일)', 'value' => '0', 'description' => 'expiry_enabled=1일 때만 사용', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_settings');
        Schema::dropIfExists('entry_transactions');
        Schema::dropIfExists('entry_checkins');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['entries', 'entry_checkin_progress']);
        });
    }
};
