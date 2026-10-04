{{-- 본 사이트 NavBar.vue와 같은 모양(로고/검색/메뉴 행)을 서버사이드 Blade로 재현.
     이 페이지는 Vue Router 밖(일반 네비게이션)이라 로그인 상태를 알 수 없으므로
     로그인/시작하기 버튼은 비로그인 상태로 고정 표시한다. --}}
<nav class="bg-white/95 backdrop-blur-sm border-b border-gray-100 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-3 flex items-center h-12 gap-2">
        <a href="/" class="flex items-center flex-shrink-0" aria-label="AwesomeKorean">
            <img src="/images/logo.png" alt="AwesomeKorean" class="h-8 w-auto" style="max-height:32px">
        </a>
        <div class="flex-1 mx-2 min-w-0 hidden md:block">
            <form action="/search" method="GET" class="relative max-w-lg mx-auto">
                <input type="text" name="q" placeholder="궁금한 것을 검색해 보세요 — 영주권, 맛집, 중고차…"
                    class="w-full bg-surface border-[1.5px] border-line rounded-full pl-4 pr-4 py-2 text-sm text-ink outline-none placeholder:text-ink-faint">
            </form>
        </div>
        <div class="flex-1 md:hidden"></div>
        <div class="flex items-center gap-1.5 flex-shrink-0">
            <a href="/login" class="text-[13px] font-semibold text-ink-light hover:text-ink hover:bg-surface px-3 py-1.5 rounded-full transition-colors">로그인</a>
            <a href="/register" class="text-[13px] text-white font-bold px-4 py-1.5 rounded-full transition-all shadow-btn" style="background-image:linear-gradient(135deg,#FF7A30,#FF4D12)">시작하기</a>
        </div>
    </div>
    <div class="border-t border-gray-50 hidden md:block">
        <div class="max-w-7xl mx-auto px-4 flex justify-center items-center h-10 overflow-x-auto scrollbar-hide">
            @foreach ([
                ['label' => '홈', 'path' => '/'],
                ['label' => '커뮤니티', 'path' => '/community'],
                ['label' => 'Q&A', 'path' => '/qa'],
                ['label' => '구인구직', 'path' => '/jobs'],
                ['label' => '중고장터', 'path' => '/market'],
                ['label' => '업소록', 'path' => '/directory'],
                ['label' => '부동산', 'path' => '/realestate'],
                ['label' => '이벤트', 'path' => '/events'],
                ['label' => '뉴스', 'path' => '/news'],
                ['label' => '정보', 'path' => '/info'],
                ['label' => '레시피', 'path' => '/recipes'],
                ['label' => '동호회', 'path' => '/clubs'],
                ['label' => '게임', 'path' => '/games'],
                ['label' => '숏츠', 'path' => '/shorts'],
                ['label' => '음악듣기', 'path' => '/music'],
                ['label' => '공동구매', 'path' => '/groupbuy'],
            ] as $item)
                <a href="{{ $item['path'] }}"
                   class="text-[13px] font-semibold px-3 py-2.5 border-b-2 whitespace-nowrap transition-colors duration-150 {{ $item['path'] === '/info' ? 'border-amber-400 text-amber-600' : 'border-transparent text-ink-light hover:text-ink' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    </div>
</nav>
