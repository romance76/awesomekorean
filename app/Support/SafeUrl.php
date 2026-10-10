<?php

namespace App\Support;

/**
 * 관리자가 넣는 링크 주소 검사: 사이트 안 경로("/로 시작")나 http(s) 주소만 허용한다.
 * javascript:, data:, vbscript: 같은 주소는 눌렀을 때 스크립트가 실행되므로 저장 단계에서 막는다.
 */
class SafeUrl
{
    public static function ok($url): bool
    {
        if ($url === null || $url === '') return true;
        if (!is_string($url) || strlen($url) > 1000) return false;
        $u = trim($url);
        if (preg_match('/[\x00-\x1F\x7F]/', $u)) return false;               // 제어문자(줄바꿈 등)
        if ($u[0] === '/') return !str_starts_with($u, '//');                  // 사이트 안 경로 ('//' 로 시작하면 외부 주소라 제외)
        return (bool) preg_match('#^https?://[^\s<>"\']+$#i', $u);
    }
}
