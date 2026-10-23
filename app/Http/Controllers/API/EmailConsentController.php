<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\EmailConsent;
use Illuminate\Http\Request;

// 광고·소식 메일 수신 동의: 마이페이지 스위치, 안내 배너 응답, 메일 속 수신 거부 링크
class EmailConsentController extends Controller
{
    public function show(Request $request)
    {
        $u = $request->user();
        return response()->json(['success' => true, 'data' => [
            'consent' => (bool) $u->marketing_email_consent,
            'consent_at' => $u->marketing_consent_at,
            'asked' => (bool) $u->marketing_consent_asked_at,
            'verified' => (bool) $u->email_verified_at,
            'state' => $u->state, 'zipcode' => $u->zipcode,
        ]]);
    }

    /** 마이페이지 스위치 / 안내 배너의 "받을게요"·"안 받아요" */
    public function update(Request $request)
    {
        $data = $request->validate(['consent' => 'required|boolean', 'source' => 'nullable|in:profile,banner']);
        $u = $request->user();
        $source = $data['source'] ?? 'profile';
        if ($data['consent']) EmailConsent::grant($u, $source, $request->ip());
        else EmailConsent::revoke($u, $source, $request->ip());
        $u->refresh();
        return response()->json(['success' => true, 'data' => [
            'consent' => (bool) $u->marketing_email_consent, 'consent_at' => $u->marketing_consent_at, 'asked' => true,
        ]]);
    }

    /** 안내 배너의 "나중에" — 동의 상태는 그대로 두고 일정 기간 다시 묻지 않기 위해 표시만 남김 */
    public function later(Request $request)
    {
        $u = $request->user();
        if (!$u->marketing_consent_asked_at) $u->forceFill(['marketing_consent_asked_at' => now()])->save();
        return response()->json(['success' => true]);
    }

    /** 메일 맨 아래 수신 거부 링크(서명 URL) — 로그인 없이 한 번 누르면 끊김. POST 는 메일 앱의 "원클릭 수신 거부" 용 */
    public function unsubscribe(Request $request, User $user)
    {
        if ($user->marketing_email_consent) EmailConsent::revoke($user, 'unsubscribe_link', $request->ip());
        if ($request->isMethod('post')) return response()->json(['success' => true]);
        return redirect('/email-unsubscribed');
    }
}
