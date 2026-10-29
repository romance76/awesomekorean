<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\Outreach;
use Illuminate\Http\Request;

// 손님 메일의 수신 거부: 서명된 주소(만료 없음)라 로그인 없이 가능.
// 메일 속 버튼은 확인 페이지(/outreach-unsubscribe)를 열고, 손님이 체크한 뒤 "확인"을 누르면 여기로 POST 한다.
// 메일 앱의 "원클릭 수신 거부"(List-Unsubscribe-Post)도 같은 주소로 POST 한다.
class OutreachUnsubscribeController extends Controller
{
    public function unsubscribe(Request $request)
    {
        $email = mb_strtolower(trim((string) $request->query('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) abort(404);
        Outreach::suppress($email, 'unsubscribe_link', $request->ip());
        return response()->json(['success' => true]);
    }
}
