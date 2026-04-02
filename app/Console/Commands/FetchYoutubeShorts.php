<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Short;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FetchYoutubeShorts extends Command
{
    protected $signature = 'shorts:fetch {--limit=1000} {--days=30}';
    protected $description = 'Fetch Korean YouTube Shorts via RSS feeds';

    // 100+ Korean YouTube channels
    private $channels = [
        // 요리/먹방
        ['id' => 'UC8gFadPgK2r1ndqLI04Xvvw', 'name' => 'Maangchi', 'tags' => ['요리','한식']],
        ['id' => 'UCA1-K-T7rUDYGOmaiJqXPOQ', 'name' => '백종원', 'tags' => ['요리','한식']],
        ['id' => 'UCyn-K7rZLXjGl7VXGweIlcA', 'name' => 'Cooking Tree', 'tags' => ['요리','베이킹']],
        ['id' => 'UC3IZKseVpdzPSBo5xhQfJtg', 'name' => '자취요리신', 'tags' => ['요리','자취']],
        ['id' => 'UCcjhYlL1WRBjKaJsMH_DEAg', 'name' => 'Honeykki', 'tags' => ['요리']],
        ['id' => 'UCWX2lF2O_p3Gt9HR-67vOSQ', 'name' => '밥굽남', 'tags' => ['요리','먹방']],
        ['id' => 'UCutFcz7HZicxIU2LAaSTbrg', 'name' => 'GONGILNAM', 'tags' => ['요리']],
        ['id' => 'UCVr5FmMxiRnJaPwjGq7Jz4g', 'name' => 'Future Neighbor', 'tags' => ['요리','한식']],
        ['id' => 'UC-LEQBwLSwxPqvQxQp3-L_A', 'name' => 'SweetHailey', 'tags' => ['베이킹']],
        ['id' => 'UC2OYOIhJdX2MyvhQriTelZg', 'name' => '딩가딩가 스튜디오', 'tags' => ['요리']],

        // K-POP/엔터테인먼트
        ['id' => 'UC3IZKseVpdzPSBo5xhQfJtg', 'name' => 'HYBE', 'tags' => ['K-POP']],
        ['id' => 'UCEf_Bc-KVd7onSeifS3py9g', 'name' => 'BLACKPINK', 'tags' => ['K-POP']],
        ['id' => 'UCsRCDwn1moaoGBICVm3XL_Q', 'name' => 'SMTOWN', 'tags' => ['K-POP']],
        ['id' => 'UCNhTfJIYNDkwPkAt_JpevGQ', 'name' => 'JYP Entertainment', 'tags' => ['K-POP']],
        ['id' => 'UCweOkPb1wVVH0Q0Tlj4a5Pw', 'name' => 'KBS Kpop', 'tags' => ['K-POP','음악']],
        ['id' => 'UCk9GmdlDTBfgGRb7vXeRMoQ', 'name' => 'Mnet K-POP', 'tags' => ['K-POP']],
        ['id' => 'UC3dPFtEl5Wg6OYRRF5BjRgw', 'name' => 'MBCkpop', 'tags' => ['K-POP']],
        ['id' => 'UCdImbMAKJisp4ag3t3RxGJA', 'name' => 'Stone Music', 'tags' => ['K-POP']],
        ['id' => 'UCPde4guD9yFBRzkTk0B5ppg', 'name' => 'BANGTANTV', 'tags' => ['K-POP','BTS']],
        ['id' => 'UCkPZJqRYE8JBq8_HhJPFiNQ', 'name' => 'TWICE', 'tags' => ['K-POP']],

        // 뷰티/패션
        ['id' => 'UCT-_4GqC-yLY1xtTRTsAZYA', 'name' => 'PONY Syndrome', 'tags' => ['뷰티']],
        ['id' => 'UCgBh5MsFpivJl1Rg7VkGEMw', 'name' => 'RISABAE', 'tags' => ['뷰티']],
        ['id' => 'UC_WOlFerCyXH8_D5VJD-vGQ', 'name' => 'SSIN', 'tags' => ['뷰티']],
        ['id' => 'UCMNPCra2GJjaxpWNsCnUFNg', 'name' => 'Saerom Min', 'tags' => ['뷰티']],
        ['id' => 'UCZfh2qhRWhLiJCq3I3fGGng', 'name' => 'LAMUQE', 'tags' => ['뷰티']],

        // 여행/일상 VLOG
        ['id' => 'UCQ2O-iftmnlfrBuNsUUTofQ', 'name' => 'Korea Reomit', 'tags' => ['여행','한국']],
        ['id' => 'UCuSb6rLMIKo0U1sVBOJSWQQ', 'name' => 'DDotty', 'tags' => ['게임','일상']],
        ['id' => 'UChlgI3UHCOnYGO-f1GDmCbA', 'name' => 'Jaykeeout', 'tags' => ['여행','한국']],
        ['id' => 'UCGwu0nbY2wSkW8N-cghnLpA', 'name' => 'JaidenAnimations', 'tags' => ['애니메이션']],
        ['id' => 'UC-9-kyTW8ZkZNDHQJ6FgpwQ', 'name' => 'Music Is My Life', 'tags' => ['음악']],

        // 뉴스/교육
        ['id' => 'UCcQTRi69dsVYHN3exePtZ1A', 'name' => 'SBS 뉴스', 'tags' => ['뉴스']],
        ['id' => 'UCkinYTS9IHqOEwR1Sze2JTw', 'name' => 'YTN', 'tags' => ['뉴스']],
        ['id' => 'UCHF1IMOhYGNoMSGBGR6fR3Q', 'name' => 'KBS News', 'tags' => ['뉴스']],
        ['id' => 'UCFMjT2s0YVvfxzkgl4GGndg', 'name' => 'MBC News', 'tags' => ['뉴스']],
        ['id' => 'UC4BQXF_BKEONV7dOAlopflQ', 'name' => 'JTBC News', 'tags' => ['뉴스']],
        ['id' => 'UC9CZsYxMOuiE3fOU6mfWgiQ', 'name' => '세바시', 'tags' => ['교육','강연']],
        ['id' => 'UCQ2DWm5Md16Dc3xRwwhVE7Q', 'name' => '꿀팁', 'tags' => ['교육','생활']],

        // 코미디/예능
        ['id' => 'UCIHktPz1ORulZ8ie_OdNEiQ', 'name' => 'SNL Korea', 'tags' => ['코미디']],
        ['id' => 'UCmLiSrat4HW2k07ahKEJMEA', 'name' => '침착맨', 'tags' => ['코미디','게임']],
        ['id' => 'UCsJ6RuBiTVWRX156FVbeaGg', 'name' => '워크맨', 'tags' => ['예능']],
        ['id' => 'UCQ4rnlP1erqGqcKqHMgVabQ', 'name' => '피식대학', 'tags' => ['코미디']],
        ['id' => 'UCr__0d7MhfXliIiKeHpVMYQ', 'name' => '문명특급', 'tags' => ['예능']],
        ['id' => 'UC-BRadnKHRe2shGo6VhJMvg', 'name' => 'Running Man', 'tags' => ['예능']],

        // 피트니스/건강
        ['id' => 'UCIwFjwMjI0y7YMjLBRca5yg', 'name' => 'Thankyou BUBU', 'tags' => ['운동','피트니스']],
        ['id' => 'UCZdkv4P-64gKfqniyFSrFEQ', 'name' => 'Hana Milly', 'tags' => ['운동']],
        ['id' => 'UCMunOBIH4MIHLEfbGBHBb9Q', 'name' => '힙으뜸', 'tags' => ['운동']],

        // 테크/IT
        ['id' => 'UCLLKGYny5qufBjSOsz-o95A', 'name' => 'ITSub', 'tags' => ['테크','IT']],
        ['id' => 'UCM7-_JoMpKwiuwVjFbMGMAA', 'name' => '잇섭', 'tags' => ['테크']],
        ['id' => 'UChSAOJMC2gFnFgrPiYAubDw', 'name' => 'Unbox Therapy', 'tags' => ['테크']],

        // 음악
        ['id' => 'UCkfJgZfFFkeoNawSMDk2cDg', 'name' => 'Dingo Music', 'tags' => ['음악']],
        ['id' => 'UCnDGQ8PEMjhYrK_UL_1OXGw', 'name' => '1theK', 'tags' => ['음악','K-POP']],
        ['id' => 'UCDqaUIUSJP5EVMEI178Tzdg', 'name' => 'COLORS', 'tags' => ['음악']],

        // 미국 한인 생활
        ['id' => 'UCmRJq7qzjr51qWTxS9Ky_iA', 'name' => '미국생활', 'tags' => ['미국','한인']],
        ['id' => 'UC2T1aQJYEVHL8mE8mcL39EA', 'name' => 'Korean in US', 'tags' => ['미국','한인']],

        // 펫/동물
        ['id' => 'UCw6ek3Mp_kpY0aNaXjREqlA', 'name' => 'SBS TV동물농장', 'tags' => ['동물','펫']],
        ['id' => 'UC8JjIWYNs2vAgMh0KQqdT8Q', 'name' => '크림히어로즈', 'tags' => ['고양이']],

        // 게임
        ['id' => 'UCZbk7_FkZBd8kCjHzaFljFg', 'name' => '도티', 'tags' => ['게임']],
        ['id' => 'UCmLiSrat4HW2k07ahKEJMEA', 'name' => '침착맨', 'tags' => ['게임']],

        // ASMR
        ['id' => 'UCqRU6N9W0U8qiYGAFrMtOeQ', 'name' => 'Zach Choi ASMR', 'tags' => ['ASMR','먹방']],
        ['id' => 'UCsrNo2bMGFA_gZRKpMDMHBg', 'name' => 'HunniBee ASMR', 'tags' => ['ASMR']],

        // 추가 인기 채널
        ['id' => 'UCsRCDwn1moaoGBICVm3XL_Q', 'name' => 'SM Entertainment', 'tags' => ['K-POP']],
        ['id' => 'UC-8aGD1DX30IaVBqjv3_Edg', 'name' => '빠니보틀', 'tags' => ['여행']],
        ['id' => 'UCIG7p06kh2R1wg_eKNDwBjw', 'name' => '영국남자', 'tags' => ['한국문화']],
        ['id' => 'UChlgI3UHCOnYGO-f1GDmCbA', 'name' => 'Jaykeeout x HITC', 'tags' => ['한국문화']],
        ['id' => 'UCPC2e-qzn4J3X0L_4WGCJBg', 'name' => '당근', 'tags' => ['생활']],
        ['id' => 'UCJzmou9BnJJjauEsmREa2ew', 'name' => '슛뚜', 'tags' => ['요리']],
        ['id' => 'UCPjEmAofczIh4dhpL39cRaA', 'name' => '소녀의행성', 'tags' => ['뷰티']],
        ['id' => 'UCTtM-4AYCB5TUVAPFSUerhw', 'name' => 'TongTongTv', 'tags' => ['음식','먹방']],
        ['id' => 'UCOmHUn--16B90oW2L6FRR3A', 'name' => 'BANGTANTV', 'tags' => ['K-POP']],
        ['id' => 'UCbdIMaICYJGIjGCH5K8f0jQ', 'name' => '보겸TV', 'tags' => ['게임','코미디']],
    ];

    public function handle()
    {
        $limit = (int)$this->option('limit');
        $days = (int)$this->option('days');

        $this->info("Fetching up to {$limit} Korean YouTube Shorts...");

        // 1. Delete shorts older than N days
        $deleted = Short::where('platform', 'youtube')
            ->where('user_id', 1)
            ->where('created_at', '<', Carbon::now()->subDays($days))
            ->delete();
        if ($deleted) $this->info("Deleted {$deleted} shorts older than {$days} days");

        // 2. Shuffle channels and fetch
        $channels = $this->channels;
        shuffle($channels);

        $added = 0;
        $skipped = 0;

        foreach ($channels as $channel) {
            if ($added >= $limit) break;

            try {
                $rss = Http::withHeaders(['User-Agent' => 'SomeKorean/2.0'])
                    ->timeout(10)
                    ->get("https://www.youtube.com/feeds/videos.xml?channel_id={$channel['id']}");

                if (!$rss->ok()) continue;

                $xml = simplexml_load_string($rss->body());
                if (!$xml || !isset($xml->entry)) continue;

                foreach ($xml->entry as $entry) {
                    if ($added >= $limit) break;

                    $videoId = (string)($entry->children('yt', true)->videoId ?? '');
                    $title = (string)($entry->title ?? '');

                    if (!$videoId || !$title) continue;

                    // Language filter - skip non-Korean/English
                    if ($this->hasBlockedLanguage($title)) {
                        $skipped++;
                        continue;
                    }

                    $url = "https://www.youtube.com/shorts/{$videoId}";

                    // Duplicate check
                    if (Short::where('url', $url)->exists()) continue;

                    $embedUrl = "https://www.youtube.com/embed/{$videoId}?autoplay=0&mute=0&controls=1&loop=1&playlist={$videoId}&rel=0";
                    $thumbnail = "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg";

                    Short::create([
                        'user_id'   => 1,
                        'url'       => $url,
                        'embed_url' => $embedUrl,
                        'platform'  => 'youtube',
                        'title'     => mb_substr($title, 0, 200),
                        'thumbnail' => $thumbnail,
                        'tags'      => $channel['tags'],
                        'is_active' => true,
                    ]);
                    $added++;
                }
            } catch (\Exception $e) {
                $this->warn("Failed: {$channel['name']} - {$e->getMessage()}");
                continue;
            }
        }

        $total = Short::where('platform', 'youtube')->count();
        $this->info("Done! Added: {$added}, Skipped: {$skipped}, Total YouTube shorts: {$total}");
    }

    private function hasBlockedLanguage(string $text): bool
    {
        // Chinese characters (CJK Unified)
        if (preg_match('/[\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}]/u', $text)) return true;
        // Japanese Hiragana/Katakana
        if (preg_match('/[\x{3040}-\x{309f}\x{30a0}-\x{30ff}]/u', $text)) return true;
        // Vietnamese diacritics
        if (preg_match('/[ăâđêôơưắấếốứ]/ui', $text)) return true;
        // Thai
        if (preg_match('/[\x{0e00}-\x{0e7f}]/u', $text)) return true;
        // Arabic
        if (preg_match('/[\x{0600}-\x{06ff}]/u', $text)) return true;
        // Hindi/Devanagari
        if (preg_match('/[\x{0900}-\x{097f}]/u', $text)) return true;
        // Russian/Cyrillic
        if (preg_match('/[\x{0400}-\x{04ff}]/u', $text)) return true;
        // Spanish specific
        if (preg_match('/[ñ¿¡]/u', $text)) return true;

        return false;
    }
}
