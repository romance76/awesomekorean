<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 신고(reports) 처리 이력 — "누가 언제 해결/기각/메모/재오픈 했는지"를 건별로 정확히 남긴다.
// 지금까지는 신고 시각(created_at)과 마지막 수정 시각(updated_at)만 있어서, 메모만 고쳐도 시각이 바뀌고 처리자는 기록되지 않았다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (!Schema::hasColumn('reports', 'handled_at')) {
                $table->timestamp('handled_at')->nullable()->after('admin_note');   // 가장 최근 처리(해결/기각) 시각
                $table->unsignedBigInteger('handled_by')->nullable()->after('handled_at'); // 처리한 관리자
            }
        });

        if (!Schema::hasTable('report_logs')) {
            Schema::create('report_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('report_id')->index();
                $table->string('action', 30);                 // created | resolved | dismissed | reopened | note | hide_content
                $table->string('from_status', 20)->nullable();
                $table->string('to_status', 20)->nullable();
                $table->unsignedBigInteger('admin_id')->nullable();   // 처리한 관리자 (신고 접수는 비움)
                $table->string('actor_name', 100)->nullable();        // 그 시점의 이름(나중에 이름이 바뀌어도 기록은 유지)
                $table->text('note')->nullable();
                $table->boolean('estimated')->default(false);         // 옛 기록에서 시각을 추정해 채운 경우
                $table->timestamp('created_at')->useCurrent();
                $table->index(['report_id', 'created_at']);
            });
        }

        // ── 기존 신고 채우기 (이미 이력 테이블에 있는 신고는 건너뜀) ──
        $names = DB::table('users')->pluck(DB::raw('COALESCE(nickname, name)'), 'id');
        DB::table('reports')->orderBy('id')->chunkById(200, function ($rows) use ($names) {
            foreach ($rows as $r) {
                if (DB::table('report_logs')->where('report_id', $r->id)->exists()) continue;
                DB::table('report_logs')->insert([
                    'report_id' => $r->id, 'action' => 'created', 'from_status' => null, 'to_status' => 'pending',
                    'admin_id' => null, 'actor_name' => $names[$r->reporter_id] ?? null,
                    'note' => $r->reason, 'estimated' => false, 'created_at' => $r->created_at,
                ]);
                if ($r->status !== 'pending') {
                    // 정확한 처리 시각이 저장된 적이 없어 마지막 수정 시각으로 대신한다 → estimated 표시
                    DB::table('report_logs')->insert([
                        'report_id' => $r->id, 'action' => $r->status === 'resolved' ? 'resolved' : ($r->status === 'dismissed' ? 'dismissed' : 'note'),
                        'from_status' => 'pending', 'to_status' => $r->status,
                        'admin_id' => null, 'actor_name' => null,
                        'note' => $r->admin_note, 'estimated' => true, 'created_at' => $r->updated_at ?? $r->created_at,
                    ]);
                    DB::table('reports')->where('id', $r->id)->update(['handled_at' => $r->updated_at ?? $r->created_at]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_logs');
        Schema::table('reports', function (Blueprint $table) {
            if (Schema::hasColumn('reports', 'handled_at')) $table->dropColumn(['handled_at', 'handled_by']);
        });
    }
};