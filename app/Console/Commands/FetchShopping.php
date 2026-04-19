<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class FetchShopping extends Command
{
    protected $signature   = 'shopping:fetch';
    protected $description = '쇼핑 딜 RSS 피드를 가져와 DB에 저장합니다';

    private array $feeds = [
        'Slickdeals'   => 'https://slickdeals.net/newsearch.php?mode=frontpage&searcharea=deals&searchin=first&rss=1',
        'DealNews'     => 'https://www.dealnews.com/rss.html',
        'TechBargains' => 'https://techbargains.com/rss',
    ];

    public function handle(): int
    {
        if (!Schema::hasTable('shopping_deals')) {
            $this->warn('shopping_deals 테이블이 없습니다. 마이그레이션을 먼저 실행하세요.');
            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;

        foreach ($this->feeds as $source => $url) {
            $this->info("피드 가져오는 중: {$source}");
            try {
                $context = stream_context_create([
                    'http' => [
                        'timeout'    => 15,
                        'user_agent' => 'Mozilla/5.0 (AwesomeKorean Bot)',
                    ],
                    'ssl' => [
                        'verify_peer'      => false,
                        'verify_peer_name' => false,
                    ],
                ]);

                $xml = @file_get_contents($url, false, $context);
                if (!$xml) {
                    $this->warn("  가져오기 실패: {$source}");
                    continue;
                }

                $feed = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
                if (!$feed) {
                    $this->warn("  XML 파싱 실패: {$source}");
                    continue;
                }

                $items = $feed->channel->item ?? [];

                foreach ($items as $item) {
                    $link  = (string) ($item->link ?? '');
                    $title = (string) ($item->title ?? '');

                    if (!$link || !$title) continue;

                    if (DB::table('shopping_deals')->where('url', $link)->exists()) {
                        $skipped++;
                        continue;
                    }

                    $description = strip_tags((string) ($item->description ?? ''));

                    // Extract price from title
                    $price = null;
                    if (preg_match('/\$[\d,]+(?:\.\d{2})?/', $title, $pm)) {
                        $price = $pm[0];
                    }

                    // Image from RSS
                    $imageUrl  = null;
                    $namespaces = $item->getNameSpaces(true);
                    if (isset($namespaces['media'])) {
                        $media = $item->children($namespaces['media']);
                        if (isset($media->content)) {
                            $imageUrl = (string) $media->content->attributes()->url;
                        } elseif (isset($media->thumbnail)) {
                            $imageUrl = (string) $media->thumbnail->attributes()->url;
                        }
                    }
                    if (!$imageUrl && isset($item->enclosure)) {
                        $type = (string) $item->enclosure->attributes()->type;
                        if (str_starts_with($type, 'image/')) {
                            $imageUrl = (string) $item->enclosure->attributes()->url;
                        }
                    }

                    $pubDate = $item->pubDate ?? null;
                    $publishedAt = $pubDate ? Carbon::parse((string) $pubDate) : now();

                    DB::table('shopping_deals')->insert([
                        'title'        => mb_substr($title, 0, 255),
                        'description'  => mb_substr($description, 0, 1000),
                        'url'          => $link,
                        'source'       => $source,
                        'price'        => $price,
                        'image_url'    => $imageUrl,
                        'category'     => $this->detectCategory($title . ' ' . $description),
                        'is_active'    => true,
                        'published_at' => $publishedAt,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                    $created++;
                }

                $this->info("  완료: {$source}");
            } catch (\Exception $e) {
                $this->error("  오류 ({$source}): " . $e->getMessage());
            }
        }

        $this->info("쇼핑 딜 완료: 신규 {$created}건, 중복 {$skipped}건");
        return self::SUCCESS;
    }

    private function detectCategory(string $text): string
    {
        $keywords = [
            '전자기기' => ['laptop', 'phone', 'tablet', 'TV', 'monitor', 'headphone', 'speaker', 'camera', 'SSD', 'GPU', 'PC', 'gaming'],
            '생활용품' => ['kitchen', 'home', 'appliance', 'cleaning', 'furniture', 'bed', 'vacuum', 'coffee maker'],
            '패션/의류' => ['clothing', 'shoes', 'jacket', 'shirt', 'pants', 'dress', 'fashion', 'boots', 'sneakers'],
            '건강/뷰티' => ['vitamin', 'supplement', 'skincare', 'beauty', 'health', 'fitness', 'protein'],
            '식품/음료' => ['food', 'coffee', 'snack', 'grocery', 'wine', 'drink', 'chocolate'],
            '스포츠'   => ['sports', 'gym', 'outdoor', 'camping', 'hiking', 'bike', 'golf', 'yoga'],
        ];

        $lower = strtolower($text);
        foreach ($keywords as $cat => $words) {
            foreach ($words as $w) {
                if (str_contains($lower, strtolower($w))) return $cat;
            }
        }
        return '기타';
    }
}
