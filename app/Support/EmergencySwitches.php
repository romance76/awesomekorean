<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 비상 스위치 — 최고관리자가 한 번에 "일시 중지"를 켜고 끄는 사이트 전체 스위치.
 *
 * 값은 site_settings 의 한 칸('emergency_<이름>_paused')에 '1'(멈춤) / '0'(정상) 으로 저장한다.
 * 스위치를 읽다가 오류가 나면 "정상(멈추지 않음)"으로 본다 — 스위치 때문에 가입·스케줄이 멈추는 일이 없게.
 *
 * 쓰는 곳
 *  - signup_bonus          : AuthController / SocialAuthController (가입 포인트·Entry), SweepstakesAutomation::onSignup (N번째 가입 경품)
 *  - sweepstakes_schedules : SweepstakesAutomation::runDueSchedules / autoDraw (반복 일정 이벤트 생성·자동 추첨)
 *  - auto_collect          : routes/console.php 의 음악·뉴스·숏츠 자동 수집 스케줄 (관리자가 직접 누르는 수집은 그대로 가능)
 */
class EmergencySwitches
{
    public const GROUP = 'emergency';

    /** 화면에 보여 줄 스위치 목록 (순서 = 화면 순서). 문구는 전부 쉬운 한국어 존댓말 */
    public const SWITCHES = [
        'signup_bonus' => [
            'setting' => 'emergency_signup_bonus_paused',
            'label' => '가입 보너스 지급',
            'icon' => 'gift',
            'when_paused' => '새로 가입하는 회원에게 주는 가입 포인트·Entry와 "매 N번째 가입" 경품 당첨이 멈춰요. 가입 자체는 그대로 돼요.',
            'when_running' => '새로 가입한 회원에게 가입 보너스가 정상으로 지급돼요.',
            'confirm_pause' => '가입 보너스 지급을 멈출까요?' . "\n\n" . '멈추는 동안 새로 가입하는 회원은 가입 포인트·Entry를 받지 못하고, "매 N번째 가입" 경품 순번에도 세지지 않아요. 가입 자체는 막지 않아요.',
            'confirm_resume' => '가입 보너스 지급을 다시 시작할까요?' . "\n\n" . '지금부터 새로 가입하는 회원에게 보너스가 지급돼요. 멈춰 있는 동안 가입한 회원에게는 소급해서 주지 않아요.',
        ],
        'sweepstakes_schedules' => [
            'setting' => 'emergency_sweepstakes_schedules_paused',
            'label' => '경품 반복 일정',
            'icon' => 'calendar',
            'when_paused' => '반복 일정으로 새 경품 이벤트가 만들어지는 것과 자동 추첨이 모두 멈춰요. 이미 열린 경품과 응모 내역은 그대로예요.',
            'when_running' => '반복 일정이 정해 둔 시각에 맞춰 정상으로 돌아가요.',
            'confirm_pause' => '경품 반복 일정을 전부 멈출까요?' . "\n\n" . '새 경품 이벤트 자동 생성과 자동 추첨이 멈춰요. 이미 열려 있는 경품과 응모 내역은 그대로 남아요.',
            'confirm_resume' => '경품 반복 일정을 다시 시작할까요?' . "\n\n" . '멈춰 있는 동안 끝난 경품은 곧바로 자동 추첨이 이어지고, 밀린 반복 일정은 한 번만 새로 만들어져요.',
        ],
        'auto_collect' => [
            'setting' => 'emergency_auto_collect_paused',
            'label' => '자동 수집 (음악·뉴스·숏츠)',
            'icon' => 'refresh',
            'when_paused' => '음악·뉴스·숏츠를 정해진 시간에 자동으로 가져오는 일이 멈춰요. 이미 모은 글은 그대로예요. 관리자가 직접 누르는 수집 버튼은 계속 쓸 수 있어요.',
            'when_running' => '음악·뉴스·숏츠가 정해진 시간마다 자동으로 수집돼요.',
            'confirm_pause' => '자동 수집을 멈출까요?' . "\n\n" . '음악·뉴스·숏츠 자동 수집이 멈춰요. 이미 모은 글은 지워지지 않고, 관리자가 직접 누르는 수집 버튼은 계속 쓸 수 있어요.',
            'confirm_resume' => '자동 수집을 다시 시작할까요?' . "\n\n" . '다음 정해진 시각부터 음악·뉴스·숏츠가 자동으로 수집돼요.',
        ],
    ];

    public static function settingKey(string $name): ?string
    {
        return self::SWITCHES[$name]['setting'] ?? null;
    }

    /** 스위치가 켜져 있나(= 일시 중지 중인가). 모르는 이름이거나 읽기 오류면 false(정상) */
    public static function on(string $name): bool
    {
        $key = self::settingKey($name);
        if (!$key) return false;
        try {
            if (!Schema::hasTable('site_settings')) return false;
            $v = DB::table('site_settings')->where('key', $key)->value('value');
            return in_array((string) $v, ['1', 'true', 'on'], true);
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }

    /** 스위치를 켜거나(true = 멈춤) 끈다(false = 정상). 바뀌기 전 상태를 돌려준다 */
    public static function set(string $name, bool $paused): bool
    {
        $key = self::settingKey($name);
        if (!$key) throw new \InvalidArgumentException('알 수 없는 스위치');
        $before = self::on($name);
        $now = now();
        $exists = DB::table('site_settings')->where('key', $key)->exists();
        if ($exists) {
            DB::table('site_settings')->where('key', $key)->update(['value' => $paused ? '1' : '0', 'updated_at' => $now]);
        } else {
            DB::table('site_settings')->insert(['key' => $key, 'value' => $paused ? '1' : '0', 'group' => self::GROUP, 'created_at' => $now, 'updated_at' => $now]);
        }
        return $before;
    }
}
