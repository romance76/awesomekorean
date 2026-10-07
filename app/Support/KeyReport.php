<?php

namespace App\Support;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * API 키를 등록/수정/삭제할 때마다 관리자 메일로 전체 키 현황을 정리해서 보낸다.
 * 키 값이 그대로 들어 있으니 받는 메일함(관리자 본인)만 안전하게 관리할 것. 발송 실패가 키 저장을 막지는 않는다.
 */
class KeyReport
{
    public static function recipient(): string
    {
        return config('services.admin_report_email') ?: 'romance76@gmail.com';
    }

    public static function send(string $action, string $who): void
    {
        try {
            $et = now('America/New_York')->format('Y-m-d H:i') . ' (미국 동부)';
            $lines = ["관리자 정보 — API 키 변경 알림", "", "변경 내용: {$action}", "변경한 사람: {$who}", "시각: {$et}", "", "── 현재 등록된 전체 키 ──"];
            foreach (ApiKey::orderBy('service')->get() as $k) {
                $lines[] = sprintf("[%s] %s  (%s, %s)", $k->service, $k->name, $k->is_active ? '사용중' : '꺼짐', optional($k->updated_at)->setTimezone('America/New_York')->format('m/d H:i'));
                $lines[] = '    ' . ($k->api_key ?: '(비어 있음)');
            }
            $lines[] = "";
            $lines[] = "※ DB 에는 암호화되어 저장됩니다. 이 메일은 키 백업용이니 본인 메일함 밖으로 공유하지 마세요.";
            $body = implode("\n", $lines);
            Mail::raw($body, function ($m) use ($action) {
                $m->to(self::recipient())->subject('[어썸코리안] 관리자 정보 - API 키 변경: ' . mb_substr($action, 0, 40));
            });
        } catch (\Throwable $e) {
            Log::warning('API 키 변경 알림 메일 발송 실패: ' . $e->getMessage());
        }
    }
}
