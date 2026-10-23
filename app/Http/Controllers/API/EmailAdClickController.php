<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BannerAd;
use App\Support\WeeklyDigest;

// 정기 메일 맨 아래 광고 띠의 배너를 눌렀을 때: 클릭을 세고 광고주가 정한 주소로 보낸다.
// 받는 사람이 누구인지는 기록하지 않는다(광고별 클릭 수만).
class EmailAdClickController extends Controller
{
    public function click(int $id)
    {
        $ad = BannerAd::find($id);
        $to = $ad ? trim((string) $ad->link_url) : '';
        if (!$ad || $to === '') return redirect('/');
        $internal = str_starts_with($to, '/') && !str_starts_with($to, '//');   // 사이트 안 주소는 허용, 그 밖에는 http(s) 만
        if (!$internal && !preg_match('#^https?://#i', $to)) return redirect('/');
        WeeklyDigest::recordClick($ad->id);
        $ad->increment('clicks');
        return $internal ? redirect($to) : redirect()->away($to);
    }
}
