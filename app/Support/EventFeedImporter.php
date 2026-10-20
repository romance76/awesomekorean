<?php

namespace App\Support;

use App\Models\Event;
use App\Models\SiteSetting;
use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * 공식 지역 행사 피드 가져오기 — 관광청·시청·카운티가 공개한 행사 중 "한인이 갈 만한 큰 행사"만 골라 매주 일요일에 한 번.
 *
 * 출처(event_feed_sources)마다 형식이 달라서 4가지로 읽는다:
 *   tribe(WordPress Events Calendar 공개 API) · socrata_nycparks(뉴욕시 공개 데이터) · ics(iCal) ·
 *   sitemap(사이트맵의 행사 페이지에 들어 있는 JSON-LD) · microdata(행사 페이지 마이크로데이터)
 * 고르는 규칙: 중요도 점수(축제·공연·퍼레이드 가산, 한국/아시아 관련 가산, 수업·모임·회의 감점)가 기준 이상인 행사를 점수순으로,
 *   주 10개 · 카운티 3개 · 도시 2개를 넘지 않게, 제목+날짜가 같거나 이미 있는 행사(Ticketmaster 포함)와 겹치지 않게.
 * 예의: 사이트 robots.txt 의 Disallow 는 따르고, 요청 사이 간격을 두며, 출처 이름과 원본 링크를 반드시 남긴다.
 */
class EventFeedImporter
{
    public const SOURCE = 'feed';
    public const DEFAULT_PER_STATE = 10;
    public const DEFAULT_PER_COUNTY = 3;
    public const DEFAULT_PER_CITY = 2;
    private const UA = 'awesomekorean.com-community-events/1.0 (+https://awesomekorean.com)';
    private const MAX_PAGES_PER_SOURCE = 160;   // 사이트맵 출처에서 한 번에 열어 볼 행사 페이지 수(제목에 축제·공연 등이 있는 것 먼저)
    private const WINDOW_DAYS = 120;            // 앞으로 며칠 안의 행사만
    private const MIN_SCORE = 4;

    private const STATE_TZ = [
        'CA' => 'America/Los_Angeles', 'WA' => 'America/Los_Angeles', 'OR' => 'America/Los_Angeles', 'NV' => 'America/Los_Angeles',
        'AZ' => 'America/Phoenix', 'HI' => 'Pacific/Honolulu', 'CO' => 'America/Denver', 'UT' => 'America/Denver',
        'IL' => 'America/Chicago', 'TX' => 'America/Chicago', 'MN' => 'America/Chicago', 'MO' => 'America/Chicago', 'WI' => 'America/Chicago',
        'AL' => 'America/Chicago', 'TN' => 'America/Chicago', 'OK' => 'America/Chicago', 'KS' => 'America/Chicago', 'IA' => 'America/Chicago',
    ];
    private const WEEKDAY_KO = ['일', '월', '화', '수', '목', '금', '토'];

    // 중요도 점수 — 제목(+분류) 기준 가산
    private const BOOST = [
        '/\b(festival|fest)\b/i' => 5,
        '/\b(parade|carnival|fireworks|lantern|night market|marathon|expo|fair)\b/i' => 4,
        '/\b(concert|symphony|orchestra|ballet|opera|musical|broadway|live in|tour)\b/i' => 3,
        '/\b(celebration|heritage|holiday|christmas|halloween|oktoberfest|thanksgiving|new year|lights|food truck|market)\b/i' => 2,
        '/\b(game|vs\.?|championship|tournament|race|5k|rodeo)\b/i' => 2,
    ];
    private const KOREAN = [
        '/\b(korean|korea|k-?pop|hangul|hanbok|chuseok|seollal|lunar new year|k-?town|koreatown|taekwondo|k-?drama)\b/i' => 8,
        '/\b(asian|aapi|apia|pan-?asian|asia)\b/i' => 4,
    ];
    private const PENALTY = '/\b(happy hour|trivia|bingo|yoga|pilates|zumba|fitness|exercise|class(es)?|lesson|workshop|storytime|story time|meeting|hearing|commission|board|council|committee|support group|open mic|game night|networking|webinar|training|seminar|volunteer|litter|cleanup|clinic|office hours|orientation|walking tour|farmers? market|book club|craft(s)? night|paint(ing)? night)\b/i';

    public static function limits(): array
    {
        $get = function (string $key, int $default, int $min, int $max) {
            $v = (int) SiteSetting::where('key', $key)->value('value');
            return ($v >= $min && $v <= $max) ? $v : $default;
        };
        return [
            'per_state' => $get('event_feed_per_state', self::DEFAULT_PER_STATE, 1, 40),
            'per_county' => $get('event_feed_per_county', self::DEFAULT_PER_COUNTY, 1, 20),
            'per_city' => $get('event_feed_per_city', self::DEFAULT_PER_CITY, 1, 10),
        ];
    }

    public static function saveLimits(int $state, int $county, int $city): void
    {
        foreach (['event_feed_per_state' => max(1, min(40, $state)), 'event_feed_per_county' => max(1, min(20, $county)), 'event_feed_per_city' => max(1, min(10, $city))] as $k => $v) {
            SiteSetting::updateOrCreate(['key' => $k], ['value' => (string) $v, 'group' => 'events']);
        }
    }

    /**
     * 켜 둔 출처(또는 한 곳)를 읽어 중요한 행사를 고르고 저장한다.
     * @return array{sources:array,selected:int,created:int,updated:int,removed:int}
     */
    public static function run(?string $onlyKey = null): array
    {
        $summary = ['sources' => [], 'selected' => 0, 'created' => 0, 'updated' => 0, 'removed' => 0];
        $q = DB::table('event_feed_sources')->orderBy('sort_order');
        $sources = $onlyKey ? $q->where('key', $onlyKey)->get() : $q->where('enabled', true)->get();
        $limits = self::limits();

        $cands = []; $okKeys = [];
        foreach ($sources as $src) {
            $res = ['key' => $src->key, 'name' => $src->name, 'candidates' => 0, 'error' => null];
            try {
                $items = self::fetchSource($src);
                foreach ($items as $it) {
                    $c = self::qualify($it, $src);
                    if ($c) { $cands[] = $c; $res['candidates']++; }
                }
                $okKeys[] = $src->key;
            } catch (\Throwable $e) {
                report($e);
                $res['error'] = mb_substr($e->getMessage(), 0, 280);
            }
            DB::table('event_feed_sources')->where('id', $src->id)->update([
                'last_run_at' => now(), 'last_candidates' => $res['candidates'], 'last_error' => $res['error'], 'updated_at' => now(),
            ]);
            $summary['sources'][$src->key] = $res;
        }

        $picked = self::select($cands, $limits);
        $keptByKey = [];
        foreach ($picked as $c) {
            $row = self::buildRow($c);
            self::upsert($row) ? $summary['created']++ : $summary['updated']++;
            $keptByKey[$c['src']->key][] = $row['source_id'];
            $summary['selected']++;
        }
        foreach ($sources as $src) {
            $n = count($keptByKey[$src->key] ?? []);
            DB::table('event_feed_sources')->where('id', $src->id)->update(['last_selected' => $n]);
            $summary['sources'][$src->key]['selected'] = $n;
            // 읽기에 성공한 출처의 예전 행사 중 이번에 안 고른 것은 정리(참가자가 있는 것은 남김)
            if (in_array($src->key, $okKeys, true)) {
                $summary['removed'] += Event::where('source', self::SOURCE)->where('source_id', 'like', $src->key . ':%')
                    ->whereNotIn('source_id', $keptByKey[$src->key] ?? ['-'])->where('attendee_count', 0)->delete();
            }
        }
        // 지난 행사 정리
        $summary['removed'] += Event::where('source', self::SOURCE)->where('attendee_count', 0)
            ->whereRaw('COALESCE(end_date, start_date) < ?', [now()->subDays(3)])->delete();
        return $summary;
    }

    // ─────────────────────────── 읽기 ───────────────────────────

    private static function fetchSource(object $src): array
    {
        $cfg = json_decode((string) $src->config, true) ?: [];
        switch ($src->type) {
            case 'tribe': return self::fetchTribe($src, $cfg);
            case 'socrata_nycparks': return self::fetchNycParks($src, $cfg);
            case 'ics': return self::fetchIcs($src, $cfg);
            case 'sitemap': return self::fetchPages($src, $cfg, 'ld');
            case 'microdata': return self::fetchPages($src, $cfg, 'micro');
        }
        throw new \RuntimeException('알 수 없는 형식: ' . $src->type);
    }

    private static array $robots = [];

    /** robots.txt 의 Disallow(User-agent: *)를 따른다 */
    private static function allowed(string $url): bool
    {
        $p = parse_url($url);
        if (empty($p['host']) || empty($p['scheme'])) return true;
        $host = $p['scheme'] . '://' . $p['host'];
        if (!isset(self::$robots[$host])) {
            $rules = [];
            try {
                $r = Http::withHeaders(['User-Agent' => self::UA])->timeout(10)->get($host . '/robots.txt');
                if ($r->ok()) {
                    $apply = false;
                    foreach (preg_split('/\r?\n/', $r->body()) as $line) {
                        $line = trim((string) preg_replace('/#.*/', '', $line));
                        if ($line === '') continue;
                        if (preg_match('/^user-agent:\s*(.+)$/i', $line, $m)) { $apply = (trim($m[1]) === '*'); }
                        elseif ($apply && preg_match('/^disallow:\s*(\S+)/i', $line, $m)) { $rules[] = $m[1]; }
                    }
                }
            } catch (\Throwable $e) {}
            self::$robots[$host] = $rules;
        }
        $path = ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '');
        foreach (self::$robots[$host] as $rule) {
            $re = '#^' . str_replace(['\*', '\$'], ['.*', '$'], preg_quote($rule, '#')) . '#';
            if (preg_match($re, $path)) return false;
        }
        return true;
    }

    private static function get(string $url, int $timeout = 25): ?Response
    {
        if (!self::allowed($url)) return null;
        try {
            return Http::withHeaders(['User-Agent' => self::UA, 'Accept' => '*/*'])->timeout($timeout)->retry(1, 1000, throw: false)->get($url);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function fetchTribe(object $src, array $cfg): array
    {
        $base = rtrim($cfg['base'], '/');
        $out = [];
        $url = $base . '/wp-json/tribe/events/v1/events?' . http_build_query([
            'per_page' => 50, 'page' => 1, 'status' => 'publish',
            'start_date' => now()->format('Y-m-d'), 'end_date' => now()->addDays(self::WINDOW_DAYS)->format('Y-m-d'),
        ]);
        for ($i = 0; $i < 6 && $url; $i++) {
            $r = self::get($url);
            if (!$r || !$r->ok()) { if ($i === 0) throw new \RuntimeException('Events API 응답 없음'); break; }
            $j = $r->json();
            foreach (($j['events'] ?? []) as $e) {
                $tz = $e['timezone'] ?? (self::STATE_TZ[$src->state] ?? 'America/New_York');
                $v = (isset($e['venue']) && is_array($e['venue']) && isset($e['venue']['venue'])) ? $e['venue'] : [];
                $img = $e['image'] ?? null;
                $out[] = self::item([
                    'title' => $e['title'] ?? '', 'start' => $e['start_date'] ?? null, 'end' => $e['end_date'] ?? null, 'tz' => $tz,
                    'allday' => !empty($e['all_day']),
                    'venue' => $v['venue'] ?? null, 'address' => $v['address'] ?? null, 'city' => $v['city'] ?? null, 'state' => $v['stateprovince'] ?? ($v['state'] ?? null),
                    'zip' => $v['zip'] ?? null, 'lat' => $v['geo_lat'] ?? null, 'lng' => $v['geo_lng'] ?? null,
                    'image' => is_array($img) ? ($img['url'] ?? null) : (is_string($img) ? $img : null),
                    'url' => $e['url'] ?? null, 'desc' => $e['description'] ?? ($e['excerpt'] ?? ''), 'price' => $e['cost'] ?? null,
                    'cats' => collect($e['categories'] ?? [])->pluck('name')->implode(' | '),
                ], $src);
            }
            $url = $j['next_rest_url'] ?? null;
            usleep(300000);
        }
        return array_filter($out);
    }

    private static function fetchNycParks(object $src, array $cfg): array
    {
        $r = self::get($cfg['url'], 40);
        if (!$r || !$r->ok()) throw new \RuntimeException('뉴욕시 데이터 응답 없음');
        $out = [];
        foreach ((array) $r->json() as $e) {
            if (empty($e['starttime']) || empty($e['title'])) continue;
            [$lat, $lng] = array_pad(array_map('trim', explode(',', (string) ($e['coordinates'] ?? ''))), 2, null);
            $out[] = self::item([
                'title' => $e['title'], 'start' => $e['starttime'], 'end' => $e['endtime'] ?? null, 'tz' => 'America/New_York',
                'venue' => $e['parknames'] ?? null, 'address' => $e['location'] ?? null, 'city' => 'New York', 'state' => 'NY',
                'lat' => is_numeric($lat) ? $lat : null, 'lng' => is_numeric($lng) ? $lng : null,
                'image' => isset($e['image']['url']) ? str_replace('http://', 'https://', $e['image']['url']) : null,
                'url' => isset($e['link']['url']) ? str_replace('http://', 'https://', $e['link']['url']) : null,
                'desc' => $e['description'] ?? '', 'cats' => $e['categories'] ?? '',
            ], $src);
        }
        return array_filter($out);
    }

    private static function fetchIcs(object $src, array $cfg): array
    {
        $r = self::get($cfg['url'], 40);
        if (!$r || !$r->ok()) throw new \RuntimeException('iCal 응답 없음');
        $body = preg_replace("/\r?\n[ \t]/", '', $r->body());
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $body, $blocks);
        $defTz = self::STATE_TZ[$src->state] ?? 'America/New_York';
        $out = [];
        foreach ($blocks[1] as $blk) {
            $p = [];
            if (preg_match_all('/^([A-Z\-]+)((?:;[^:\r\n]*)*):(.*)$/m', $blk, $pm, PREG_SET_ORDER)) {
                foreach ($pm as $m) { if (!isset($p[$m[1]])) $p[$m[1]] = ['params' => $m[2], 'v' => str_replace(['\\,', '\\;', '\\n', '\\N', '\\\\'], [',', ';', "\n", "\n", '\\'], trim($m[3]))]; }
            }
            if (empty($p['SUMMARY']['v']) || empty($p['DTSTART']['v'])) continue;
            [$start, $allday] = self::icsDate($p['DTSTART'], $defTz);
            [$end] = isset($p['DTEND']) ? self::icsDate($p['DTEND'], $defTz) : [null, false];
            $loc = $p['LOCATION']['v'] ?? '';
            $city = null; $state = null; $zip = null;
            if (preg_match('/,\s*([^,]+),\s*([A-Z]{2})\s*(\d{5})?\s*$/', $loc, $lm)) { $city = trim($lm[1]); $state = $lm[2]; $zip = $lm[3] ?? null; }
            $uid = $p['UID']['v'] ?? ($p['SUMMARY']['v'] . $p['DTSTART']['v']);
            $out[] = self::item([
                'title' => $p['SUMMARY']['v'], 'start_c' => $start, 'end_c' => $end, 'allday' => $allday, 'tz' => $defTz,
                'venue' => null, 'address' => $loc ?: null, 'city' => $city, 'state' => $state, 'zip' => $zip,
                'url' => $p['URL']['v'] ?? ($cfg['url_fallback'] ?? 'https://www.seattle.gov/events') . '#' . substr(sha1($uid), 0, 12),
                'desc' => $p['DESCRIPTION']['v'] ?? '', 'cats' => $p['CATEGORIES']['v'] ?? '',
            ], $src);
        }
        return array_filter($out);
    }

    /** @return array{0:?Carbon,1:bool} */
    private static function icsDate(array $prop, string $defTz): array
    {
        $v = $prop['v'];
        try {
            if (preg_match('/^\d{8}$/', $v)) return [Carbon::createFromFormat('Ymd H:i:s', $v . ' 12:00:00', $defTz)->utc(), true];
            if (str_ends_with($v, 'Z')) return [Carbon::createFromFormat('Ymd\THis\Z', $v, 'UTC'), false];
            $tz = preg_match('/TZID=([^;:]+)/', $prop['params'], $m) ? $m[1] : $defTz;
            return [Carbon::createFromFormat('Ymd\THis', $v, $tz)->utc(), false];
        } catch (\Throwable $e) {
            return [null, false];
        }
    }

    /** 사이트맵 → 행사 페이지 → JSON-LD(또는 마이크로데이터) */
    private static function fetchPages(object $src, array $cfg, string $mode): array
    {
        $pages = self::sitemapPages($cfg);
        if (!$pages) throw new \RuntimeException('사이트맵에서 행사 페이지를 못 찾음');
        $urls = self::pickPages($pages);
        $out = [];
        foreach (array_chunk($urls, 6) as $chunk) {
            $allowedUrls = array_values(array_filter($chunk, fn ($u) => self::allowed($u)));
            if (!$allowedUrls) continue;
            try {
                $resps = Http::pool(fn (Pool $pool) => array_map(
                    fn ($u) => $pool->withHeaders(['User-Agent' => self::UA, 'Accept' => 'text/html'])->timeout(25)->get($u),
                    $allowedUrls
                ));
            } catch (\Throwable $e) { continue; }
            foreach ($allowedUrls as $i => $u) {
                $resp = $resps[$i] ?? null;
                if (!($resp instanceof Response) || !$resp->ok()) continue;
                $html = $resp->body();
                $items = $mode === 'ld' ? self::itemsFromLd($html, $u, $src) : self::itemsFromMicro($html, $u, $src);
                foreach ($items as $it) if ($it) $out[] = $it;
            }
            usleep(400000);
        }
        return $out;
    }

    private static function sitemapPages(array $cfg): array
    {
        $seeds = $cfg['sitemaps'] ?? [];
        if (!$seeds && !empty($cfg['base'])) {
            $base = rtrim($cfg['base'], '/');
            $r = self::get($base . '/robots.txt', 15);
            if ($r && $r->ok() && preg_match_all('/^\s*sitemap:\s*(\S+)/im', $r->body(), $m)) $seeds = $m[1];
            if (!$seeds) $seeds = [$base . '/sitemap.xml', $base . '/sitemap_index.xml'];
        }
        $childRe = $cfg['child_regex'] ?? 'event';
        $urlRe = $cfg['url_regex'] ?? '#/(events?|calendar)/[^/?\#]+#i';
        $pages = []; $seen = [];
        $queue = array_map(fn ($s) => [$s, 0], $seeds);
        while ($queue && count($seen) < 40) {
            [$u, $depth] = array_shift($queue);
            if (isset($seen[$u])) continue;
            $seen[$u] = true;
            $r = self::get($u, 40);
            if (!$r || !$r->ok()) continue;
            $xml = $r->body();
            if (str_contains($xml, '<sitemapindex')) {
                preg_match_all('#<sitemap>(.*?)</sitemap>#s', $xml, $bl);
                foreach ($bl[1] as $b) {
                    if ($depth < 2 && preg_match('#<loc>\s*([^<\s]+)\s*</loc>#', $b, $lm) && preg_match('#' . $childRe . '#i', $lm[1])) $queue[] = [html_entity_decode($lm[1]), $depth + 1];
                }
            } else {
                preg_match_all('#<url>(.*?)</url>#s', $xml, $bl);
                foreach ($bl[1] as $b) {
                    if (!preg_match('#<loc>\s*([^<\s]+)\s*</loc>#', $b, $lm)) continue;
                    $loc = html_entity_decode($lm[1]);
                    if (!preg_match($urlRe, $loc)) continue;
                    preg_match('#<lastmod>\s*([^<\s]+)\s*</lastmod>#', $b, $lmod);
                    $pages[$loc] = $lmod[1] ?? '';
                }
            }
        }
        return $pages;
    }

    /** 행사 페이지가 너무 많으면 주소(슬러그)에 축제·공연 등이 든 것과 최근 수정된 것 위주로 최대 N개 */
    private static function pickPages(array $pages): array
    {
        $scored = [];
        foreach ($pages as $u => $lm) {
            $slug = strtolower(urldecode(basename((string) parse_url($u, PHP_URL_PATH))));
            $scored[] = [$u, self::score(str_replace('-', ' ', $slug)), (string) $lm];
        }
        usort($scored, fn ($a, $b) => [$b[1], $b[2]] <=> [$a[1], $a[2]]);
        return array_map(fn ($x) => $x[0], array_slice($scored, 0, self::MAX_PAGES_PER_SOURCE));
    }

    private static function itemsFromLd(string $html, string $pageUrl, object $src): array
    {
        $nodes = [];
        if (preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $m)) {
            foreach ($m[1] as $raw) {
                $j = json_decode(trim($raw), true);
                if (is_array($j)) self::collectEvents($j, $nodes);
            }
        }
        $out = [];
        foreach ($nodes as $e) {
            $status = strtolower((string) ($e['eventStatus'] ?? ''));
            $mode = strtolower((string) ($e['eventAttendanceMode'] ?? ''));
            if (str_contains($status, 'cancel') || (str_contains($mode, 'online') && !str_contains($mode, 'mixed') && !str_contains($mode, 'offline'))) continue;
            $loc = $e['location'] ?? [];
            if (is_array($loc) && array_is_list($loc)) $loc = $loc[0] ?? [];
            $loc = is_array($loc) ? $loc : [];
            $addr = $loc['address'] ?? [];
            if (is_string($addr)) $addr = ['streetAddress' => $addr];
            $addr = is_array($addr) ? $addr : [];
            $img = $e['image'] ?? null;
            if (is_array($img)) $img = array_is_list($img) ? ($img[0] ?? null) : ($img['url'] ?? null);
            if (is_array($img)) $img = $img['url'] ?? null;
            $off = $e['offers'] ?? [];
            if (is_array($off) && array_is_list($off)) $off = $off[0] ?? [];
            $out[] = self::item([
                'title' => $e['name'], 'start' => self::s($e['startDate'] ?? null) ?: null, 'end' => self::s($e['endDate'] ?? null) ?: null, 'tz' => self::STATE_TZ[$src->state] ?? 'America/New_York',
                'venue' => self::s($loc['name'] ?? null) ?: null, 'address' => self::s($addr['streetAddress'] ?? null) ?: null, 'city' => self::s($addr['addressLocality'] ?? null) ?: null,
                'state' => self::s($addr['addressRegion'] ?? null) ?: null, 'zip' => self::s($addr['postalCode'] ?? null) ?: null,
                'lat' => $loc['geo']['latitude'] ?? null, 'lng' => $loc['geo']['longitude'] ?? null,
                'image' => is_string($img) ? self::absUrl($img, $pageUrl) : null,
                'url' => $pageUrl, 'desc' => self::s($e['description'] ?? ''), 'price' => is_array($off) ? ($off['price'] ?? ($off['lowPrice'] ?? null)) : null,
                'cats' => '',
            ], $src);
        }
        return array_filter($out);
    }

    private static function collectEvents(array $n, array &$out): void
    {
        if (isset($n['@type']) && !empty($n['name']) && is_string($n['name'])) {
            $t = is_array($n['@type']) ? implode(',', $n['@type']) : (string) $n['@type'];
            if (preg_match('/Event/', $t)) $out[] = $n;
        }
        if (array_is_list($n)) { foreach ($n as $c) if (is_array($c)) self::collectEvents($c, $out); return; }
        foreach (['@graph', 'itemListElement', 'mainEntity', 'subEvent'] as $k) {
            if (isset($n[$k]) && is_array($n[$k])) {
                $list = array_is_list($n[$k]) ? $n[$k] : [$n[$k]];
                foreach ($list as $c) if (is_array($c)) self::collectEvents($c, $out);
            }
        }
    }

    private static function itemsFromMicro(string $html, string $pageUrl, object $src): array
    {
        if (!preg_match('#itemtype=["\']https?://schema\.org/Event#i', $html)) return [];
        $name = preg_match('#itemprop=["\']name["\'][^>]*>\s*([^<]+)#i', $html, $m) ? trim(html_entity_decode($m[1])) : '';
        $start = preg_match('#itemprop=["\']startDate["\'][^>]*content=["\']([^"\']+)#i', $html, $m) ? $m[1] : null;
        $end = preg_match('#itemprop=["\']endDate["\'][^>]*content=["\']([^"\']+)#i', $html, $m) ? $m[1] : null;
        $desc = preg_match('#itemprop=["\']description["\'][^>]*>(.*?)</span>#is', $html, $m) ? $m[1] : '';
        $img = preg_match('#<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)#i', $html, $m) ? $m[1] : null;
        if ($name === '' || !$start) return [];
        return [self::item([
            'title' => $name, 'start' => $start, 'end' => $end, 'tz' => self::STATE_TZ[$src->state] ?? 'America/New_York',
            'venue' => null, 'address' => null, 'city' => $src->city, 'state' => $src->state, 'image' => $img ? self::absUrl($img, $pageUrl) : null,
            'url' => $pageUrl, 'desc' => $desc, 'cats' => '',
        ], $src)];
    }

    private static function absUrl(string $u, string $base): string
    {
        if (preg_match('#^https?://#i', $u)) return $u;
        if (str_starts_with($u, '//')) return 'https:' . $u;
        $p = parse_url($base);
        return ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . '/' . ltrim($u, '/');
    }

    /** JSON 값이 배열 등이어도 안전하게 문자열로 */
    private static function s($v): string
    {
        return is_string($v) ? $v : (is_numeric($v) ? (string) $v : '');
    }

    private static function text(?string $html, int $max = 900): string
    {
        $t = html_entity_decode(strip_tags((string) preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = trim((string) preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $t)));
        return mb_substr($t, 0, $max);
    }

    /** 읽은 값을 같은 모양으로 정리 (날짜가 없으면 null) */
    private static function item(array $a, object $src): ?array
    {
        $title = self::text($a['title'] ?? '', 250);
        if ($title === '') return null;
        $tz = $a['tz'] ?? 'America/New_York';
        $allday = !empty($a['allday']);
        $start = $a['start_c'] ?? null; $end = $a['end_c'] ?? null;
        try {
            if (!$start && !empty($a['start'])) {
                $s = trim((string) $a['start']);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) { $allday = true; $s .= ' 12:00:00'; }
                $start = Carbon::parse($s, $tz)->utc();
            }
            if (!$end && !empty($a['end'])) {
                $e = trim((string) $a['end']);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $e)) $e .= ' 12:00:00';
                $end = Carbon::parse($e, $tz)->utc();
            }
        } catch (\Throwable $ex) { return null; }
        if (!$start) return null;
        $price = null;
        if (isset($a['price']) && $a['price'] !== '' && $a['price'] !== null) {
            if (is_numeric($a['price'])) $price = (float) $a['price'];
            elseif (preg_match('/\$\s*([\d,]+(?:\.\d+)?)/', (string) $a['price'], $pm)) $price = (float) str_replace(',', '', $pm[1]);
        }
        $url = $a['url'] ?? null;
        if (!$url) return null;
        $state = strtoupper(trim((string) ($a['state'] ?? '')));
        if (strlen($state) !== 2) $state = $src->state;
        return [
            'title' => $title, 'start' => $start, 'end' => $end, 'allday' => $allday, 'tz' => $tz,
            'venue' => !empty($a['venue']) ? self::text($a['venue'], 250) : null, 'address' => !empty($a['address']) ? self::text($a['address'], 250) : null,
            'city' => !empty($a['city']) ? self::text($a['city'], 100) : ($src->city ?: null), 'state' => $state,
            'zip' => !empty($a['zip']) ? mb_substr((string) $a['zip'], 0, 10) : null,
            'lat' => isset($a['lat']) && is_numeric($a['lat']) ? (float) $a['lat'] : null, 'lng' => isset($a['lng']) && is_numeric($a['lng']) ? (float) $a['lng'] : null,
            'image' => !empty($a['image']) && preg_match('#^https?://#i', (string) $a['image']) ? mb_substr(str_replace('http://', 'https://', $a['image']), 0, 250) : null,
            'url' => mb_substr($url, 0, 250), 'desc' => self::text($a['desc'] ?? '', 900), 'price' => $price,
            'cats' => self::text($a['cats'] ?? '', 200),
        ];
    }

    // ─────────────────────────── 고르기 ───────────────────────────

    /** 중요도 점수 (제목·분류·소개 기준) */
    public static function score(string $title, string $desc = '', string $cats = ''): int
    {
        $s = 0;
        $head = $title . ' ' . $cats;
        foreach (self::BOOST as $re => $pts) if (preg_match($re, $head)) $s += $pts;
        $all = $head . ' ' . mb_substr($desc, 0, 400);
        foreach (self::KOREAN as $re => $pts) if (preg_match($re, $all)) $s += $pts;
        if (preg_match(self::PENALTY, $head)) $s -= 6;
        return $s;
    }

    /** 기준 이상이고 기간 안인 행사만 후보로 */
    private static function qualify(array $it, object $src): ?array
    {
        $now = now();
        $end = $it['end'] ?: $it['start'];
        if ($end->lt($now->copy()->addHours(6))) return null;          // 이미 끝났거나 곧 끝남
        if ($it['start']->gt($now->copy()->addDays(self::WINDOW_DAYS))) return null;
        $days = $it['end'] ? $it['start']->diffInDays($it['end']) : 0;
        if ($days > 45) return null;                                    // 몇 달씩 이어지는 상설 프로그램 제외
        $score = self::score($it['title'], $it['desc'], $it['cats']);
        if ($it['image']) $score += 2;
        if ($days >= 1 && $days <= 14) $score += 2;
        if (mb_strlen($it['desc']) > 200) $score += 1;
        if ($score < self::MIN_SCORE) return null;
        $it['score'] = $score;
        $it['src'] = $src;
        $it['cityKey'] = mb_strtolower(trim((string) $it['city']));
        return $it;
    }

    private static function norm(string $t): string
    {
        $t = mb_strtolower($t);
        $t = preg_replace('/^(the|a)\s+/', '', $t);
        return (string) preg_replace('/[^a-z0-9가-힣]+/u', '', $t);
    }

    /** 이미 같은 행사가 있는지(제목이 같거나 한쪽이 다른 쪽을 포함, 날짜 ±1일) */
    private static function isDup(string $norm, Carbon $start, array $taken): bool
    {
        foreach ($taken as [$n, $d]) {
            if (abs($d->diffInDays($start, false)) > 1) continue;
            if ($n === $norm) return true;
            if (min(strlen($n), strlen($norm)) >= 12 && (str_contains($n, $norm) || str_contains($norm, $n))) return true;
        }
        return false;
    }

    private static function select(array $cands, array $lim): array
    {
        usort($cands, fn ($a, $b) => [$b['score'], $a['start']->timestamp] <=> [$a['score'], $b['start']->timestamp]);
        // 이미 사이트에 있는 다른 출처(Ticketmaster·회원 등록) 행사와 겹치지 않게
        $taken = [];
        foreach (Event::whereBetween('start_date', [now()->subDay(), now()->addDays(self::WINDOW_DAYS + 3)])
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', self::SOURCE))->get(['title', 'start_date']) as $e) {
            $taken[] = [self::norm($e->title), Carbon::parse($e->start_date)];
        }
        $byState = $byCounty = $byCity = []; $picked = [];
        foreach ($cands as $c) {
            $st = $c['state'];
            if (($byState[$st] ?? 0) >= $lim['per_state']) continue;
            $county = $c['src']->county ?: ('city:' . $c['cityKey']);
            $ck = $st . '|' . $county;
            if (($byCounty[$ck] ?? 0) >= $lim['per_county']) continue;
            $cityk = $st . '|' . $c['cityKey'];
            if ($c['cityKey'] !== '' && ($byCity[$cityk] ?? 0) >= $lim['per_city']) continue;
            $norm = self::norm($c['title']);
            if ($norm === '' || self::isDup($norm, $c['start'], $taken)) continue;
            $taken[] = [$norm, $c['start']];
            $byState[$st] = ($byState[$st] ?? 0) + 1; $byCounty[$ck] = ($byCounty[$ck] ?? 0) + 1;
            if ($c['cityKey'] !== '') $byCity[$cityk] = ($byCity[$cityk] ?? 0) + 1;
            $picked[] = $c;
        }
        return $picked;
    }

    // ─────────────────────────── 저장 ───────────────────────────

    private static function category(string $title, string $cats): string
    {
        $t = $title . ' ' . $cats;
        if (preg_match('/\b(festival|fest|parade|fair|expo|carnival|market)\b/i', $t)) return 'festival';
        if (preg_match('/\b(vs\.?|game|match|marathon|5k|race|tournament|rodeo|championship|nba|nfl|mlb|nhl|mls)\b/i', $t)) return 'sports';
        if (preg_match('/\b(concert|symphony|orchestra|ballet|opera|musical|theat(er|re)|live|comedy|film|movie|show|tour|band|music)\b/i', $t)) return 'concert';
        if (preg_match('/\b(workshop|class|seminar|lecture|training)\b/i', $t)) return 'education';
        return 'festival';
    }

    /** 좌표가 없는 행사는 장소·주소·도시 이름으로 위치를 찾는다(OpenStreetMap Nominatim, 결과는 90일 캐시) — 좌표가 있어야 "내 위치 반경" 목록에 나온다 */
    private static function geocode(array $c): ?array
    {
        $tries = [];
        if (!empty($c['venue']) && !empty($c['city'])) $tries[] = $c['venue'] . ', ' . $c['city'] . ', ' . $c['state'];
        if (!empty($c['address']) && !empty($c['city'])) $tries[] = trim($c['address'] . ', ' . $c['city'] . ', ' . $c['state'] . ' ' . ($c['zip'] ?? ''));
        if (!empty($c['city'])) $tries[] = $c['city'] . ', ' . $c['state'];
        foreach ($tries as $q) {
            $key = 'geo_' . md5(mb_strtolower($q));
            $hit = Cache::get($key);
            if ($hit === null) {
                $hit = false;
                try {
                    $r = Http::withHeaders(['User-Agent' => self::UA])->timeout(15)->get('https://nominatim.openstreetmap.org/search', ['q' => $q, 'format' => 'json', 'limit' => 1, 'countrycodes' => 'us']);
                    $j = $r->ok() ? $r->json() : null;
                    if (is_array($j) && !empty($j[0]['lat']) && !empty($j[0]['lon'])) $hit = [(float) $j[0]['lat'], (float) $j[0]['lon']];
                } catch (\Throwable $e) {}
                Cache::put($key, $hit, now()->addDays(90));
                sleep(1);   // Nominatim 이용 규칙: 초당 1건 이하
            }
            if (is_array($hit)) return $hit;
        }
        return null;
    }

    private static function buildRow(array $c): array
    {
        if ($c['lat'] === null || $c['lng'] === null) {
            if ($g = self::geocode($c)) { [$c['lat'], $c['lng']] = $g; }
        }
        $local = $c['start']->copy()->setTimezone($c['tz']);
        $fmt = function (Carbon $d, bool $time) {
            $h = (int) $d->format('G');
            $s = sprintf('%d월 %d일(%s)', $d->month, $d->day, self::WEEKDAY_KO[(int) $d->format('w')]);
            return $time ? sprintf('%s %s %d:%s', $s, $h < 12 ? '오전' : '오후', ($h % 12) ?: 12, $d->format('i')) : $s;
        };
        $when = $local->year . '년 ' . $fmt($local, !$c['allday']);
        if ($c['end'] && $c['end']->copy()->setTimezone($c['tz'])->format('Y-m-d') !== $local->format('Y-m-d')) {
            $when .= ' ~ ' . $fmt($c['end']->copy()->setTimezone($c['tz']), false);
        }
        $where = collect([$c['venue'], $c['address'], $c['city'], trim(($c['state'] ?? '') . ' ' . ($c['zip'] ?? ''))])->filter()->unique()->implode(', ');
        $titleKo = EventImporter::translate($c['title']);

        $lines = [];
        if ($titleKo !== '' && mb_strtolower($titleKo) !== mb_strtolower($c['title'])) $lines[] = "📝 한글 제목: {$titleKo}";
        $lines[] = "📅 일정: {$when}" . ($c['allday'] ? '' : ' (현지 시간)');
        if ($where) $lines[] = "📍 장소: {$where}";
        $lines[] = '🎫 요금: ' . ($c['price'] === null ? '원본 페이지에서 확인하세요' : ($c['price'] == 0 ? '무료' : '$' . rtrim(rtrim(number_format($c['price'], 2), '0'), '.') . ' ~'));
        $body = implode("\n", $lines);
        if ($c['desc'] !== '') $body .= "\n\n■ 소개\n" . EventImporter::translate(mb_substr($c['desc'], 0, 600));
        $body .= "\n\n※ 출처: {$c['src']->name} · 일정과 요금은 바뀔 수 있어요. 자세한 내용은 아래 '원본 행사 보기'에서 확인하세요.";

        return [
            'source_id' => $c['src']->key . ':' . substr(sha1($c['url']), 0, 24),
            'title' => mb_substr($c['title'], 0, 250),
            'description' => mb_substr($when . ($c['venue'] ? ' · ' . $c['venue'] : ''), 0, 250),
            'content' => mb_substr($body, 0, 4000),
            'image_url' => $c['image'],
            'category' => self::category($c['title'], $c['cats']),
            'organizer' => $c['src']->name,
            'venue' => $c['venue'], 'address' => $c['address'], 'city' => $c['city'], 'state' => $c['state'], 'zipcode' => $c['zip'],
            'lat' => $c['lat'], 'lng' => $c['lng'],
            'start_date' => $c['start']->copy()->setTimezone(config('app.timezone')),
            'end_date' => $c['end'] ? $c['end']->copy()->setTimezone(config('app.timezone')) : null,
            'price' => $c['price'],
            'url' => $c['url'],
        ];
    }

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
}
