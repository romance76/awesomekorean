<?php

namespace App\Console\Commands;

use App\Support\TwoFactor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 비상 해제 — 폰과 복구 코드를 모두 잃어 관리자 로그인이 막혔을 때, 서버에서 그 계정의 2단계 인증을 끈다.
 * 사용: php artisan user:2fa-reset 이메일주소
 * (서버에 접속할 수 있는 사람만 실행할 수 있다. 비밀번호는 그대로이고 2단계 인증 정보만 지운다.)
 */
class User2faReset extends Command
{
    protected $signature = 'user:2fa-reset {email : 해제할 계정의 이메일}';
    protected $description = '계정의 2단계 인증을 끈다(폰·복구 코드를 잃어 로그인이 막혔을 때)';

    public function handle(): int
    {
        $u = DB::table('users')->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $this->argument('email')))])->first(['id', 'email', 'role']);
        if (!$u) { $this->error('그 이메일의 계정이 없어요'); return self::FAILURE; }
        TwoFactor::clear((int) $u->id);
        Log::warning('[2fa] reset from console', ['target' => $u->id]);
        $this->info("{$u->email} ({$u->role}) 의 2단계 인증을 껐어요. 이제 비밀번호만으로 로그인할 수 있어요.");
        return self::SUCCESS;
    }
}
