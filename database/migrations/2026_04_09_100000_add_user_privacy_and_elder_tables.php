<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // users 테이블에 프라이버시 + 주소 컬럼 추가
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'address1')) $table->string('address1')->nullable()->after('state');
            if (!Schema::hasColumn('users', 'address2')) $table->string('address2')->nullable()->after('address1');
            if (!Schema::hasColumn('users', 'allow_messages')) $table->boolean('allow_messages')->default(true)->after('allow_friend_request');
            if (!Schema::hasColumn('users', 'allow_elder_service')) $table->boolean('allow_elder_service')->default(false)->after('allow_messages');
        });

        // 보호자-보호대상 매칭 테이블
        if (!Schema::hasTable('elder_guardians')) {
            Schema::create('elder_guardians', function (Blueprint $table) {
                $table->id();
                $table->foreignId('guardian_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('ward_user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('status', ['pending', 'active', 'rejected'])->default('pending');
                $table->timestamps();
                $table->unique(['guardian_user_id', 'ward_user_id']);
            });
        }

        // 안심서비스 스케줄 테이블
        if (!Schema::hasTable('elder_schedules')) {
            Schema::create('elder_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('elder_guardian_id')->constrained('elder_guardians')->cascadeOnDelete();
                $table->enum('type', ['random', 'scheduled'])->default('random');
                $table->string('time_start', 5)->default('09:00'); // random: 시작시간
                $table->string('time_end', 5)->default('18:00');   // random: 종료시간
                $table->integer('calls_per_day')->default(1);
                $table->json('days')->nullable(); // ["mon","tue","wed","thu","fri","sat","sun"]
                $table->json('scheduled_times')->nullable(); // ["09:00","14:00"]
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 전화 로그 테이블
        if (!Schema::hasTable('elder_call_logs')) {
            Schema::create('elder_call_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('elder_guardian_id')->constrained('elder_guardians')->cascadeOnDelete();
                $table->unsignedBigInteger('ward_user_id');
                $table->timestamp('called_at');
                $table->boolean('answered')->default(false);
                $table->integer('attempts')->default(1);
                $table->boolean('guardian_notified')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('elder_call_logs');
        Schema::dropIfExists('elder_schedules');
        Schema::dropIfExists('elder_guardians');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['address1', 'address2', 'allow_messages', 'allow_elder_service']);
        });
    }
};
