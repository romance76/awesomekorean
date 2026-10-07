<?php

namespace App\Support;

use App\Models\EntryCheckin;
use App\Models\EntryTransaction;
use App\Models\Sweepstakes;
use App\Models\SweepstakesEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Entry(Sweepstakes 응모권) 지급/차감의 유일한 통로.
 * Point(User::addPoints)와 완전히 분리 — 이 클래스 어디에도 points 컬럼을
 * 읽거나 쓰는 코드가 없어야 한다(교환 경로를 만들지 않기 위한 설계 원칙).
 */
class EntryService
{
    /**
     * 단순 지급/차감 + 이력 기록 (회원가입 보너스, 관리자 수동 조정, 환불 등).
     * 음수 amount 는 차감이며, 잔액 부족 시 예외를 던진다.
     */
    public static function award(
        User $user,
        int $amount,
        string $transactionType,
        string $description,
        ?string $source = null,
        ?array $reference = null,
        ?string $idempotencyKey = null
    ): EntryTransaction {
        return DB::transaction(function () use ($user, $amount, $transactionType, $description, $source, $reference, $idempotencyKey) {
            if ($idempotencyKey) {
                $existing = EntryTransaction::where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            $before = (int) $locked->entries;
            $after = $before + $amount;
            if ($after < 0) {
                throw new \RuntimeException('Entry 잔액이 부족합니다.');
            }

            // entries는 points와 동일한 이유로 $fillable에 없음(mass-assignment
            // 금지) — update()는 조용히 무시되므로 반드시 forceFill 사용.
            $locked->forceFill(['entries' => $after])->save();

            return EntryTransaction::create([
                'user_id' => $locked->id,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'transaction_type' => $transactionType,
                'source' => $source,
                'reference_type' => $reference['type'] ?? null,
                'reference_id' => $reference['id'] ?? null,
                'description' => $description,
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    /**
     * 하루 한도(해당 transaction_type 의 오늘 지급 건수) 안에서만 지급.
     * 사용자 행을 잠근 뒤 세므로 동시 요청이 한도를 넘기지 못한다.
     */
    public static function awardCapped(
        User $user,
        int $amount,
        string $transactionType,
        string $description,
        string $source,
        int $dailyMax,
        ?array $reference = null
    ): bool {
        if ($amount <= 0 || $dailyMax <= 0) return false;

        return DB::transaction(function () use ($user, $amount, $transactionType, $description, $source, $dailyMax, $reference) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $today = EntryTransaction::where('user_id', $user->id)
                ->where('transaction_type', $transactionType)
                ->where('amount', '>', 0)
                ->whereDate('created_at', today())
                ->count();
            if ($today >= $dailyMax) return false;

            static::award($user, $amount, $transactionType, $description, $source, $reference);
            return true;
        });
    }

    /**
     * 이메일 인증 완료 보너스 — 계정당 평생 1회(idempotency key 로 중복 방지).
     */
    public static function awardEmailVerified(User $user): void
    {
        $amount = EntrySettings::get('email_verify_bonus', 1);
        if ($amount <= 0) return;
        static::award($user, $amount, 'EMAIL_VERIFY_BONUS', '이메일 인증 완료 보너스', 'email_verify', null, "email_verify:{$user->id}");
    }

    /**
     * 활동 보상 — 포인트가 실제로 지급된 작성(글/댓글/답변/리뷰 등) 1건마다 호출.
     * N건이 쌓이면 Entry 1개. 하루 지급 한도에 걸리면 진행도를 N 에 붙잡아 두었다가
     * 한도가 풀리는 다음 활동 때 지급한다.
     */
    public static function recordActivity(User $user): bool
    {
        $required = EntrySettings::get('activity_required_count', 10);
        if ($required <= 0) return false;
        $dailyMax = EntrySettings::get('activity_daily_max', 1);

        return DB::transaction(function () use ($user, $required, $dailyMax) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            $progress = min((int) $locked->entry_activity_progress + 1, $required);

            if ($progress < $required) {
                $locked->forceFill(['entry_activity_progress' => $progress])->save();
                return false;
            }

            $awarded = static::awardCapped($locked, 1, 'ACTIVITY_REWARD', "활동 {$required}회 보너스", 'activity', $dailyMax);
            $locked->forceFill(['entry_activity_progress' => $awarded ? 0 : $required])->save();
            return $awarded;
        });
    }

    /**
     * 완료형 활동(판매완료·거래완료·채용확정 등) 보상. 하루 한도 적용.
     */
    public static function awardMilestone(User $user, string $reason, string $modelClass, int $modelId): void
    {
        static::awardCapped(
            $user,
            EntrySettings::get('milestone_bonus', 1),
            'MILESTONE_REWARD',
            $reason,
            'milestone',
            EntrySettings::get('milestone_daily_max', 1),
            ['type' => $modelClass, 'id' => $modelId]
        );
    }

    /**
     * 출석체크. 하루 1회만 성공 — entry_checkins(user_id, checkin_date) UNIQUE
     * 제약으로 애플리케이션 레벨 경쟁 상태(동시 더블클릭)까지 방어한다
     * (user_daily_spins와 동일한 패턴).
     *
     * @return array{already_checked_in_today: bool, progress: int, required: int, entry_awarded: bool}
     */
    public static function checkin(User $user): array
    {
        $today = now()->toDateString();
        $required = max(1, EntrySettings::get('checkin_required_count', 5));

        try {
            DB::transaction(function () use ($user, $today) {
                EntryCheckin::create(['user_id' => $user->id, 'checkin_date' => $today]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if (in_array($e->errorInfo[1] ?? 0, [1062, 19])) {
                $fresh = $user->fresh();
                return [
                    'already_checked_in_today' => true,
                    'progress' => (int) $fresh->entry_checkin_progress,
                    'required' => $required,
                    'entry_awarded' => false,
                ];
            }
            throw $e;
        }

        return DB::transaction(function () use ($user, $required) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            $progress = (int) $locked->entry_checkin_progress + 1;

            if ($progress >= $required) {
                $locked->forceFill(['entry_checkin_progress' => 0])->save();
                static::award(
                    $locked,
                    1,
                    'CHECKIN_REWARD',
                    "출석 {$required}회 완료 보너스",
                    'checkin'
                );
                return [
                    'already_checked_in_today' => false,
                    'progress' => 0,
                    'required' => $required,
                    'entry_awarded' => true,
                ];
            }

            $locked->forceFill(['entry_checkin_progress' => $progress])->save();
            return [
                'already_checked_in_today' => false,
                'progress' => $progress,
                'required' => $required,
                'entry_awarded' => false,
            ];
        });
    }

    /**
     * 특정 Sweepstakes에 Entry를 투입. user 잔액 차감 + sweepstakes_entries
     * 누적 + sweepstakes.total_entries 누적을 하나의 트랜잭션으로 원자 처리.
     * $idempotencyKey 를 넘기면 동일 키의 재요청(더블클릭/API 재시도)은
     * 추가 차감 없이 직전 결과를 그대로 반환한다.
     */
    public static function spendOnSweepstakes(
        User $user,
        Sweepstakes $sweepstakes,
        int $amount,
        ?string $idempotencyKey = null
    ): SweepstakesEntry {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount는 1 이상이어야 합니다.');
        }

        return DB::transaction(function () use ($user, $sweepstakes, $amount, $idempotencyKey) {
            if ($idempotencyKey) {
                $existingTx = EntryTransaction::where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existingTx) {
                    return SweepstakesEntry::where('sweepstakes_id', $sweepstakes->id)
                        ->where('user_id', $user->id)
                        ->firstOrFail();
                }
            }

            $lockedSweeps = Sweepstakes::whereKey($sweepstakes->id)->lockForUpdate()->first();
            if (!$lockedSweeps || !$lockedSweeps->isOpenForEntries()) {
                throw new \RuntimeException('지금은 참가할 수 없는 Sweepstakes입니다.');
            }

            $lockedUser = User::whereKey($user->id)->lockForUpdate()->first();
            $before = (int) $lockedUser->entries;
            if ($before < $amount) {
                throw new \RuntimeException('Entry 잔액이 부족합니다.');
            }
            $after = $before - $amount;
            $lockedUser->forceFill(['entries' => $after])->save();

            EntryTransaction::create([
                'user_id' => $lockedUser->id,
                'amount' => -$amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'transaction_type' => 'SWEEPSTAKES_ENTRY',
                'source' => 'sweepstakes',
                'reference_type' => Sweepstakes::class,
                'reference_id' => $lockedSweeps->id,
                'description' => "Sweepstakes 참가: {$lockedSweeps->title}",
                'idempotency_key' => $idempotencyKey,
            ]);

            $entryRow = SweepstakesEntry::where('sweepstakes_id', $lockedSweeps->id)
                ->where('user_id', $lockedUser->id)
                ->lockForUpdate()
                ->first();
            if (!$entryRow) {
                $entryRow = SweepstakesEntry::create([
                    'sweepstakes_id' => $lockedSweeps->id,
                    'user_id' => $lockedUser->id,
                    'entries_count' => 0,
                ]);
            }
            $entryRow->increment('entries_count', $amount);
            $lockedSweeps->increment('total_entries', $amount);

            return $entryRow->fresh();
        });
    }
}
