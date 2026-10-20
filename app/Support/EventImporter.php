<?php

namespace App\Support;

use App\Models\Event;
use App\Models\SiteSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * 외부 지역 행사 가져오기 — Ticketmaster Discovery API (콘서트·스포츠·공연·가족 행사 등).
 *
 * - 주(State)별로 앞으로 N일 치 행사를 가져와 events 테이블에 넣는다(source='ticketmaster').
 * - 같은 행사는 (source, source_id)로 찾아 갱신한다. 관리자가 숨긴(is_active=false) 행사는 다시 켜지 않는다.
 * - 외부 행사는 완료 인증 보상(Entry·포인트)을 주지 않는다(reward_*=0).
 * - API 키: 관리자 화면에서 저장(암호화)하거나 .env 의 TICKETMASTER_API_KEY. 화면 저장값이 우선.
 */
class EventImporter
{
    public const SOURCE = 'ticketmaster';
    private const KEY_SETTING = 'ticketmaster_api_key';
    private const ENDPOINT = 'https://app.ticketmaster.com/discovery/v2/events.json';
    private const WINDOW_DAYS = 30;     // 한 번에 30일씩 끊어서 요청(요청 하나당 최대 1000건 제한 회피)
    private const MAX_PAGES = 5;        // 창 하나당 최대 5쪽 × 200건 (API 깊은 쪽수 제한 1000건)
    // 행사가 아닌 상품(주차·업그레이드·시즌권·멤버십·구장 투어 등)은 가져오지 않는다
    private const SKIP_TITLE = '/\b(parking|shuttle|upgrade|add[- ]?on|camping|season pass|season ticket|membership|gift card|premium seating package|club seats?|suite rental|stadium tours?)\b|^tours?:/i';
    // 제목이 "… Pass" 로 끝나는 정기권(축제 이용권은 제외)
    private const SKIP_PASS = '/\bpass$/i';

    // 티켓마스터 분류(segment) → 우리 카테고리
    private const SEGMENT_MAP = [
        'music' => 'concert',
        'sports' => 'sports',
        'arts & theatre' => 'concert',
        'film' => 'concert',
        'miscellaneous' => 'festival',
    ];

    public static function apiKey(): ?string
    {
        try {
            $v = SiteSetting::where('key', self::KEY_SETTING)->value('value');
            if ($v) return Crypt::decryptString($v);
        } catch (\Throwable $e) {
            report($e);
        }
        return config('services.ticketmaster.key') ?: null;
    }

    public static function keySource(): string
    {
        try {
            if (SiteSetting::where('key', self::KEY_SETTING)->value('value')) return 'admin';
        } catch (\Throwable $e) {}
        return config('services.ticketmaster.key') ? 'env' : 'none';
    }

    public static function maskedKey(): ?string
    {
        $k = self::apiKey();
        if (!$k) return null;
        return substr($k, 0, 3) . str_repeat('•', 6) . substr($k, -3);
    }

    public static function saveKey(?string $key): void
    {
        if ($key === null || trim($key) === '') {
            SiteSetting::where('key', self::KEY_SETTING)->delete();
            return;
        }
        SiteSetting::updateOrCreate(
            ['key' => self::KEY_SETTING],
            ['value' => Crypt::encryptString(trim($key)), 'group' => 'events']
        );
    }

    /**
     * 한 주의 행사를 가져온다.
     * @return array{fetched:int,created:int,updated:int,error:?string}
     */
    public static function importState(string $code, int $days = 90): array
    {
        $out = ['fetched' => 0, 'created' => 0, 'updated' => 0, 'error' => null];
        $key = self::apiKey();
        if (!$key) { $out['error'] = 'API 키가 없어요'; return self::record($code, $out); }

        $from = Carbon::now('UTC')->startOfHour();
        $end = $from->copy()->addDays($days);
        try {
            for ($ws = $from->copy(); $ws < $end; $ws->addDays(self::WINDOW_DAYS)) {
                $we = $ws->copy()->addDays(self::WINDOW_DAYS);
                if ($we > $end) $we = $end->copy();
                $page = 0; $totalPages = 1;
                while ($page < min($totalPages, self::MAX_PAGES)) {
                    $res = Http::timeout(20)->retry(2, 1500, throw: false)->get(self::ENDPOINT, [
                        'apikey' => $key,
                        'countryCode' => 'US',
                        'stateCode' => $code,
                        'size' => 200,
                        'page' => $page,
                        'sort' => 'date,asc',
                        'startDateTime' => $ws->format('Y-m-d\TH:i:s\Z'),
                        'endDateTime' => $we->format('Y-m-d\TH:i:s\Z'),
                        'includeTBA' => 'no',
                        'includeTest' => 'no',
                    ]);
                    if ($res->status() === 401) { $out['error'] = 'API 키가 올바르지 않아요(401)'; return self::record($code, $out); }
                    if ($res->status() === 429) { $out['error'] = '요청 한도를 넘었어요(429) — 잠시 뒤 다시 시도하세요'; return self::record($code, $out); }
                    if (!$res->ok()) { $out['error'] = 'Ticketmaster 응답 오류 ' . $res->status(); return self::record($code, $out); }

                    $json = $res->json();
                    $totalPages = (int) ($json['page']['totalPages'] ?? 1);
                    foreach (($json['_embedded']['events'] ?? []) as $e) {
                        $row = self::map($e, $code);
                        if (!$row) continue;
                        $out['fetched']++;
                        self::upsert($row) ? $out['created']++ : $out['updated']++;
                    }
                    $page++;
                    usleep(250000); // 초당 5회 제한 안쪽으로
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $out['error'] = mb_substr('오류: ' . $e->getMessage(), 0, 280);
        }
        return self::record($code, $out);
    }

    private static function record(string $code, array $out): array
    {
        DB::table('event_import_states')->where('code', $code)->update([
            'last_run_at' => now(),
            'last_fetched' => $out['fetched'], 'last_created' => $out['created'], 'last_updated' => $out['updated'],
            'last_error' => $out['error'], 'updated_at' => now(),
        ]);
        return $out;
    }

    /** 티켓마스터 행사 1건 → events 칸 배열 (쓸 수 없는 행사는 null) */
    public static function map(array $e, string $stateFallback = ''): ?array
    {
        $id = $e['id'] ?? null;
        $title = trim((string) ($e['name'] ?? ''));
        if (!$id || $title === '' || preg_match(self::SKIP_TITLE, $title)) return null;
        if (preg_match(self::SKIP_PASS, $title) && !preg_match('/fest/i', $title)) return null;
        if (strtolower($e['dates']['status']['code'] ?? '') === 'cancelled') return null;

        $v = $e['_embedded']['venues'][0] ?? [];
        $tz = $v['timezone'] ?? ($e['dates']['timezone'] ?? 'UTC');
        $startRaw = $e['dates']['start']['dateTime'] ?? null;
        try {
            if ($startRaw) {
                $start = Carbon::parse($startRaw);
            } elseif (!empty($e['dates']['start']['localDate'])) {
                $start = Carbon::parse($e['dates']['start']['localDate'] . ' ' . ($e['dates']['start']['localTime'] ?? '00:00:00'), $tz);
            } else {
                return null;
            }
        } catch (\Throwable $ex) { return null; }
        $start = $start->setTimezone(config('app.timezone'));

        $cls = $e['classifications'][0] ?? [];
        $segment = strtolower($cls['segment']['name'] ?? '');
        $genre = $cls['genre']['name'] ?? null;
        $sub = $cls['subGenre']['name'] ?? null;
        $category = self::SEGMENT_MAP[$segment] ?? 'festival';
        if (preg_match('/festival|fair|expo|parade/i', $title)) $category = 'festival';

        // 이미지: 16:9 중 폭 640 이상 가장 작은 것, 없으면 첫 번째
        $img = null;
        $imgs = $e['images'] ?? [];
        usort($imgs, fn ($a, $b) => ($a['width'] ?? 0) <=> ($b['width'] ?? 0));
        foreach ($imgs as $i) { if (($i['ratio'] ?? '') === '16_9' && ($i['width'] ?? 0) >= 640) { $img = $i['url']; break; } }
        $img = $img ?: ($imgs[0]['url'] ?? null);

        $price = $e['priceRanges'][0]['min'] ?? null;
        $kind = collect([$cls['segment']['name'] ?? null, $genre, $sub])->filter(fn ($x) => $x && strtolower($x) !== 'undefined')->unique()->implode(' · ');
        $info = trim(implode("\n\n", array_filter([$e['info'] ?? null, $e['pleaseNote'] ?? null])));
        $content = trim(($kind ? "{$kind}\n\n" : '') . $info . "\n\n출처: Ticketmaster — 티켓·상세 정보는 아래 '티켓·상세 보기'에서 확인하세요.");

        return [
            'source_id' => (string) $id,
            'title' => mb_substr($title, 0, 250),
            'description' => mb_substr($kind ?: 'Ticketmaster 행사', 0, 250),
            'content' => mb_substr($content, 0, 4000),
            'image_url' => $img,
            'category' => $category,
            'organizer' => mb_substr($e['promoter']['name'] ?? 'Ticketmaster', 0, 250),
            'venue' => isset($v['name']) ? mb_substr($v['name'], 0, 250) : null,
            'address' => isset($v['address']['line1']) ? mb_substr($v['address']['line1'], 0, 250) : null,
            'city' => isset($v['city']['name']) ? mb_substr($v['city']['name'], 0, 100) : null,
            'state' => $v['state']['stateCode'] ?? $stateFallback,
            'zipcode' => isset($v['postalCode']) ? mb_substr($v['postalCode'], 0, 10) : null,
            'lat' => $v['location']['latitude'] ?? null,
            'lng' => $v['location']['longitude'] ?? null,
            'start_date' => $start,
            'price' => $price,
            'url' => $e['url'] ?? null,
        ];
    }

    /** @return bool 새로 만들었으면 true, 갱신이면 false */
    private static function upsert(array $row): bool
    {
        $ev = Event::where('source', self::SOURCE)->where('source_id', $row['source_id'])->first();
        if ($ev) {
            // 관리자가 바꾼 것(숨김·카테고리·고정)은 건드리지 않고 외부 정보만 새로 고침
            unset($row['category']);
            $ev->update($row);
            return false;
        }
        Event::create($row + [
            'source' => self::SOURCE, 'user_id' => null, 'is_active' => true,
            'reward_entries' => 0, 'reward_points' => 0, 'is_free' => false,
        ]);
        return true;
    }

    /** 지난 가져온 행사 정리 (끝난 지 7일 지났고 참가자가 없는 것만) */
    public static function prune(): int
    {
        $n = Event::where('source', self::SOURCE)->where('start_date', '<', now()->subDays(7))
            ->where('attendee_count', 0)->delete();
        // 예전에 들어온 비행사 상품(시즌권·구장 투어 등)도 함께 정리
        $n += Event::where('source', self::SOURCE)->where('attendee_count', 0)->where(function ($q) {
            foreach (['%season pass%', '%season ticket%', '%membership%', '%gift card%', 'tour:%', 'tours:%', '%stadium tour%', '% pass'] as $like) {
                $q->orWhere('title', 'like', $like);
            }
        })->where('title', 'not like', '%fest%')->delete();
        return $n;
    }
}
