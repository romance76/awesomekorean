<?php

namespace App\Console\Commands;

use App\Models\MarketItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * 중고장터 더미 아이템에 실물과 매칭되는 이미지를 채워 넣는다.
 * 저작권 문제를 피하기 위해 브랜드 매장/구글 이미지 대신
 * Openverse(오픈 라이선스 이미지 검색, commercial 라이선스만) API를 사용.
 * 제목이 한글 브랜드 표기(예: "허먼밀러 에어론" = Herman Miller Aeron)인 경우가
 * 많아 단순 한글 제거로는 검색어가 안 나와서, 실제 108개 타이틀을 직접
 * 확인해 영어 검색어로 매핑한 사전을 사용한다.
 */
class FillMarketDemoImages extends Command
{
    protected $signature = 'market:fill-demo-images {--limit=200 : 최대 처리 건수} {--per-item=3 : 아이템당 목표 이미지 수}';
    protected $description = '이미지 없는 중고장터 아이템에 오픈 라이선스 이미지를 채움';

    /** 제목에 포함된 키워드 → 영어 검색어 (구체적인 것부터 순서대로 매칭) */
    private array $keywordMap = [
        // auto
        'OBD2' => 'OBD2 scanner car diagnostic',
        '겨울 타이어' => 'winter tires',
        '대시캠' => 'dash cam car',
        '루프탑 카고박스' => 'rooftop cargo box car',
        '블랙박스' => 'dash cam car',
        '자전거 트렁크 랙' => 'bicycle trunk rack car',
        '차량용 핸드폰 거치대' => 'car phone mount',
        '카시트' => 'child car seat',
        '카플레이' => 'wireless carplay adapter',
        // baby
        '기저귀 가방' => 'diaper bag',
        '변기 트레이닝' => 'potty training seat',
        '수유쿠션' => 'nursing pillow',
        '아기 모니터' => 'baby monitor camera',
        '바운서' => 'baby bouncer seat',
        '아기 욕조' => 'baby bathtub',
        '아기띠' => 'baby carrier',
        '유모차' => 'baby stroller',
        '유아 자전거' => 'balance bike kids',
        '젖병' => 'baby bottles set',
        '하이체어' => 'baby high chair',
        // books
        'GRE' => 'GRE test prep book',
        'IT 프로그래밍' => 'programming books',
        'SAT' => 'SAT prep book',
        '부동산 자격증' => 'real estate exam textbook',
        '그림책' => "children's picture books",
        '요리책' => 'cookbook',
        '토익' => 'English test prep book',
        '한국어 교재' => 'Korean language textbook',
        '해리포터' => 'Harry Potter books collection',
        // clothing
        '나이키 에어맥스' => 'Nike Air Max sneakers',
        '나이키 테크 플리스' => 'fleece jacket',
        '노스페이스 눕시' => 'down jacket',
        '닥터마틴' => 'Dr. Martens boots',
        '룰루레몬 레깅스' => 'leggings',
        '아기 옷' => 'baby clothes',
        '유니클로 히트텍' => 'thermal underwear',
        '캐나다구스 패딩' => 'down parka jacket',
        '코치 핸드백' => 'leather handbag',
        '파타고니아 플리스' => 'fleece jacket',
        '한복' => 'hanbok Korean traditional dress',
        // electronics
        'Bose' => 'noise cancelling headphones',
        'DJI' => 'drone',
        'JBL' => 'bluetooth speaker',
        'LG 그램' => 'laptop computer',
        'PS5' => 'game console controller',
        '갤럭시 탭' => 'tablet computer',
        '닌텐도 스위치' => 'handheld game console',
        '다이슨' => 'vacuum cleaner',
        '레노버 씽크패드' => 'laptop computer',
        '로봇청소기' => 'robot vacuum cleaner',
        '로보락' => 'robot vacuum cleaner',
        '로지텍' => 'computer mouse',
        '맥북' => 'laptop computer',
        '삼성 갤럭시' => 'smartphone',
        '사운드바' => 'soundbar speaker',
        '소니 WH' => 'wireless headphones',
        '에코 쇼' => 'smart speaker display',
        '아이패드' => 'tablet computer',
        '애플워치' => 'smartwatch',
        '에어팟' => 'wireless earbuds',
        '전기밥솥' => 'rice cooker',
        '캐논 EOS' => 'DSLR camera',
        // etc
        '강아지 용품' => 'dog supplies',
        '김치냉장고' => 'refrigerator kitchen',
        '네스프레소' => 'espresso coffee machine',
        '변압기' => 'voltage converter transformer',
        '액자 세트' => 'picture frames wall',
        '에어프라이어' => 'air fryer kitchen',
        '이사 박스' => 'moving boxes',
        '캠핑 쿨러' => 'cooler ice chest camping',
        '코스트코 선반' => 'metal shelving rack',
        '크리스마스 트리' => 'christmas tree decorated',
        '피아노 디지털' => 'digital piano keyboard',
        '보드게임' => 'board games',
        '식기 세트' => 'dinnerware set dishes',
        // furniture
        'TV 스탠드' => 'TV stand furniture',
        '가죽 소파' => 'leather sofa',
        '드레서 화장대' => 'dresser vanity mirror',
        '바 스툴' => 'bar stools',
        '허먼밀러' => 'ergonomic office chair',
        '소파베드' => 'sofa bed',
        '신발장' => 'shoe cabinet',
        '파티오 세트' => 'outdoor patio furniture',
        '유아 침대' => 'crib baby bed',
        'KALLAX' => 'shelving unit',
        'MALM' => 'bed frame bedroom',
        'PAX' => 'wardrobe closet',
        '접이식 테이블' => 'folding table chairs outdoor',
        '코너 데스크' => 'corner desk',
        '사이드 테이블' => 'nightstand',
        '와인 냉장고' => 'wine refrigerator cooler',
        'Casper' => 'mattress bedroom',
        // sports
        '골프 풀세트' => 'golf clubs set',
        '낚시대' => 'fishing rod',
        '농구공' => 'basketball',
        '덤벨 세트' => 'dumbbell weights set',
        '등산 배낭' => 'hiking backpack',
        '서핑보드' => 'surfboard',
        '자전거' => 'road bicycle',
        '캠핑 의자' => 'camping chairs',
        '캠핑 텐트' => 'camping tent',
        '테니스 라켓' => 'tennis racket',
        '펠로톤' => 'exercise bike',
        '풋살화' => 'soccer cleats shoes',
    ];

    /** 매칭되는 키워드가 없을 때 카테고리별 기본 검색어 */
    private array $categoryFallback = [
        'electronics' => 'consumer electronics',
        'furniture' => 'furniture',
        'clothing' => 'clothing',
        'auto' => 'car accessories',
        'baby' => 'baby product',
        'sports' => 'sports equipment',
        'books' => 'books',
        'etc' => 'household item',
    ];

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $perItem = max(1, (int) $this->option('per-item'));

        $items = MarketItem::where(function ($q) use ($perItem) {
                $q->whereNull('images')
                  ->orWhereRaw('JSON_LENGTH(images) < ?', [$perItem]);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $this->info("대상: {$items->count()}건");
        $filled = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $query = $this->buildQuery($item);
            $urls = $this->searchImages($query, $perItem);

            if (count($urls) < $perItem) {
                $fallbackQuery = $this->categoryFallback[$item->category] ?? 'product';
                if ($fallbackQuery !== $query) {
                    $more = $this->searchImages($fallbackQuery, $perItem - count($urls));
                    $urls = array_merge($urls, $more);
                }
            }

            if (empty($urls)) {
                $skipped++;
                continue;
            }

            $stored = [];
            foreach ($urls as $url) {
                if (count($stored) >= $perItem) break;
                $path = $this->downloadAndStore($url);
                if ($path) $stored[] = $path;
            }

            if (!empty($stored)) {
                $item->update(['images' => $stored, 'thumbnail_index' => 0]);
                $filled++;
                $this->line("✓ [{$item->id}] {$item->title} ({$query}) — " . count($stored) . '장');
            } else {
                $skipped++;
            }

            usleep(150000);
        }

        $this->info("완료: {$filled}건 채움, {$skipped}건 실패/스킵");
        return 0;
    }

    private function buildQuery(MarketItem $item): string
    {
        foreach ($this->keywordMap as $needle => $query) {
            if (mb_stripos($item->title, $needle) !== false) {
                return $query;
            }
        }
        return $this->categoryFallback[$item->category] ?? 'product';
    }

    private const USER_AGENT = 'AwesomeKoreanBot/1.0 (https://awesomekorean.com; demo listing images)';

    private function searchImages(string $query, int $count): array
    {
        try {
            $resp = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(6)->get('https://api.openverse.org/v1/images/', [
                    'q' => $query,
                    'license_type' => 'commercial',
                    'page_size' => $count * 4,
                ]);
            if (!$resp->ok()) return [];
            $results = $resp->json('results') ?? [];
            return collect($results)->pluck('url')->filter()->take($count * 3)->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function downloadAndStore(string $url): ?string
    {
        try {
            $resp = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(8)->get($url);
            if (!$resp->ok() || strlen($resp->body()) < 2000) return null;

            $img = \Intervention\Image\Laravel\Facades\Image::read($resp->body());
            $img->scaleDown(800, 800);
            $bytes = $img->toJpeg(78)->toString();

            $filename = 'market/' . md5($url . microtime()) . '.jpg';
            Storage::disk('public')->put($filename, $bytes);
            return $filename;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
