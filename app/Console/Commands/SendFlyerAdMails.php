<?php

namespace App\Console\Commands;

use App\Mail\FlyerAdMail;
use App\Support\EmailConsent;
use App\Support\FlyerMailer;
use App\Support\WeeklyDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * 전면광고 광고주 특권 메일(무료) 자동 발송 — 규칙은 App\Support\FlyerMailer 머리말 참고.
 * 월요일 소식 메일과 같은 잠금을 쓴다: 우편 주소가 없거나 "실제 발송" 스위치가 꺼져 있으면 아무것도 보내지 않는다.
 * 매시간 돌며, 받는 사람 현지 시각이 오전 10시인 회원만 처리한다.
 *  --dry : 잠금과 상관없이 "지금 보낼 대상"만 세어 보여 준다(실제 발송 없음, 기록 없음)
 */
class SendFlyerAdMails extends Command
{
    protected $signature = 'mail:flyer-ads {--dry : 보내지 않고 대상만 센다} {--limit=250 : 한 번 실행에 보낼 최대 통수}';
    protected $description = '승인된 전면광고를 각 주의 마지막 날 해당 지역 수신 동의 회원에게 메일로 알림(주 1통 상한, 우편 주소 없으면 잠김)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        if (!$dry && ($why = WeeklyDigest::lockReason())) {
            $this->info('실제 발송 잠김 — ' . $why);
            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $sent = 0; $failed = 0; $would = 0; $skippedCap = 0;
        EmailConsent::eligibleQuery()->orderBy('id')->chunkById(200, function ($users) use ($dry, $limit, &$sent, &$failed, &$would, &$skippedCap) {
            foreach ($users as $u) {
                if (!$dry && $sent + $failed >= $limit) return false;
                $now = now(WeeklyDigest::timezoneOf($u->state));
                if (!$dry && ($now->isMonday() || $now->hour !== FlyerMailer::LOCAL_HOUR)) continue;   // 월요일은 소식 메일 날 — 쉼
                $pending = FlyerMailer::pendingFor($u, $now->toDateString());
                if (!$pending) continue;
                if (FlyerMailer::receivedThisWeek($u->id)) { $skippedCap++; continue; }   // 주 1통 상한 — 남은 몫은 다음 날 이후로 미룸
                if ($dry) { $would++; continue; }

                // 먼저 기록(광고·회원·날짜 유일)을 넣어 겹쳐 실행돼도 두 번 보내지 않는다. 같은 광고의 밀린 몫은 한 번에 처리한 것으로 기록
                $rows = [];
                foreach ($pending as $p) foreach ($p['due'] as $d) $rows[] = ['flyer_ad_id' => $p['ad']->id, 'user_id' => $u->id, 'due_date' => $d, 'status' => 'sent', 'sent_at' => now()];
                if (!DB::table('email_flyer_sends')->insertOrIgnore($rows)) continue;
                try {
                    $data = WeeklyDigest::flyerPayload($u, array_map(fn($p) => $p['ad'], $pending));
                    Mail::to($u->email)->send(new FlyerAdMail($data));
                    $sent++;
                } catch (\Throwable $e) {
                    // 실패하면 기록을 지워 다음 날 다시 시도되게 한다(미룬 몫 허용 기간 안에서)
                    foreach ($rows as $r) DB::table('email_flyer_sends')->where(['flyer_ad_id' => $r['flyer_ad_id'], 'user_id' => $r['user_id'], 'due_date' => $r['due_date']])->delete();
                    \Log::warning("[전면광고 메일] 발송 실패 user={$u->id}: " . $e->getMessage());
                    $failed++;
                }
            }
        });
        $this->info($dry ? "(대상 확인) 오늘 보낼 회원 {$would}명, 주 1통 상한으로 미룬 회원 {$skippedCap}명" : "보냄 {$sent}통, 실패 {$failed}통, 주 1통 상한으로 미룸 {$skippedCap}명");
        return self::SUCCESS;
    }
}
