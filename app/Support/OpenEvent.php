<?php

namespace App\Support;

use App\Models\SiteSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 오픈 이벤트(소프트 오픈 프로모션) — 원래 가격/포인트 설정은 건드리지 않고, 그 위에 "덧씌우는" 방식.
 * 관리자 "오픈 이벤트" 화면에서 켜고 끄며, 기간(미국 동부 날짜)이 지나면 자동으로 원래대로 돌아온다.
 *
 *  - earn      : 포인트 적립 배수 (point_settings 의 category='earn' 규칙을 읽을 때 곱함. 하루 한도(*_daily_max)는 건수 기준이라 그대로)
 *  - items     : 포인트로 쓰는 항목별 할인율(0~100, 100=무료) / flyer_usd 는 달러 결제 할인율
 *  - photos    : 장터·부동산 무료 사진 장수(설정값과 비교해 큰 쪽)
 *  - purchase_bonus : 포인트 구매 시 추가 보너스(%)
 */
class OpenEvent
{
    public const KEY = 'open_event';
    private const CACHE = 'open_event_cfg_v1';
    private const TZ = 'America/New_York';

    /** 항목 목록(화면 표시 이름, 기본 적용 여부/할인율) */
    public const ITEMS = [
        'chat_create' => ['label' => '채팅방 개설 (1:1·그룹·공개)', 'on' => true, 'pct' => 100],
        'chat_entry' => ['label' => '공개 채팅방 입장·자동연장 (24시간)', 'on' => false, 'pct' => 0],
        'bump' => ['label' => '장터 끌어올리기', 'on' => true, 'pct' => 90],
        'promotion' => ['label' => '상위노출 (구인·장터·부동산·업소·동호회 등)', 'on' => true, 'pct' => 90],
        'banner' => ['label' => '배너·텍스트 광고 신청/입찰', 'on' => true, 'pct' => 90],
        'flyer_usd' => ['label' => 'NEW 전단 광고 (달러 결제)', 'on' => true, 'pct' => 90],
    ];

    private static ?array $memo = null;      // [timestamp, config]
    private static ?array $stateMemo = null; // [timestamp, state]

    public static function defaults(): array
    {
        $items = [];
        foreach (self::ITEMS as $k => $v) $items[$k] = ['on' => $v['on'], 'pct' => $v['pct']];
        return [
            'enabled' => false,                 // 기본은 꺼짐 — 관리자가 "적용"을 눌러야 켜진다
            'starts_on' => '2026-10-15',        // 미국 동부 날짜(포함)
            'ends_on' => '2026-11-30',
            'earn' => ['on' => true, 'multiplier' => 2],
            'items' => $items,
            'photos' => ['on' => true, 'count' => 5],
            'purchase_bonus' => ['on' => true, 'pct' => 10],
            'headline' => '오픈 기념 이벤트',
            'subline' => '포인트 2배 · 채팅방 무료 · 광고 90% 할인',
        ];
    }

    /** 저장된 값을 안전한 범위로 정리해서 기본값 위에 합친다 */
    public static function normalize(array $in): array
    {
        $d = self::defaults();
        $out = $d;

        $out['enabled'] = (bool) ($in['enabled'] ?? $d['enabled']);
        foreach (['starts_on', 'ends_on'] as $k) {
            $v = (string) ($in[$k] ?? $d[$k]);
            $out[$k] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : $d[$k];
        }
        if (strcmp($out['ends_on'], $out['starts_on']) < 0) $out['ends_on'] = $out['starts_on'];

        $out['earn'] = [
            'on' => (bool) ($in['earn']['on'] ?? $d['earn']['on']),
            'multiplier' => max(1, min(10, (int) ($in['earn']['multiplier'] ?? $d['earn']['multiplier']))),
        ];
        foreach (self::ITEMS as $k => $_) {
            $row = $in['items'][$k] ?? [];
            $max = $k === 'flyer_usd' ? 95 : 100;   // 달러 결제는 0원이 되지 않게 95% 까지
            $out['items'][$k] = [
                'on' => (bool) ($row['on'] ?? $d['items'][$k]['on']),
                'pct' => max(0, min($max, (int) ($row['pct'] ?? $d['items'][$k]['pct']))),
            ];
        }
        $out['photos'] = [
            'on' => (bool) ($in['photos']['on'] ?? $d['photos']['on']),
            'count' => max(0, min(30, (int) ($in['photos']['count'] ?? $d['photos']['count']))),
        ];
        $out['purchase_bonus'] = [
            'on' => (bool) ($in['purchase_bonus']['on'] ?? $d['purchase_bonus']['on']),
            'pct' => max(0, min(100, (int) ($in['purchase_bonus']['pct'] ?? $d['purchase_bonus']['pct']))),
        ];
        $out['headline'] = mb_substr(trim((string) ($in['headline'] ?? $d['headline'])), 0, 40) ?: $d['headline'];
        $out['subline'] = mb_substr(trim((string) ($in['subline'] ?? $d['subline'])), 0, 100);
        return $out;
    }

    public static function config(): array
    {
        // 요청 안에서 여러 번 불러도 DB/캐시를 덜 치도록 20초만 기억 (장시간 도는 큐 워커 대비)
        if (self::$memo && time() - self::$memo[0] < 20) return self::$memo[1];
        $cfg = Cache::remember(self::CACHE, 60, function () {
            try {
                $raw = SiteSetting::where('key', self::KEY)->value('value');
            } catch (\Throwable $e) {
                $raw = null;
            }
            $saved = $raw ? json_decode($raw, true) : [];
            return self::normalize(is_array($saved) ? $saved : []);
        });
        self::$memo = [time(), $cfg];
        return $cfg;
    }

    public static function save(array $input): array
    {
        $cfg = self::normalize($input);
        SiteSetting::updateOrCreate(['key' => self::KEY], ['value' => json_encode($cfg, JSON_UNESCAPED_UNICODE), 'group' => 'event']);
        self::flush();
        return $cfg;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE);
        Cache::forget('open_event_earn_keys');
        self::$memo = null;
        self::$stateMemo = null;
    }

    /** off(꺼짐) / scheduled(예정) / active(진행 중) / ended(종료) — 날짜는 미국 동부 기준 */
    public static function state(): string
    {
        if (self::$stateMemo && time() - self::$stateMemo[0] < 20) return self::$stateMemo[1];
        $c = self::config();
        if (!$c['enabled']) {
            $s = 'off';
        } else {
            $now = Carbon::now(self::TZ);
            $start = Carbon::parse($c['starts_on'], self::TZ)->startOfDay();
            $end = Carbon::parse($c['ends_on'], self::TZ)->endOfDay();
            $s = $now->lt($start) ? 'scheduled' : ($now->gt($end) ? 'ended' : 'active');
        }
        self::$stateMemo = [time(), $s];
        return $s;
    }

    public static function active(): bool
    {
        return self::state() === 'active';
    }

    // ───────── 적립 배수 ─────────

    /** 적립으로 취급하는 규칙 키: point_settings 의 category='earn' + 아래 보강 키 (하루 한도 *_daily_max 는 제외) */
    private static function earnKeys(): array
    {
        return Cache::remember('open_event_earn_keys', 300, function () {
            try {
                $keys = DB::table('point_settings')->where('category', 'earn')->pluck('key')->all();
            } catch (\Throwable $e) {
                $keys = [];
            }
            return array_values(array_unique(array_merge($keys, ['daily_login_bonus', 'like_reward_amount'])));
        });
    }

    /** PointRules::get() 가 값을 돌려주기 직전에 호출 — 적립 규칙이면 배수를 곱한다 */
    public static function rule(string $key, int $value): int
    {
        if ($value <= 0 || !self::active()) return $value;
        $earn = self::config()['earn'];
        if (!$earn['on'] || $earn['multiplier'] <= 1) return $value;
        if (str_ends_with($key, '_daily_max')) return $value;
        return in_array($key, self::earnKeys(), true) ? $value * $earn['multiplier'] : $value;
    }

    public static function earnMultiplier(): int
    {
        if (!self::active()) return 1;
        $e = self::config()['earn'];
        return $e['on'] ? max(1, $e['multiplier']) : 1;
    }

    // ───────── 할인 ─────────

    public static function discountPct(string $item): int
    {
        if (!self::active()) return 0;
        $row = self::config()['items'][$item] ?? null;
        return ($row && $row['on']) ? (int) $row['pct'] : 0;
    }

    /** 금액에 항목 할인율을 적용 (할인이 없으면 그대로). $min 은 할인 후 최소값(0원이 되면 안 되는 달러 결제용) */
    public static function apply(int $amount, string $item, int $min = 0): int
    {
        $pct = self::discountPct($item);
        if ($pct <= 0 || $amount <= 0) return $amount;
        return max($min, (int) round($amount * (100 - $pct) / 100));
    }

    // ───────── 사진 / 구매 보너스 ─────────

    public static function freePhotos(int $current): int
    {
        if (!self::active()) return $current;
        $p = self::config()['photos'];
        return $p['on'] ? max($current, (int) $p['count']) : $current;
    }

    public static function purchaseBonusPct(): int
    {
        if (!self::active()) return 0;
        $b = self::config()['purchase_bonus'];
        return $b['on'] ? (int) $b['pct'] : 0;
    }

    // ───────── 화면 표시용 ─────────

    /** 이벤트 내용을 한 줄씩 요약 (사이트 안내 띠, 관리자 미리보기 공용) */
    public static function perks(?array $c = null): array
    {
        $c = $c ?: self::config();
        $out = [];
        if ($c['earn']['on'] && $c['earn']['multiplier'] > 1) $out[] = "포인트 적립 {$c['earn']['multiplier']}배";
        foreach (self::ITEMS as $k => $meta) {
            $row = $c['items'][$k];
            if (!$row['on'] || $row['pct'] <= 0) continue;
            $short = [
                'chat_create' => '채팅방 개설', 'chat_entry' => '공개 채팅방 입장', 'bump' => '끌어올리기',
                'promotion' => '상위노출', 'banner' => '광고 신청', 'flyer_usd' => 'NEW 전단 광고',
            ][$k];
            $out[] = $row['pct'] >= 100 ? "{$short} 무료" : "{$short} {$row['pct']}% 할인";
        }
        if ($c['photos']['on'] && $c['photos']['count'] > 0) $out[] = "사진 {$c['photos']['count']}장까지 무료";
        if ($c['purchase_bonus']['on'] && $c['purchase_bonus']['pct'] > 0) $out[] = "포인트 구매 +{$c['purchase_bonus']['pct']}% 보너스";
        return $out;
    }

    /** 공개용(사이트 안내 띠): 진행 중일 때만 내용을 돌려준다 */
    public static function publicInfo(): array
    {
        $state = self::state();
        if ($state !== 'active') return ['active' => false];
        $c = self::config();
        return [
            'active' => true,
            'headline' => $c['headline'],
            'subline' => $c['subline'],
            'ends_on' => $c['ends_on'],
            'perks' => self::perks($c),
        ];
    }
}
