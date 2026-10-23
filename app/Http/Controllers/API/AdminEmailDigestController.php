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
            'notices' => [
                'digest' => WeeklyDigest::setting(WeeklyDigest::noticeKey('digest', 'ko')), 'digest_en' => WeeklyDigest::setting(WeeklyDigest::noticeKey('digest', 'en')),
                'flyer' => WeeklyDigest::setting(WeeklyDigest::noticeKey('flyer', 'ko')), 'flyer_en' => WeeklyDigest::setting(WeeklyDigest::noticeKey('flyer', 'en')),
            ],
            'flyer_schedule' => \App\Support\FlyerMailer::schedule(),
            'en_members' => (clone $eligible)->where('language', 'en')->count(),
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

    /** 안내 문구 저장 — type: digest(월요일 소식 메일) | flyer(전면광고 메일). 비우면 메일에 나오지 않는다 */
    public function saveNotice(Request $request)
    {
        $d = $request->validate(['type' => 'required|in:digest,flyer', 'lang' => 'nullable|in:ko,en', 'text' => 'nullable|string|max:600']);
        WeeklyDigest::save(WeeklyDigest::noticeKey($d['type'], WeeklyDigest::normLang($d['lang'] ?? 'ko')), trim((string) ($d['text'] ?? '')));
        return $this->status();
    }

    /** 미리보기 안에서 직접 고친 문구 저장. key: intro | ad_title (빈 값이면 기본 문구로 되돌림) */
    public function saveText(Request $request)
    {
        $d = $request->validate(['type' => 'required|in:digest,flyer', 'lang' => 'nullable|in:ko,en', 'key' => 'required|string', 'value' => 'nullable|string|max:200']);
        $typeKey = $d['type'] . (($d['lang'] ?? 'ko') === 'en' ? '_en' : '');
        if (!array_key_exists($d['key'], WeeklyDigest::TEXT_DEFAULTS[$typeKey] ?? [])) {
            return response()->json(['success' => false, 'message' => '고칠 수 없는 문구예요.'], 422);
        }
        WeeklyDigest::saveText($typeKey, $d['key'], (string) ($d['value'] ?? ''));
        return $this->status();
    }

    /** 미리보기(HTML). type=digest 는 광고 띠가 어떻게 뽑히는지, type=flyer 는 전면광고 메일 모양을 본다. sample=1 이면 샘플 광고/샘플 전면광고로 채운다 */
    public function preview(Request $request)
    {
        $type = $request->query('type') === 'flyer' ? 'flyer' : 'digest';
        $sample = $request->boolean('sample');
        $mail = WeeklyDigest::testMailable($type, null, $request->query('state') ?: 'GA', $sample, $request->query('lang'));
        $view = $type === 'flyer' ? 'emails.flyer-ad' : 'emails.weekly-digest';
        $data = $mail->data; $data['editMode'] = true;   // 미리보기에서만 "직접 고치기" 표시가 붙는다(실제 메일·시험 메일에는 없음)
        return response()->json(['success' => true, 'html' => view($view, $data)->render(), 'ads' => count($mail->data['ads'] ?? []), 'sample_flyer' => $type === 'flyer' ? (bool) $mail->data['sample'] : false]);
    }

    /** 시험 발송 — 입력한 주소 한 곳으로만 보낸다(광고 노출 집계·발송 기록에는 반영하지 않음) */
    public function test(Request $request)
    {
        $d = $request->validate(['email' => 'required|email', 'state' => 'nullable|string|max:2', 'type' => 'nullable|in:digest,flyer', 'lang' => 'nullable|in:ko,en', 'sample' => 'nullable|boolean']);
        $user = User::where('email', $d['email'])->first();
        $mail = WeeklyDigest::testMailable($d['type'] ?? 'digest', $user, $d['state'] ?? null, (bool) ($d['sample'] ?? false), $d['lang'] ?? 'ko');
        $data = $mail->data;
        try {
            Mail::to($d['email'])->send($mail);
        } catch (\Throwable $e) {
            \Log::warning('[주간 소식 메일] 시험 발송 실패: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => '시험 메일을 보내지 못했어요: ' . mb_substr($e->getMessage(), 0, 120)], 500);
        }
        $extra = ($d['type'] ?? 'digest') === 'flyer' ? '(전면광고 메일' . (!empty($data['sample']) ? ' · 샘플 내용' : '') . ')' : '(광고 ' . count($data['ads']) . '개)';
        return response()->json(['success' => true, 'message' => $d['email'] . ' 로 시험 메일을 보냈어요' . $extra . '.']);
    }
}
