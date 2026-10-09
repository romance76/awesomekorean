<?php

namespace App\Support;

use App\Models\Sweepstakes;
use App\Models\SweepstakesPrizeClaim;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 당첨자 상품 수령(연락처 확인) 건 관리.
 * 추첨 로직과 분리되어 있어, 언제 호출해도 안전(idempotent)하다.
 */
class SweepstakesPrizeClaimService
{
    /** 종료된 추첨의 모든 당첨자에 대해 누락된 claim 행을 만든다. 반환: 해당 추첨의 claim 컬렉션 */
    public static function syncFor(Sweepstakes $s)
    {
        if ($s->status !== 'winner_selected') {
            return collect();
        }

        $winners = []; // user_id => [rank, label]

        if (Schema::hasTable('sweepstakes_winners')) {
            $rows = DB::table('sweepstakes_winners')
                ->where('sweepstakes_id', $s->id)
                ->orderBy('rank')
                ->get(['user_id', 'rank', 'prize_label']);
            foreach ($rows as $r) {
                if ($r->user_id && !isset($winners[$r->user_id])) {
                    $winners[$r->user_id] = [(int) $r->rank, $r->prize_label ?: (string) $s->prize_name];
                }
            }
        }

        if ($s->winner_user_id && !isset($winners[$s->winner_user_id])) {
            $winners[$s->winner_user_id] = [1, (string) $s->prize_name];
        }

        foreach ($winners as $userId => [$rank, $label]) {
            SweepstakesPrizeClaim::firstOrCreate(
                ['sweepstakes_id' => $s->id, 'user_id' => $userId],
                ['rank' => max(1, min(255, $rank)), 'prize_label' => $label !== '' ? $label : null]
            );
        }

        return SweepstakesPrizeClaim::where('sweepstakes_id', $s->id)->get();
    }

    /** 회원 프로필 기준 연락처 상태 (본인에게만 노출) */
    public static function contactStatus($user): array
    {
        $email = trim((string) ($user->email ?? ''));
        $phone = trim((string) ($user->phone ?? ''));
        $addr = trim((string) ($user->address1 ?? ''));
        $city = trim((string) ($user->city ?? ''));
        $zip = trim((string) ($user->zipcode ?? ''));
        $addressOk = $addr !== '' && $zip !== '';

        $missing = [];
        if ($email === '') $missing[] = 'email';
        if ($phone === '') $missing[] = 'phone';
        if (!$addressOk) $missing[] = 'address';

        return [
            'email' => $email,
            'phone' => $phone,
            'address' => trim(implode(' ', array_filter([
                $addr, trim((string) ($user->address2 ?? '')), $city, trim((string) ($user->state ?? '')), $zip,
            ]))),
            'missing' => $missing,
        ];
    }
}
