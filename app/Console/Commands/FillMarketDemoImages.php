<?php

namespace App\Console\Commands;

use App\Models\MarketItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * 중고장터 더미 아이템에 실물과 매칭되는 이미지를 채워 넣는다.
 * 저작권 문제를 피하기 위해 브랜드 매장/구글 이미지 검색 결과를 그대로
 * 긁어오는 대신 Openverse(오픈 라이선스 이미지 검색, commercial 라이선스만)
 * API를 사용한다. 특정 소스 하나(예: Flickr)로 한정하면 그 호스트가
 * 일시적으로 요청을 막았을 때 전부 실패하는 문제가 있어 여러 소스를
 * 섞어서 검색 — 그래도 Openverse 인덱스 자체가 대부분 개인/아마추어가
 * 올린 사진 위주라 브랜드 공식 스튜디오컷과는 결이 다름.
 * 제목이 한글 브랜드 표기(예: "허먼밀러 에어론" = Herman Miller Aeron)인 경우가
 * 많아 단순 한글 제거로는 검색어가 안 나와서, 실제 108개 타이틀을 직접
 * 확인해 영어 검색어로 매핑한 사전을 사용한다.
 */
class FillMarketDemoImages extends Command
{
    protected $signature = 'market:fill-demo-images {--limit=200 : 최대 처리 건수} {--per-item=3 : 아이템당 목표 이미지 수} {--force : 이미지가 이미 있어도 다시 채움}';
    protected $description = '이미지 없는 중고장터 아이템에 오픈 라이선스 이미지를 채움';

    /** 연속 다운로드 실패 허용 횟수 — 넘으면 이미지 소스가 막힌 것으로 보고 조기 중단 */
    private const MAX_CONSECUTIVE_FAILURES = 6;
    /** 배포 SSH 타임아웃(20분)에 걸리지 않도록 두는 실행 시간 상한(초) */
    private const MAX_RUNTIME_SECONDS = 420;

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
        // 고해상도 원본 이미지 디코딩 시 메모리를 넉넉히 확보 (CLI 프로세스 한정,
        // 웹 요청에는 영향 없음)
        @ini_set('memory_limit', '512M');

        $limit = (int) $this->option('limit');
        $perItem = max(1, (int) $this->option('per-item'));
        $force = (bool) $this->option('force');

        $query = MarketItem::query();
        if (!$force) {
            $query->where(function ($q) use ($perItem) {
                $q->whereNull('images')
                  ->orWhereRaw('JSON_LENGTH(images) < ?', [$perItem]);
            });
        }
        $items = $query->orderBy('id')->limit($limit)->get();

        $this->info("대상: {$items->count()}건");
        $filled = 0;
        $skipped = 0;

        // 안전장치 1: 연속 다운로드 실패가 계속되면(=Flickr 쪽에서 지속적으로
        // 차단/제한 중) 똑같은 실패를 계속 반복하며 시간만 낭비하지 않도록
        // 조기 중단 — 배포 SSH 20분 타임아웃으로 통째로 죽는 것을 방지.
        $consecutiveFailures = 0;
        // 안전장치 2: 순수 시간 기준으로도 상한을 둬서 위 카운터가 어떤
        // 이유로든 못 걸러내는 경우까지 대비 (배포 파이프라인 다른 단계
        // 몫으로 여유를 남겨둠).
        $startedAt = microtime(true);

        foreach ($items as $item) {
            if ($consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES) {
                $this->warn("연속 {$consecutiveFailures}회 다운로드 실패 — 이미지 소스가 일시적으로 막힌 것으로 보여 중단합니다. (처리됨: {$filled}건)");
                break;
            }
            if (microtime(true) - $startedAt > self::MAX_RUNTIME_SECONDS) {
                $this->warn('실행 시간 상한(' . self::MAX_RUNTIME_SECONDS . "초) 도달 — 중단합니다. (처리됨: {$filled}건)");
                break;
            }

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
                if ($consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES) break;
                $path = $this->downloadAndStore($url);
                if ($path) {
                    $stored[] = $path;
                    $consecutiveFailures = 0;
                } else {
                    $consecutiveFailures++;
                }
                // Flickr 쪽 순간 요청 폭주로 인한 일시적 차단(403/429)을 피하기 위해
                // 이미지 한 장씩 받을 때마다 약간의 텀을 둠
                usleep(300000);
            }

            if (!empty($stored)) {
                if ($force && !empty($item->images)) {
                    foreach ($item->images as $old) {
                        Storage::disk('public')->delete($old);
                    }
                }
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
                    // 특정 소스(Flickr 등) 하나에 의존하면 그 호스트가 일시적으로
                    // 요청을 막았을 때 전부 실패하므로, 여러 소스를 섞어서 검색해
                    // 한쪽이 막혀도 다른 소스 URL로 대체될 수 있게 함
                    'page_size' => $count * 4,
                ]);
            if (!$resp->ok()) return [];
            $results = $resp->json('results') ?? [];
            return collect($results)
                ->pluck('url')
                ->filter()
                // Flickr(live.staticflickr.com)는 이 서버 IP를 계속 403으로 막고
                // 있는 것으로 반복 확인됨 — 후보에서 아예 제외해서 연속 실패
                // 안전장치가 애먼 아이템에서 조기에 소진되는 것을 방지
                ->reject(fn($url) => str_contains($url, 'staticflickr.com'))
                ->take($count * 3)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function downloadAndStore(string $url): ?string
    {
        try {
            $resp = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(8)->get($url);
            if (($resp->status() === 403 || $resp->status() === 429)) {
                // 일시적 요청 제한일 수 있으니 잠깐 쉬었다가 한 번만 재시도
                usleep(1500000);
                $resp = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(8)->get($url);
            }
            if (!$resp->ok() || strlen($resp->body()) < 2000) return null;
            // Wikimedia 등 원본이 수 MB짜리 고해상도 사진인 경우가 많아, 디코딩 시
            // 압축 크기의 수십 배에 달하는 메모리를 써서 PHP 메모리 한도를 넘기면
            // (Allowed memory size exhausted) 예외로 잡히지 않고 프로세스 자체가
            // 죽어버릴 수 있음 — 디코딩 전에 원본 용량으로 미리 걸러냄
            if (strlen($resp->body()) > 4_000_000) return null;

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
