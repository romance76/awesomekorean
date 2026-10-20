<?php

namespace App\Support;

use App\Models\Event;
use App\Models\SiteSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * 외부 지역 행사 가져오기 — Ticketmaster Discovery API (콘서트·스포츠·공연·축제 등).
 *
 * - 주(State)마다 "가장 인기 있는" 행사만 N개(기본 12, 관리자 화면에서 조절) 가져온다.
 *   분류(음악·스포츠·공연·기타)별로 인기순 후보를 받아 몫을 나눠 골고루 고르고, 같은 투어·팀의 중복 일정은 하나만 둔다.
 * - 한국어로 보여 준다: 분류·장르를 한글로, 일시·장소·티켓 가격을 한글 안내로 만들고, 영어 소개문은 자동 번역(캐시).
 * - 같은 행사는 (source, source_id)로 찾아 갱신한다. 관리자가 숨긴(is_active=false) 행사는 다시 켜지 않는다.
 * - 이번에 고른 목록에서 빠진 이전 가져오기 행사는 지운다(참가자가 있는 것은 남김).
 * - 외부 행사는 완료 인증 보상(Entry·포인트)을 주지 않는다(reward_*=0).
 * - API 키: 관리자 화면에서 저장(암호화)하거나 .env 의 TICKETMASTER_API_KEY. 화면 저장값이 우선.
 */
class EventImporter
{
    public const SOURCE = 'ticketmaster';
    public const DEFAULT_PER_STATE = 12;
    private const KEY_SETTING = 'ticketmaster_api_key';
    private const PER_STATE_SETTING = 'event_import_per_state';
    private const ENDPOINT = 'https://app.ticketmaster.com/discovery/v2/events.json';

    // 분류별 후보 요청: 우리 카테고리, 가져올 몫(비율), 인기순 후보 수
    private const SEGMENTS = [
        ['name' => 'Music', 'category' => 'concert', 'weight' => 0.35, 'size' => 60],
        ['name' => 'Sports', 'category' => 'sports', 'weight' => 0.25, 'size' => 50],
        ['name' => 'Arts & Theatre', 'category' => 'concert', 'weight' => 0.10, 'size' => 40],
        ['name' => 'Miscellaneous', 'category' => 'concert', 'weight' => 0.15, 'size' => 40],   // 순회 쇼·가족 공연 등
        ['name' => 'Fairs & Festivals', 'category' => 'festival', 'weight' => 0.15, 'size' => 30], // 진짜 축제·페어
    ];

    // 행사가 아닌 상품(주차·업그레이드·시즌권·멤버십·구장 투어 등)은 가져오지 않는다
    private const SKIP_TITLE = '/\b(parking|shuttle|upgrade|add[- ]?on|camping|season pass|season ticket|membership|gift card|premium seating package|club seats?|suite rental|stadium tours?|vip|hospitality|suites?)\b|^tours?:/i';
    // 제목이 "… Pass" 로 끝나는 정기권(축제 이용권은 제외)
    private const SKIP_PASS = '/\bpass$/i';

    private const SEGMENT_KO = [
        'music' => '음악', 'sports' => '스포츠', 'arts & theatre' => '공연·예술', 'film' => '영화', 'miscellaneous' => '기타 행사',
    ];
    private const GENRE_KO = [
        'rock' => '록', 'pop' => '팝', 'hip-hop/rap' => '힙합/랩', 'r&b' => 'R&B', 'country' => '컨트리', 'metal' => '메탈',
        'alternative' => '얼터너티브', 'dance/electronic' => '댄스/일렉트로닉', 'classical' => '클래식', 'jazz' => '재즈',
        'latin' => '라틴', 'folk' => '포크', 'reggae' => '레게', 'k-pop' => 'K-POP', 'blues' => '블루스', 'religious' => '종교 음악',
        'world' => '월드뮤직', 'basketball' => '농구', 'baseball' => '야구', 'football' => '풋볼', 'hockey' => '하키', 'soccer' => '축구',
        'theatre' => '연극', 'musical' => '뮤지컬', 'comedy' => '코미디', 'family' => '가족', 'dance' => '무용', 'opera' => '오페라',
        'circus & specialty acts' => '서커스·특별 공연', 'magic & illusion' => '마술', 'fairs & festivals' => '페어·축제',
        'wrestling' => '레슬링', 'motorsports/racing' => '모터스포츠', 'golf' => '골프', 'tennis' => '테니스', 'ice skating' => '아이스 스케이팅',
        'hobby/special interest expos' => '박람회·전시', 'community/civic' => '지역 행사', 'other' => '',
    ];
    private const WEEKDAY_KO = ['일', '월', '화', '수', '목', '금', '토'];

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

    /** 주마다 가져올 행사 수 (3~40, 기본 12) */
    public static function perState(): int
    {
        $v = (int) SiteSetting::where('key', self::PER_STATE_SETTING)->value('value');
        return ($v >= 3 && $v <= 40) ? $v : self::DEFAULT_PER_STATE;
    }

    public static function savePerState(int $n): void
    {
        SiteSetting::updateOrCreate(['key' => self::PER_STATE_SETTING], ['value' => (string) max(3, min(40, $n)), 'group' => 'events']);
    }

    /**
     * 한 주의 대표 행사를 가져온다.
     * @return array{fetched:int,created:int,updated:int,removed:int,error:?string}
     */
    public static function importState(string $code, int $days = 90): array
    {
        $out = ['fetched' => 0, 'created' => 0, 'updated' => 0, 'removed' => 0, 'error' => null];
        $key = self::apiKey();
        if (!$key) { $out['error'] = 'API 키가 없어요'; return self::record($code, $out); }

        $from = Carbon::now('UTC')->startOfHour();
        $end = $from->copy()->addDays($days);
        $pools = [];
        $seen = [];
        try {
            foreach (self::SEGMENTS as $i => $seg) {
                $res = Http::timeout(20)->retry(2, 1500, throw: false)->get(self::ENDPOINT, [
                    'apikey' => $key,
                    'countryCode' => 'US',
                    'stateCode' => $code,
                    'classificationName' => $seg['name'],
                    'size' => $seg['size'],
                    'sort' => 'relevance,desc',
                    'startDateTime' => $from->format('Y-m-d\TH:i:s\Z'),
                    'endDateTime' => $end->format('Y-m-d\TH:i:s\Z'),
                    'includeTBA' => 'no',
                    'includeTest' => 'no',
                ]);
                if ($res->status() === 401) { $out['error'] = 'API 키가 올바르지 않아요(401)'; return self::record($code, $out); }
                if ($res->status() === 429) { $out['error'] = '요청 한도를 넘었어요(429) — 잠시 뒤 다시 시도하세요'; return self::record($code, $out); }
                if (!$res->ok()) { $out['error'] = 'Ticketmaster 응답 오류 ' . $res->status(); return self::record($code, $out); }

                $withImg = []; $noImg = [];
                foreach (($res->json()['_embedded']['events'] ?? []) as $e) {
                    $c = self::map($e, $code, $seg['category']);
                    if (!$c || isset($seen[$c['group']])) continue;   // 같은 투어·팀의 다른 날짜는 하나만(분류 묶음이 달라도 중복 제거)
                    $seen[$c['group']] = true;
                    if ($c['row']['image_url']) $withImg[] = $c; else $noImg[] = $c;
                }
                $pools[$i] = array_merge($withImg, $noImg);   // 이미지 있는 행사 먼저(인기순 유지)
                usleep(250000); // 초당 5회 제한 안쪽으로
            }

            $kept = [];
            foreach (self::pick($pools, self::perState()) as $c) {
                $row = self::finalize($c);
                $out['fetched']++;
                self::upsert($row) ? $out['created']++ : $out['updated']++;
                $kept[] = $row['source_id'];
            }
            // 이번 목록에서 빠진 예전 가져오기 행사 정리(참가자가 있는 것은 남김)
            if ($kept) {
                $out['removed'] = Event::where('source', self::SOURCE)->where('state', $code)
                    ->whereNotIn('source_id', $kept)->where('attendee_count', 0)->delete();
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

    /** 분류별 몫(비율)대로 인기순 후보에서 고르고, 모자라면 남은 후보로 채운 뒤 날짜순으로 */
    private static function pick(array $pools, int $n): array
    {
        $quota = [];
        foreach (self::SEGMENTS as $i => $seg) $quota[$i] = (int) floor($n * $seg['weight']);
        $rem = $n - array_sum($quota);
        for ($i = 0; $rem > 0; $i = ($i + 1) % count(self::SEGMENTS)) { $quota[$i]++; $rem--; }

        $picked = []; $left = [];
        foreach ($pools as $i => $rows) {
            $picked = array_merge($picked, array_slice($rows, 0, $quota[$i]));
            $left = array_merge($left, array_slice($rows, $quota[$i]));
        }
        if (count($picked) < $n) $picked = array_merge($picked, array_slice($left, 0, $n - count($picked)));
        usort($picked, fn ($a, $b) => $a['row']['start_date'] <=> $b['row']['start_date']);
        return $picked;
    }

    /**
     * 티켓마스터 행사 1건 → ['row' => events 기본 칸, 'group' => 중복 묶음 키, ...한글 안내를 만들 재료]
     * (쓸 수 없는 행사는 null)
     */
    public static function map(array $e, string $stateFallback = '', string $category = 'festival'): ?array
    {
        $id = $e['id'] ?? null;
        $title = trim((string) ($e['name'] ?? ''));
        if (!$id || $title === '' || preg_match(self::SKIP_TITLE, $title)) return null;
        if (preg_match(self::SKIP_PASS, $title) && !preg_match('/fest/i', $title)) return null;
        $status = strtolower($e['dates']['status']['code'] ?? '');
        if (in_array($status, ['cancelled', 'canceled'], true)) return null;

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

        $cls = $e['classifications'][0] ?? [];
        // 카테고리는 요청한 후보 묶음이 아니라 행사 자신의 주 분류(segment)로 정한다
        // ("기타" 묶음에도 스포츠 경기 등이 섞여 내려오기 때문)
        $category = self::categoryFor($cls['segment']['name'] ?? '', $cls['genre']['name'] ?? '', $title) ?? $category;

        // 이미지: 16:9 중 폭 640 이상 가장 작은 것, 없으면 가장 큰 것
        $img = null;
        $imgs = $e['images'] ?? [];
        usort($imgs, fn ($a, $b) => ($a['width'] ?? 0) <=> ($b['width'] ?? 0));
        foreach ($imgs as $i) { if (($i['ratio'] ?? '') === '16_9' && ($i['width'] ?? 0) >= 640) { $img = $i['url'] ?? null; break; } }
        $img = $img ?: ($imgs ? ($imgs[count($imgs) - 1]['url'] ?? null) : null);

        $pr = $e['priceRanges'][0] ?? [];
        $attractions = collect($e['_embedded']['attractions'] ?? []);
        $group = $attractions->first()['id'] ?? mb_strtolower(preg_replace('/[^a-z0-9]+/i', '', $title));

        return [
            'group' => (string) $group,
            'tz' => $tz,
            'start' => $start,
            'segment' => $cls['segment']['name'] ?? '',
            'genre' => $cls['genre']['name'] ?? '',
            'sub' => $cls['subGenre']['name'] ?? '',
            'performers' => $attractions->pluck('name')->filter()->unique()->take(6)->values()->all(),
            'info' => trim((string) ($e['info'] ?? '')),
            'note' => trim((string) ($e['pleaseNote'] ?? '')),
            'min' => $pr['min'] ?? null,
            'max' => $pr['max'] ?? null,
            'row' => [
                'source_id' => (string) $id,
                'title' => mb_substr($title, 0, 250),
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
                'start_date' => $start->copy()->setTimezone(config('app.timezone')),
                'price' => $pr['min'] ?? null,
                'url' => $e['url'] ?? null,
            ],
        ];
    }

    /** Ticketmaster 분류 → 우리 카테고리 (모르면 null) */
    public static function categoryFor(string $segment, string $genre, string $title): ?string
    {
        $seg = strtolower(trim($segment));
        $gen = strtolower(trim($genre));
        if ($seg === 'sports') return 'sports';
        if (in_array($gen, ['basketball', 'baseball', 'football', 'hockey', 'soccer', 'wrestling', 'motorsports/racing', 'golf', 'tennis', 'ice skating', 'boxing', 'volleyball', 'lacrosse', 'rugby', 'track & field', 'martial arts'], true)) return 'sports';
        if (preg_match('/festival|\bfair\b|\bexpo\b|parade/i', $title)) return 'festival';
        if (in_array($gen, ['fairs & festivals', 'community/civic', 'hobby/special interest expos'], true)) return 'festival';
        if (in_array($gen, ['lecture/seminar', 'educational'], true)) return 'education';
        // 음악·공연·영화와 "기타"(Disney On Ice 같은 순회 쇼·가족 공연)는 모두 보러 가는 공연
        if (in_array($seg, ['music', 'arts & theatre', 'film', 'miscellaneous'], true)) return 'concert';
        return null;
    }

    /** 한글 안내문(일시·장소·티켓·소개)을 붙여 events 한 줄로 완성 */
    private static function finalize(array $c): array
    {
        $row = $c['row'];
        $kind = collect([$c['segment'], $c['genre'], $c['sub']])
            ->filter(fn ($x) => $x && strtolower($x) !== 'undefined')
            ->map(fn ($x) => self::ko($x))->filter()->unique()->implode(' · ');

        $local = $c['start']->copy()->setTimezone($c['tz']);
        $h = (int) $local->format('G');
        $when = sprintf('%d년 %d월 %d일(%s) %s %d:%s', $local->year, $local->month, $local->day,
            self::WEEKDAY_KO[(int) $local->format('w')], $h < 12 ? '오전' : '오후', ($h % 12) ?: 12, $local->format('i'));

        $where = collect([$row['venue'], $row['address'], $row['city'], trim(($row['state'] ?? '') . ' ' . ($row['zipcode'] ?? ''))])
            ->filter()->implode(', ');

        if ($c['min'] !== null) {
            $fmt = fn ($n) => '$' . rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
            $ticket = $fmt($c['min']) . (($c['max'] !== null && (float) $c['max'] > (float) $c['min']) ? ' ~ ' . $fmt($c['max']) : '') . ' (수수료 별도)';
        } else {
            $ticket = '가격은 Ticketmaster 에서 확인하세요';
        }

        $lines = [];
        if ($kind) $lines[] = "🎵 분류: {$kind}";
        if ($c['performers'] && !(count($c['performers']) === 1 && mb_strtolower($c['performers'][0]) === mb_strtolower($row['title']))) {
            $lines[] = '🎤 출연·팀: ' . implode(', ', $c['performers']);
        }
        $lines[] = "📅 일시: {$when} (현지 시간)";
        if ($where) $lines[] = "📍 장소: {$where}";
        $lines[] = "🎫 티켓: {$ticket}";
        $body = implode("\n", $lines);

        if ($c['info'])  $body .= "\n\n■ 소개\n" . self::translate($c['info']);
        if ($c['note'])  $body .= "\n\n■ 안내\n" . self::translate($c['note']);
        $body .= "\n\n※ 일정·가격·좌석은 바뀔 수 있어요. 티켓 구매와 최신 정보는 아래 '티켓·상세 보기'(Ticketmaster)에서 확인하세요.";

        $row['content'] = mb_substr($body, 0, 4000);
        $row['description'] = mb_substr(trim(($kind ? $kind . ' · ' : '') . $local->format('n/j') . ' ' . ($row['venue'] ?? '')), 0, 250);
        return $row;
    }

    private static function ko(string $name): string
    {
        $k = mb_strtolower(trim($name));
        if (isset(self::SEGMENT_KO[$k])) return self::SEGMENT_KO[$k];
        return array_key_exists($k, self::GENRE_KO) ? self::GENRE_KO[$k] : $name;
    }

    /** 영어 → 한국어 자동 번역(무료 Google 엔드포인트, 같은 문장은 60일 캐시). 실패하면 원문 그대로 */
    private static function translate(string $text): string
    {
        $text = trim(mb_substr(preg_replace('/\s+/', ' ', $text), 0, 1200));
        if ($text === '') return '';
        $cached = Cache::get('tm_ko_' . md5($text));
        if (is_string($cached) && $cached !== '') return $cached;
        try {
            $res = Http::timeout(15)->get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx', 'sl' => 'en', 'tl' => 'ko', 'dt' => 't', 'q' => $text,
            ]);
            $arr = $res->ok() ? $res->json() : null;
            if (is_array($arr) && !empty($arr[0])) {
                $out = '';
                foreach ($arr[0] as $seg) { if (isset($seg[0])) $out .= $seg[0]; }
                $out = trim($out);
                if ($out !== '') { Cache::put('tm_ko_' . md5($text), $out, now()->addDays(60)); usleep(150000); return $out; }
            }
        } catch (\Throwable $e) {}
        return $text;
    }

    /** @return bool 새로 만들었으면 true, 갱신이면 false */
    private static function upsert(array $row): bool
    {
        $ev = Event::where('source', self::SOURCE)->where('source_id', $row['source_id'])->first();
        if ($ev) {
            $ev->update($row);   // 숨김(is_active)·참가자 수 등은 건드리지 않음
            return false;
        }
        Event::create($row + [
            'source' => self::SOURCE, 'user_id' => null, 'is_active' => true,
            'reward_entries' => 0, 'reward_points' => 0, 'is_free' => false,
        ]);
        return true;
    }

    /** 지난 가져온 행사 정리 (끝난 지 7일 지났고 참가자가 없는 것만) + 행사가 아닌 상품 */
    public static function prune(): int
    {
        $n = Event::where('source', self::SOURCE)->where('start_date', '<', now()->subDays(7))
            ->where('attendee_count', 0)->delete();
        $n += Event::where('source', self::SOURCE)->where('attendee_count', 0)->where(function ($q) {
            foreach (['%season pass%', '%season ticket%', '%membership%', '%gift card%', 'tour:%', 'tours:%', '%stadium tour%', '% pass', '%suites -%', '% vip %', '%hospitality%'] as $like) {
                $q->orWhere('title', 'like', $like);
            }
        })->where('title', 'not like', '%fest%')->delete();
        return $n;
    }
}
