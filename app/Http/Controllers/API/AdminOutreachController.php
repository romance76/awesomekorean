<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\OutreachMail;
use App\Support\Outreach;
use App\Support\WeeklyDigest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

// 관리자 > 손님 메일: 주소 붙여 넣기 → 대기열, 소개 메일 문구, 실제 발송 스위치·하루 한도, 시험 발송, 수신 거부 목록
class AdminOutreachController extends Controller
{
    /** 문구 입력 검사 규칙(저장·미리보기 공통). 자세한 정리(길이·HTML)는 Outreach::normalize 가 한 번 더 한다 */
    private const TEXT_RULES = [
        'texts' => 'nullable|array',
        'texts.*.subject' => 'nullable|string|max:300', 'texts.*.hero_title' => 'nullable|string|max:300', 'texts.*.hero_sub' => 'nullable|string|max:400',
        'texts.*.body' => 'nullable|string|max:20000', 'texts.*.features_title' => 'nullable|string|max:200', 'texts.*.button' => 'nullable|string|max:200',
        'texts.*.closing' => 'nullable|string|max:1500',
        'texts.*.promo_show' => 'nullable|in:0,1', 'texts.*.promo_badge' => 'nullable|string|max:100', 'texts.*.promo_title' => 'nullable|string|max:300',
        'texts.*.promo_text' => 'nullable|string|max:1000', 'texts.*.promo_button' => 'nullable|string|max:200', 'texts.*.promo_note' => 'nullable|string|max:800',
        'texts.*.features' => 'nullable|array|max:12', 'texts.*.features.*' => 'array',
        'texts.*.styles' => 'nullable|array', 'texts.*.features.*.link' => 'nullable|string|max:300', 'texts.*.features.*.h' => 'nullable|integer|min:0|max:2000', 'texts.*.card_cols' => 'nullable|in:2,3,4', 'texts.*.icon_pos' => 'nullable|in:top,inline',
        'texts.*.button_link' => 'nullable|string|max:300', 'texts.*.promo_link' => 'nullable|string|max:300',
        'texts.*.features.*.icon' => 'nullable|string|max:30', 'texts.*.features.*.title' => 'nullable|string|max:200', 'texts.*.features.*.desc' => 'nullable|string|max:600',
    ];

    public function status()
    {
        $counts = DB::table('outreach_recipients')->select('status', DB::raw('COUNT(*) c'))->groupBy('status')->pluck('c', 'status');
        $lang = Outreach::lang();
        return response()->json(['success' => true, 'data' => [
            'live' => Outreach::isLive(),
            'live_switch' => Outreach::setting(Outreach::SETTING_LIVE) === '1',
            'lock_reason' => Outreach::lockReason(),
            'postal_address' => WeeklyDigest::postalAddress(),
            'from' => config('mail.from.address'),
            'daily_cap' => Outreach::dailyCap(),
            'sent_today' => Outreach::sentToday(),
            'lang' => $lang,
            'texts' => ['ko' => Outreach::texts('ko'), 'en' => Outreach::texts('en')],
            'defaults' => Outreach::DEFAULTS,
            'counts' => ['queued' => (int) ($counts['queued'] ?? 0), 'sent' => (int) ($counts['sent'] ?? 0), 'failed' => (int) ($counts['failed'] ?? 0)],
            'suppressed_total' => DB::table('email_suppressions')->count(),
            'recent' => DB::table('outreach_recipients')->orderByRaw("FIELD(status, 'queued', 'failed', 'sent')")->orderBy('id')->limit(2000)->get(['id', 'email', 'status', 'sent_count', 'last_sent_at', 'error']),
            'suppressed' => DB::table('email_suppressions')->orderByDesc('id')->limit(300)->get(['email', 'source', 'created_at']),
        ]]);
    }

    /** 붙여 넣은 주소를 대기열에 넣는다 */
    public function add(Request $request)
    {
        $d = $request->validate(['emails' => 'required|string|max:60000', 'resend' => 'nullable|boolean']);
        $r = Outreach::enqueue($d['emails'], (bool) ($d['resend'] ?? false));
        return response()->json(['success' => true, 'result' => $r, 'data' => $this->status()->getData(true)['data']]);
    }

    /** 문구·발송 언어·하루 한도 저장 */
    public function saveSettings(Request $request)
    {
        $d = $request->validate([
            'lang' => 'nullable|in:ko,en,both', 'daily_cap' => 'nullable|integer|min:1|max:2000',
        ] + self::TEXT_RULES);
        if (!empty($d['lang'])) Outreach::save(Outreach::SETTING_LANG, $d['lang']);
        if (!empty($d['daily_cap'])) Outreach::save(Outreach::SETTING_CAP, (string) $d['daily_cap']);
        foreach (['ko', 'en'] as $l) if (isset($d['texts'][$l])) Outreach::saveTexts($l, $d['texts'][$l]);
        return $this->status();
    }

    /** 실제 발송 스위치 — 우편 주소가 없으면 켤 수 없다 */
    public function setLive(Request $request)
    {
        $on = $request->validate(['live' => 'required|boolean'])['live'];
        if ($on && WeeklyDigest::postalAddress() === '') {
            return response()->json(['success' => false, 'message' => '우편 주소를 먼저 저장해야 실제 발송을 켤 수 있어요. (관리자 › 메일 발송 화면)'], 422);
        }
        Outreach::save(Outreach::SETTING_LIVE, $on ? '1' : '0');
        return $this->status();
    }

    /** 대기열에서 빼기(대기 중인 주소만) */
    public function remove(Request $request, int $id)
    {
        DB::table('outreach_recipients')->where('id', $id)->where('status', 'queued')->delete();
        return $this->status();
    }

    /** 대기 중인 주소 전부 비우기 */
    public function clearQueue()
    {
        DB::table('outreach_recipients')->where('status', 'queued')->delete();
        return $this->status();
    }

    /** 수신 거부 목록에 직접 추가(전화·답장으로 "보내지 말라"고 한 주소) */
    public function suppress(Request $request)
    {
        $d = $request->validate(['emails' => 'required|string|max:20000']);
        [$emails] = Outreach::parse($d['emails']);
        foreach ($emails as $e) Outreach::suppress($e, 'admin', $request->ip());
        return $this->status();
    }

    /** 미리보기(HTML) — 저장하지 않은 화면의 문구(texts)로도 만들어 볼 수 있다 */
    public function preview(Request $request)
    {
        $d = $request->validate(['lang' => 'nullable|in:ko,en,both', 'edit' => 'nullable|boolean'] + self::TEXT_RULES);
        $lang = $d['lang'] ?? Outreach::lang();
        $data = Outreach::payload('sample@example.com', false, $lang, $d['texts'] ?? null, (bool) ($d['edit'] ?? false));
        $data['unsubscribeUrl'] = url('/outreach-unsubscribe?sample=1');
        return response()->json(['success' => true, 'subject' => $data['subject'], 'html' => view('emails.outreach', $data)->render()]);
    }

    /** 시험 발송 — 입력한 주소 한 곳으로만(잠겨 있어도 가능, 대기열·하루 한도에는 반영하지 않음) */
    public function test(Request $request)
    {
        $d = $request->validate(['email' => 'required|email', 'lang' => 'nullable|in:ko,en,both']);
        try {
            Mail::to($d['email'])->send(new OutreachMail(Outreach::payload(mb_strtolower($d['email']), true, $d['lang'] ?? null)));
        } catch (\Throwable $e) {
            \Log::warning('[손님 메일] 시험 발송 실패: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => '시험 메일을 보내지 못했어요: ' . mb_substr($e->getMessage(), 0, 120)], 500);
        }
        return response()->json(['success' => true, 'message' => $d['email'] . ' 로 시험 메일을 보냈어요.']);
    }
}
