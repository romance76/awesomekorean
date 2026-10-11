<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SiteSetting extends Model
{
    protected $fillable = ['key','value','group'];

    // 점검 모드·가입 허용·이메일 인증·비밀번호 길이가 저장·삭제되면 읽기 캐시를 바로 비운다 — 어떤 경로로 바꿔도 즉시 반영(점검을 끄면 곧바로 풀림)
    protected static function booted(): void
    {
        $forget = function (self $setting) {
            if (in_array($setting->key, \App\Support\SiteFlags::KEYS, true)) \App\Support\SiteFlags::forget();
        };
        static::saved($forget);
        static::deleted($forget);
    }
}
