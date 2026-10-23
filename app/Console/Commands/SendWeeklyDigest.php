<?php

namespace App\Console\Commands;

use App\Mail\WeeklyDigestMail;
use App\Models\User;
use App\Support\EmailConsent;
use App\Support\WeeklyDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * 월요일 정기 소식 메일.
 *  - 기본(스케줄이 매시간 호출): "실제 발송"이 열려 있을 때만 일한다. 우편 주소가 없거나 스위치가 꺼져 있으면 아무것도 보내지 않고 끝난다.
 *  - --test=주소 [--state=GA]: 시험 발송 한 통(잠겨 있어도 가능, 광고 노출 집계·발송 기록에는 반영하지 않음)
 *  - 열려 있으면: 받는 사람 현지 시각이 월요일 오전 9시인 동의 회원에게, 이번 주에 아직 안 보낸 사람만(회원별 기록으로 중복 방지)
 */
class SendWeeklyDigest extends Command
{
    protected $signature = 'mail:weekly-digest {--test= : 이 주소로 시험 메일 한 통} {--state= : 시험 메일의 받는 사람 주(예: GA)} {--limit=250 : 한 번 실행에 보낼 최대 통수}';
    protected $description = '월요일 정기 소식 메일(맨 아래 지역 광고 띠 포함). 우편 주소가 없으면 실제 발송은 잠김';

    public function handle(): int
    {
        if ($test = trim((string) $this->option('test'))) {
            $user = User::where('email', $test)->first();
            $data = WeeklyDigest::payload($user, $this->option('state') ?: null, null, true);
            Mail::to($test)->send(new WeeklyDigestMail($data));
            $this->info("시험 메일을 {$test} 로 보냈어요(광고 " . count($data['ads']) . '개).');
            return self::SUCCESS;
        }

        if ($why = WeeklyDigest::lockReason()) {
            $this->info('실제 발송 잠김 — ' . $why);
            return self::SUCCESS;
        }

        $week = WeeklyDigest::weekKey();
        $limit = max(1, (int) $this->option('limit'));
        $sent = 0; $failed = 0;
        EmailConsent::eligibleQuery()->orderBy('id')->chunkById(200, function ($users) use ($week, $limit, &$sent, &$failed) {
            foreach ($users as $u) {
                if ($sent + $failed >= $limit) return false;
                $now = now(WeeklyDigest::timezoneOf($u->state));
                if (!$now->isMonday() || $now->hour !== WeeklyDigest::SEND_HOUR) continue;
                // 먼저 기록을 넣어(회원·주 유일) 겹쳐 실행돼도 두 번 보내지 않는다
                $ok = DB::table('email_digest_sends')->insertOrIgnore(['user_id' => $u->id, 'week_key' => $week, 'status' => 'sent', 'sent_at' => now()]);
                if (!$ok) continue;
                try {
                    $data = WeeklyDigest::payload($u);
                    Mail::to($u->email)->send(new WeeklyDigestMail($data));
                    WeeklyDigest::recordSent($data['ads']);
                    $sent++;
                } catch (\Throwable $e) {
                    DB::table('email_digest_sends')->where('user_id', $u->id)->where('week_key', $week)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 190)]);
                    \Log::warning("[주간 소식 메일] 발송 실패 user={$u->id}: " . $e->getMessage());
                    $failed++;
                }
            }
        });
        $this->info("보냄 {$sent}통, 실패 {$failed}통 ({$week})");
        return self::SUCCESS;
    }
}
