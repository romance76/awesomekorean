<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\WeeklyDigestMail;
use App\Models\BannerAd;
use App\Models\User;
use App\Support\EmailConsent;
use App\Support\WeeklyDigest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

// 관리자 > 메일 발송: 월요일 정기 소식 메일의 상태·우편 주소·실제 발송 스위치·미리보기·시험 발송·광고 띠 집계
class AdminEmailDigestController extends Controller
{
    public function status()
    {
        $eligible = EmailConsent::eligibleQuery();
        $total = (clone $eligible)->count();
        $byState = (clone $eligible)->select('state', DB::raw('COUNT(*) c'))->groupBy('state')->orderByDesc('c')->limit(12)->get()
            ->map(fn($r) => ['state' => $r->state ?: '(주 미입력)', 'count' => (int) $r->c]);
        $week = WeeklyDigest::weekKey();
        $stats = DB::table('email_ad_stats')->where('stat_date', '>=', now()->subDays(28)->toDateString())
            ->select('banner_ad_id', DB::raw('SUM(sent) sent'), DB::raw('SUM(clicks) clicks'))->groupBy('banner_ad_id')->orderByDesc('sent')->limit(15)->get();
        $titles = BannerAd::whereIn('id', $stats->pluck('banner_ad_id'))->pluck('title', 'id');
        return response()->json(['success' => true, 'data' => [
            'live' => WeeklyDigest::isLive(),
            'live_switch' => WeeklyDigest::setting(WeeklyDigest::SETTING_LIVE) === '1',
            'lock_reason' => WeeklyDigest::lockReason(),
            'postal_address' => WeeklyDigest::postalAddress(),
            'from' => config('mail.from.address'),
            'schedule' => '매주 월요일, 받는 사람 현지 시각 오전 ' . WeeklyDigest::SEND_HOUR . '시',
            'eligible_total' => $total,
            'consented_total' => User::where('marketing_email_consent', true)->count(),
            'by_state' => $byState,
            'week_key' => $week,
            'sent_this_week' => DB::table('email_digest_sends')->where('week_key', $week)->where('status', 'sent')->count(),
            'failed_this_week' => DB::table('email_digest_sends')->where('week_key', $week)->where('status', 'failed')->count(),
            'ad_stats' => $stats->map(fn($s) => ['id' => $s->banner_ad_id, 'title' => $titles[$s->banner_ad_id] ?? '(삭제된 광고)', 'sent' => (int) $s->sent, 'clicks' => (int) $s->clicks]),
        ]]);
    }

    /** 우편 주소 저장(빈 값이면 실제 발송 스위치도 함께 끔) */
    public function saveAddress(Request $request)
    {
        $data = $request->validate(['postal_address' => 'nullable|string|max:200']);
        $addr = trim((string) ($data['postal_address'] ?? ''));
        WeeklyDigest::save(WeeklyDigest::SETTING_ADDRESS, $addr);
        if ($addr === '') WeeklyDigest::save(WeeklyDigest::SETTING_LIVE, '0');
        return $this->status();
    }

    /** 실제 발송 스위치 — 우편 주소가 없으면 켤 수 없다 */
    public function setLive(Request $request)
    {
        $on = $request->validate(['live' => 'required|boolean'])['live'];
        if ($on && WeeklyDigest::postalAddress() === '') {
            return response()->json(['success' => false, 'message' => '우편 주소를 먼저 저장해야 실제 발송을 켤 수 있어요.'], 422);
        }
        WeeklyDigest::save(WeeklyDigest::SETTING_LIVE, $on ? '1' : '0');
        return $this->status();
    }

    /** 미리보기(HTML) — 선택한 주 기준으로 광고 띠가 어떻게 뽑히는지 확인 */
    public function preview(Request $request)
    {
        $data = WeeklyDigest::payload(null, $request->query('state') ?: 'GA', null, true);
        return response()->json(['success' => true, 'html' => view('emails.weekly-digest', $data)->render(), 'ads' => count($data['ads'])]);
    }

    /** 시험 발송 — 입력한 주소 한 곳으로만 보낸다(광고 노출 집계·발송 기록에는 반영하지 않음) */
    public function test(Request $request)
    {
        $d = $request->validate(['email' => 'required|email', 'state' => 'nullable|string|max:2']);
        $user = User::where('email', $d['email'])->first();
        $data = WeeklyDigest::payload($user, $d['state'] ?? null, null, true);
        try {
            Mail::to($d['email'])->send(new WeeklyDigestMail($data));
        } catch (\Throwable $e) {
            \Log::warning('[주간 소식 메일] 시험 발송 실패: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => '시험 메일을 보내지 못했어요: ' . mb_substr($e->getMessage(), 0, 120)], 500);
        }
        return response()->json(['success' => true, 'message' => $d['email'] . ' 로 시험 메일을 보냈어요(광고 ' . count($data['ads']) . '개).']);
    }
}
