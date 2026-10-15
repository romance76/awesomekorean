{{-- 본 사이트 NavBar.vue와 같은 모양(로고/검색/알림/아바타/EN 번역 토글/메뉴 행)을
     서버사이드 Blade로 재현. 이 페이지는 Vue Router 밖(일반 네비게이션)이라 서버는
     로그인 상태를 모르지만, 로그인 토큰 자체는 (세션 쿠키가 아니라) localStorage/
     sessionStorage의 sk_token/sk_user에 있으므로, 아래 작은 스크립트로 로드 직후
     그것만 읽어서 로그인 중이면 게스트 버튼 대신 알림벨+아바타(마이페이지/로그아웃)로
     바꿔치기한다. --}}
@php
    // 본 사이트(siteStore.logoUrl)와 같은 로고 — 관리자 설정(site_settings.logo_url), 없으면 기본 로고
    $logoUrl = \App\Models\SiteSetting::where('key', 'logo_url')->value('value') ?: '/images/logo.png?v=20261015';
    // 상단 메뉴 묶음 (관리자 › 메뉴 묶음이 켜져 있을 때만) — NavBar.vue 와 같은 모양
    $navGroups = \App\Support\MenuGroups::publicGroups($menus ?? []);
    $isInfoGroup = fn ($g) => ($g['type'] === 'hub' && str_starts_with($g['path'], '/info')) || collect($g['items'])->contains(fn ($m) => str_starts_with($m['path'], '/info'));
    // "큰 글씨로 보기" 기능 스위치 (관리자 > 시스템). 꺼져 있으면 아이콘을 숨기고 화면도 원래대로
    $evRaw = \App\Models\SiteSetting::where('key', 'easy_view_enabled')->value('value');
    $evOn = !in_array($evRaw, ['0', 0, false, 'false'], true);
@endphp
@if ($evOn)
<script>try{var e=JSON.parse(localStorage.getItem('ak_easy_view')||'null');if(e&&e.level&&e.level!=='md'||e&&e.hc){var r=document.documentElement,l={lg:[1.25,1.2],xl:[1.5,1.4],md:[1,1]}[e.level]||[1,1];r.classList.add('easy-view');if(e.hc)r.classList.add('ev-hc');r.style.setProperty('--fs',l[0]);r.style.setProperty('--ui',l[1])}}catch(_){}</script>
@endif
{{-- 크기·간격은 app.css 의 .site-hdr-* 클래스 (NavBar.vue 와 공용) — 여기서 따로 숫자를 쓰지 말 것 --}}
<nav class="site-hdr ev-header bg-white/95 backdrop-blur-sm border-b border-gray-100 sticky top-0 z-50">
    <div class="site-hdr-row">
        {{-- 햄버거 메뉴 (모바일) — NavBar.vue 와 동일 --}}
        <button id="info-menu-btn" type="button" class="md:hidden p-2.5 -ml-1 text-ink-light hover:text-amber-500 transition-colors flex-shrink-0" aria-label="전체 메뉴">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <a href="/" class="flex items-center flex-shrink-0" aria-label="AwesomeKorean">
            <img src="{{ $logoUrl }}" alt="AwesomeKorean" class="site-hdr-logo">
        </a>
        <div class="flex-1 mx-2 min-w-0 hidden md:block">
            <form action="/search" method="GET" class="site-hdr-search-form">
                <button type="submit" aria-label="검색" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-faint hover:text-amber-500 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </button>
                <input type="text" name="q" placeholder="궁금한 것을 검색해 보세요 — 영주권, 맛집, 중고차…" class="site-hdr-search">
            </form>
        </div>
        <div class="flex-1 md:hidden"></div>

        {{-- 오른쪽 묶음: NavBar.vue 의 <div class="flex items-center gap-1.5 flex-shrink-0"> 와 같은 한 덩어리 (간격이 달라 검색창 위치가 흔들리던 문제) --}}
        <div class="flex items-center gap-1.5 flex-shrink-0">
            {{-- 큰 글씨로 보기 (EasyViewButton.vue 와 같은 모양·같은 저장 방식: localStorage ak_easy_view) — 로그인 중일 때만 --}}
            @if ($evOn)
            <div id="info-ev" class="relative flex-shrink-0" style="display:none">
                <button id="info-ev-btn" type="button" aria-label="큰 글씨로 보기" title="큰 글씨로 보기" class="relative p-2 text-ink-light hover:text-amber-500 transition-colors flex items-center justify-center" style="min-width:36px">
                    <span class="font-black leading-none ev-icon-text" style="font-size:17px">가<span style="font-size:11px">가</span></span>
                    <span id="info-ev-dot" class="absolute top-1 right-1 w-2 h-2 rounded-full bg-[#FC226B]" style="display:none"></span>
                </button>
            </div>
            @endif

            {{-- 알림벨 — 로그인 중일 때만 표시 --}}
            <div id="info-auth-notif" class="relative flex-shrink-0" style="display:none">
                <button id="info-notif-bell" type="button" class="relative p-2 text-ink-light hover:text-amber-500 transition-colors">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <span id="info-notif-badge" class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] w-4 h-4 rounded-full items-center justify-center font-bold" style="display:none">0</span>
                </button>
                <div id="info-notif-dropdown" class="notif-panel absolute right-0 top-10 bg-white rounded-2xl shadow-lift z-50 overflow-hidden flex flex-col" style="display:none; width:min(320px, calc(100vw - 2rem)); max-height:70vh">
                    <div class="px-4 py-3 flex items-center justify-between border-b border-gray-50 flex-shrink-0">
                        <span class="text-sm font-bold text-ink">알림</span>
                        <span class="flex items-center gap-3">
                            <button id="info-notif-readall" type="button" class="text-xs text-amber-600 hover:text-amber-700 font-semibold transition-colors" style="display:none">전체 읽음</button>
                            <button id="info-notif-clear" type="button" class="text-xs text-ink-muted hover:text-red-600 font-semibold transition-colors" style="display:none">읽은 알림 지우기</button>
                        </span>
                    </div>
                    <div id="info-notif-list" class="overflow-y-auto flex-1 min-h-0">
                        <div class="px-4 py-10 text-center text-sm text-ink-muted">알림이 없습니다</div>
                    </div>
                    <a href="/notifications" class="flex-shrink-0 block w-full py-2.5 text-center text-xs font-bold text-amber-700 bg-amber-50/70 hover:bg-amber-100/70 border-t border-gray-100">지난 알림 모두 보기 ›</a>
                </div>
            </div>

            <div id="info-auth-guest" class="flex items-center gap-1.5 flex-shrink-0">
                <a href="/login" class="text-[13px] font-semibold text-ink-light hover:text-ink hover:bg-surface px-3 py-1.5 rounded-full transition-colors">로그인</a>
                <a href="/register" class="text-[13px] text-white font-bold px-4 py-1.5 rounded-full transition-all shadow-btn" style="background-image:linear-gradient(135deg,#FF8A4D,#FC226B)">시작하기</a>
            </div>
            <div id="info-auth-user" class="relative flex-shrink-0" style="display:none">
                {{-- NavBar.vue 의 UserAvatar 와 같은 크기(휴대폰 46px · 그 외 58px) + 등급 링 — 아래 스크립트가 채움 --}}
                <button id="info-auth-avatar" type="button" class="block -my-1 rounded-full relative" aria-label="내 메뉴"></button>
                <div id="info-auth-dropdown" class="absolute right-0 top-14 bg-white rounded-2xl shadow-lift py-2 w-52 z-50" style="display:none">
                    <div class="px-4 py-2.5 border-b border-gray-50">
                        <div id="info-auth-name" class="text-sm font-bold text-ink truncate"></div>
                        <div id="info-auth-email" class="text-xs text-ink-faint truncate"></div>
                        <div class="flex items-center gap-3 mt-1">
                            <a href="/points" class="text-xs text-amber-600 font-bold flex items-center gap-1"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg><span id="info-auth-points">0</span>P</a>
                            <a href="/entries" class="text-xs text-violet-600 font-bold flex items-center gap-1"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg><span id="info-auth-entries">0</span></a>
                        </div>
                    </div>
                    <a href="/dashboard" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-light hover:bg-amber-50/60 hover:text-ink transition-colors">마이페이지</a>
                    <a href="/dashboard?tab=messages" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-light hover:bg-amber-50/60 hover:text-ink transition-colors">쪽지</a>
                    <a href="/friends" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-light hover:bg-amber-50/60 hover:text-ink transition-colors">친구</a>
                    <div id="info-auth-admin" style="display:none"><div class="border-t border-gray-50 my-1"></div><a href="/admin" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 transition-colors">관리자</a></div>
                    <div class="border-t border-gray-50 my-1"></div>
                    <button id="info-auth-logout" type="button" class="w-full flex items-center gap-2.5 text-left px-4 py-2.5 text-sm text-ink-muted hover:bg-gray-50 transition-colors">로그아웃</button>
                </div>
            </div>

            {{-- EN 번역 토글 — 로그인 여부와 무관하게 항상 표시 (NavBar.vue와 동일하게 Google Translate 쿠키 플립 방식) --}}
            <button id="info-lang-toggle" type="button" translate="no" class="notranslate text-[11px] font-bold px-2.5 py-1.5 rounded-full text-ink-muted bg-surface hover:bg-line transition-colors flex-shrink-0" title="Translate to English">
                <span translate="no" class="notranslate">EN</span>
            </button>
        </div>
    </div>
    @if ($navGroups)
    <div class="border-t border-gray-50 hidden md:block">
        <div class="site-hdr-menu">
            @foreach ($navGroups as $g)
                <div class="site-hdr-navitem" @if (!empty($g['login_required'])) data-lr="1" style="display:none" @endif>
                    <a href="{{ $g['path'] }}" class="site-hdr-tab {{ $isInfoGroup($g) ? 'site-hdr-tab-on' : 'site-hdr-tab-off' }} {{ !empty($g['image']) ? '!h-auto' : '' }}">
                        @if (!empty($g['image']))<img src="{{ $g['image'] }}" alt="{{ $g['label'] }}" class="h-12 w-auto max-w-[220px] object-contain block">@else<span @if (!empty($g['color']))style="color:{{ $g['color'] }}"@endif>{{ $g['label'] }}</span>@endif{!! ($g['type'] !== 'single' && count($g['items'])) ? '<span class="text-[9px] opacity-50">▼</span>' : '' !!}
                    </a>
                    @if ($g['type'] !== 'single' && count($g['items']))
                        <div class="site-hdr-drop">
                            @if ($g['type'] === 'hub')<a href="{{ $g['path'] }}" class="site-hdr-drop-item text-ink-light">{{ $g['icon'] }} {{ $g['label'] }} 홈</a>@endif
                            @foreach ($g['items'] as $m)
                                <a href="{{ $m['path'] }}" @if (!empty($m['login_required'])) data-lr="1" style="display:none" @endif class="site-hdr-drop-item {{ str_starts_with($m['path'], '/info') ? 'text-amber-700' : 'text-ink-light' }}">{{ $m['label'] }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    @else
    <div class="border-t border-gray-50 hidden md:block">
        <div class="max-w-7xl mx-auto px-4 flex justify-center items-center h-10 overflow-x-auto scrollbar-hide">
            {{-- 관리자 페이지 "메뉴 구성"에서 저장한 순서/활성화 상태(site_settings.menu_config)를
                 그대로 따름 — 홈(NavBar.vue)과 동일한 소스를 사용해 개수/순서가 어긋나지 않도록 함. --}}
            @foreach ($menus as $item)
                <a href="{{ $item['path'] }}" @if (!empty($item['login_required'])) data-lr="1" style="display:none" @endif
                   class="text-[13px] font-semibold px-3 py-2.5 border-b-2 whitespace-nowrap transition-colors duration-150 {{ $item['path'] === '/info' ? 'border-amber-400 text-amber-600' : 'border-transparent text-ink-light hover:text-ink' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    </div>
    @endif

</nav>

    {{-- 모바일 메뉴 (슬라이드) — nav 밖에 둬야 fixed 가 화면 기준으로 동작(NavBar.vue 는 body 로 Teleport).
         NavBar.vue 의 ☰ 메뉴와 같은 모양: 묶음 한 줄(아이콘 칩·큰 글씨·▾·★), 처음엔 접힘, ▾ 를 누르면 하위 메뉴, ★ 는 하단바 묶음 즐겨찾기(같은 저장 칸 sk_nav_fav_groups).
         아이콘은 App\Support\MenuIcons (본 사이트 AppIcon 과 같은 그림). NavBar.vue 의 ☰ 를 바꾸면 여기도 같이 바꿀 것 --}}
    @php $infoPath = fn ($p) => str_starts_with($p, '/info'); @endphp
    <div id="info-menu-overlay" class="fixed inset-0 bg-black/40 z-[999]" style="display:none"></div>
    <div id="info-menu-panel" class="fixed top-0 left-0 bottom-0 bg-white z-[1000] shadow-2xl overflow-y-auto" style="display:none; width: 78vw; min-width: 250px; max-width: 330px; padding-top: var(--sat); padding-bottom: var(--sab)">
        <div class="flex items-center justify-between px-4 py-2 border-b border-gray-50">
            <span class="text-sm font-bold text-ink">전체 메뉴</span>
            <button id="info-menu-close" type="button" class="p-1 text-ink-faint hover:text-ink transition-colors" aria-label="닫기">{!! \App\Support\MenuIcons::svg('x', 20) !!}</button>
        </div>
        <div class="px-3 py-1.5 border-b border-gray-50">
            <form action="/search" method="GET" class="relative">
                <button type="submit" aria-label="검색" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-faint">{!! \App\Support\MenuIcons::svg('search', 16) !!}</button>
                <input type="text" name="q" placeholder="검색"
                    class="w-full bg-surface border-[1.5px] border-line rounded-full pl-10 pr-4 py-2 text-sm text-ink outline-none placeholder:text-ink-faint focus:bg-white focus:border-amber-400 focus:ring-4 focus:ring-amber-400/10 transition-all">
            </form>
        </div>
        <div class="py-1">
            @if ($navGroups)
                @foreach ($navGroups as $g)
                    @php
                        $first = $g['items'][0] ?? null;
                        $iconKey = $g['type'] === 'hub' ? 'money' : ($first['key'] ?? '');
                        $hasSub = $g['type'] === 'group';
                        $active = ($g['type'] === 'hub' && $infoPath($g['path'])) || collect($g['items'])->contains(fn ($m) => $infoPath($m['path']));
                    @endphp
                    <div @if (!empty($g['login_required'])) data-lr="1" style="display:none" @endif>
                        <div class="flex items-center transition-colors {{ $active ? 'bg-amber-50/70' : 'hover:bg-gray-50' }}">
                            <a href="{{ $g['path'] }}" class="flex items-center gap-3 flex-1 min-w-0 py-1 px-3.5 text-[15px] font-semibold {{ $active ? 'text-amber-700' : 'text-gray-800' }}">
                                <span class="icon-chip w-8 h-8 {{ \App\Support\MenuIcons::chip($iconKey) }}">{!! \App\Support\MenuIcons::forKey($iconKey, 17) !!}</span>@if (!empty($g['image']))<img src="{{ $g['image'] }}" alt="" class="h-8 w-auto max-w-[96px] object-contain shrink-0">@endif<span class="whitespace-nowrap" @if (!empty($g['color']))style="color:{{ $g['color'] }}"@endif>{{ $g['label'] }}</span>
                            </a>
                            @if ($hasSub)
                                <button type="button" data-open-toggle="{{ $g['key'] }}" class="px-1.5 py-2 flex-shrink-0 text-ink-faint" aria-expanded="false" aria-label="펼치기: {{ $g['label'] }}"><span class="inline-block transition-transform" data-chev>{!! \App\Support\MenuIcons::svg('chevron-down', 18) !!}</span></button>
                            @endif
                            <button type="button" data-fav-group="{{ $g['key'] }}" class="pl-0.5 pr-2.5 py-1.5 flex-shrink-0 transition-colors text-gray-300" aria-label="하단바에 넣기: {{ $g['label'] }}" title="하단바에 추가">{!! \App\Support\MenuIcons::svg('star', 20) !!}</button>
                        </div>
                        @if ($hasSub)
                            <div data-sub="{{ $g['key'] }}" style="display:none">
                                @foreach ($g['items'] as $m)
                                    <div @if (!empty($m['login_required'])) data-lr="1" style="display:none" @endif class="flex items-center transition-colors {{ $infoPath($m['path']) ? 'bg-amber-50/70' : 'hover:bg-gray-50' }}">
                                        <a href="{{ $m['path'] }}" class="flex items-center gap-2.5 flex-1 min-w-0 py-1 pl-14 pr-4 text-[13px] {{ $infoPath($m['path']) ? 'text-amber-700 font-bold' : 'font-normal text-ink-light' }}">
                                            <span class="icon-chip w-6 h-6 {{ \App\Support\MenuIcons::chip($m['key']) }}">{!! \App\Support\MenuIcons::forKey($m['key'], 13) !!}</span>
                                            <span>{{ $m['label'] }}</span>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
                {{-- MY (로그인했을 때만, NavBar.vue 와 같게) — 마이도 묶음처럼 한 줄 + ▾ + ★ --}}
                <div data-lr="1" style="display:none">
                    <div class="flex items-center transition-colors hover:bg-gray-50">
                        <a href="/dashboard" class="flex items-center gap-3 flex-1 min-w-0 py-1 px-3.5 text-[15px] font-semibold text-gray-800">
                            <span class="icon-chip w-8 h-8 {{ \App\Support\MenuIcons::chip('dashboard') }}">{!! \App\Support\MenuIcons::forKey('dashboard', 17) !!}</span><span>마이</span>
                        </a>
                        <button type="button" data-open-toggle="dashboard" class="px-1.5 py-2 flex-shrink-0 text-ink-faint" aria-expanded="false" aria-label="펼치기: 마이"><span class="inline-block transition-transform" data-chev>{!! \App\Support\MenuIcons::svg('chevron-down', 18) !!}</span></button>
                        <button type="button" data-fav-group="dashboard" class="pl-0.5 pr-2.5 py-1.5 flex-shrink-0 transition-colors text-gray-300" aria-label="하단바에 넣기: 마이" title="하단바에 추가">{!! \App\Support\MenuIcons::svg('star', 20) !!}</button>
                    </div>
                    <div data-sub="dashboard" style="display:none">
                        <a href="/dashboard" class="flex items-center gap-2.5 pl-14 pr-4 py-1 text-[13px] text-ink-light hover:bg-gray-50">{!! \App\Support\MenuIcons::svg('user', 13) !!}마이페이지</a>
                        <a href="/dashboard?tab=messages" class="flex items-center gap-2.5 pl-14 pr-4 py-1 text-[13px] text-ink-light hover:bg-gray-50">{!! \App\Support\MenuIcons::svg('mail', 13) !!}쪽지</a>
                        <a href="/friends" class="flex items-center gap-2.5 pl-14 pr-4 py-1 text-[13px] text-ink-light hover:bg-gray-50">{!! \App\Support\MenuIcons::svg('heart-handshake', 13) !!}친구</a>
                    </div>
                </div>
            @else
                @foreach ($menus as $item)
                    <div @if (!empty($item['login_required'])) data-lr="1" style="display:none" @endif class="flex items-center transition-colors {{ $infoPath($item['path']) ? 'bg-amber-50/70' : 'hover:bg-gray-50' }}">
                        <a href="{{ $item['path'] }}" class="flex items-center gap-3 flex-1 min-w-0 py-2 px-4 text-sm {{ $infoPath($item['path']) ? 'text-amber-700 font-bold' : 'text-ink-light' }}">
                            <span class="icon-chip w-8 h-8 {{ \App\Support\MenuIcons::chip($item['key'] ?? '') }}">{!! \App\Support\MenuIcons::forKey($item['key'] ?? '', 16) !!}</span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    </div>
                @endforeach
            @endif
        </div>
        <div class="px-4 py-1.5 border-t border-gray-50 text-[11px] text-ink-faint flex items-center gap-1">{!! \App\Support\MenuIcons::svg('star', 12) !!} 눌러서 하단 메뉴에 추가/제거</div>
    </div>

{{-- Google Translate 위젯 — welcome.blade.php(SPA)와 동일한 초기화. 이 페이지는 SPA
     셸을 전혀 안 쓰므로, EN 토글이 실제로 번역되게 하려면 여기서도 따로 로드해야 함. --}}
<style>
.goog-te-banner-frame, .goog-te-banner-frame.skiptranslate, .goog-te-gadget,
#goog-gt-tt, .goog-tooltip, .goog-tooltip:hover,
iframe.VIpgJd-ZVi9od-ORHb-OEVmcd, iframe.VIpgJd-ZVi9od-ORHb,
.VIpgJd-ZVi9od-ORHb-OEVmcd, .VIpgJd-ZVi9od-ORHb, .VIpgJd-yQoo3f-LgbsSe { display: none !important; visibility: hidden !important; }
body { top: 0 !important; position: static !important; }
html { margin-top: 0 !important; }
.goog-text-highlight { background: transparent !important; box-shadow: none !important; }
</style>
<div id="google_translate_element" style="display:none"></div>
<script>
function googleTranslateElementInit() {
    new google.translate.TranslateElement({
        pageLanguage: 'ko',
        includedLanguages: 'en,ko,ja,zh-CN,es,vi',
        autoDisplay: false,
        layout: google.translate.TranslateElement.InlineLayout.SIMPLE
    }, 'google_translate_element');
}
</script>
<script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" defer></script>

<script>
(function () {
    // 헤더 실제 높이를 --hdr-h 로 공유 (NavBar.vue 와 같음) — 왼쪽·오른쪽 따라오는 칸이 헤더 밑에 가려지지 않게
    try {
        var hdr = document.querySelector('nav.site-hdr');
        var setH = function () { var h = hdr.offsetHeight; if (h) document.documentElement.style.setProperty('--hdr-h', h + 'px'); };
        setH(); window.addEventListener('resize', setH);
    } catch (e) {}

    // 모바일 햄버거 메뉴 열기/닫기
    try {
        var panel = document.getElementById('info-menu-panel');
        var overlay = document.getElementById('info-menu-overlay');
        function setMenu(open) {
            panel.style.display = open ? 'block' : 'none';
            overlay.style.display = open ? 'block' : 'none';
        }
        document.getElementById('info-menu-btn').addEventListener('click', function () { setMenu(panel.style.display === 'none'); });
        document.getElementById('info-menu-close').addEventListener('click', function () { setMenu(false); });
        overlay.addEventListener('click', function () { setMenu(false); });
    } catch (e) {}

    // ☰ 메뉴의 묶음 펼치기(▾)·하단바 즐겨찾기(★) — NavBar.vue / navFavorites.js 와 같은 규칙, 같은 저장 칸(sk_nav_fav_groups·sk_nav_favorites)
    try {
        var panelEl = document.getElementById('info-menu-panel');
        var GROUPS = @json(collect($navGroups ?? [])->map(fn ($g) => ['key' => $g['key'], 'items' => collect($g['items'])->pluck('key')->values()])->values());
        var GKEY = 'sk_nav_fav_groups', OKEY = 'sk_nav_favorites';
        var DEFAULT_KEYS = ['home', 'community', 'games', 'chat', 'dashboard'];
        var GROUP_DEFAULTS = ['home', 'community', 'money', 'dashboard'];
        var groupFavs = function () {
            try { var s = JSON.parse(localStorage.getItem(GKEY)); if (Array.isArray(s)) return s; } catch (e) {}
            var old = DEFAULT_KEYS;
            try { var p = JSON.parse(localStorage.getItem(OKEY)); if (Array.isArray(p) && p.length >= 3) old = p; } catch (e) {}
            var out = [];
            GROUPS.forEach(function (g) {
                if (old.indexOf(g.key) >= 0 || g.items.some(function (k) { return old.indexOf(k) >= 0; })) out.push(g.key);
            });
            if (old.indexOf('dashboard') >= 0) out.push('dashboard');
            if (out.length >= 3) return out;
            return GROUP_DEFAULTS.filter(function (k) { return k === 'dashboard' || GROUPS.some(function (g) { return g.key === k; }); });
        };
        var paintStars = function () {
            var favs = groupFavs();
            panelEl.querySelectorAll('[data-fav-group]').forEach(function (b) {
                var on = favs.indexOf(b.getAttribute('data-fav-group')) >= 0;
                b.classList.toggle('text-amber-400', on);
                b.classList.toggle('text-gray-300', !on);
                var svg = b.querySelector('svg');
                if (svg) svg.setAttribute('fill', on ? 'currentColor' : 'none');
                var label = (b.getAttribute('aria-label') || '').split(': ')[1] || '';
                b.setAttribute('aria-label', (on ? '하단바에서 빼기: ' : '하단바에 넣기: ') + label);
                b.title = on ? '하단바에서 제거' : '하단바에 추가';
            });
        };
        paintStars();
        panelEl.querySelectorAll('[data-fav-group]').forEach(function (b) {
            b.addEventListener('click', function () {
                var key = b.getAttribute('data-fav-group'), cur = groupFavs().slice(), i = cur.indexOf(key);
                if (i >= 0) { if (cur.length <= 3) return; cur.splice(i, 1); } else { cur.push(key); }   // 최소 3개는 유지
                try { localStorage.setItem(GKEY, JSON.stringify(cur)); } catch (e) {}
                paintStars();
            });
        });
        panelEl.querySelectorAll('[data-open-toggle]').forEach(function (b) {
            b.addEventListener('click', function () {
                var key = b.getAttribute('data-open-toggle');
                var sub = panelEl.querySelector('[data-sub="' + key + '"]');
                var open = sub.style.display === 'none';
                sub.style.display = open ? 'block' : 'none';
                b.setAttribute('aria-expanded', open ? 'true' : 'false');
                var label = (b.getAttribute('aria-label') || '').split(': ')[1] || '';
                b.setAttribute('aria-label', (open ? '접기: ' : '펼치기: ') + label);
                var chev = b.querySelector('[data-chev]');
                if (chev) chev.style.transform = open ? 'rotate(180deg)' : '';
            });
        });
    } catch (e) {}

    // EN 번역 토글 — 로그인 여부와 무관하게 항상 동작해야 하므로 별도 try 블록
    try {
        var enBtn = document.getElementById('info-lang-toggle');
        function isTranslated() { return document.cookie.indexOf('googtrans=/ko/en') !== -1; }
        function syncEnLabel() {
            var translated = isTranslated();
            enBtn.querySelector('span').textContent = translated ? '한' : 'EN';
            enBtn.title = translated ? '한국어로 돌아가기' : 'Translate to English';
        }
        syncEnLabel();
        enBtn.addEventListener('click', function () {
            var host = location.hostname;
            var root = host.replace(/^www\./, '');
            var domains = ['', host, '.' + host, '.' + root];
            domains.forEach(function (d) {
                document.cookie = 'googtrans=; path=/; max-age=0' + (d ? '; domain=' + d : '');
            });
            if (!isTranslated()) {
                document.cookie = 'googtrans=/ko/en; path=/';
                document.cookie = 'googtrans=/ko/en; path=/; domain=.' + root;
            }
            location.reload();
        });
    } catch (e) {}

    // 로그인 상태 반영 — 알림벨 + 아바타(마이페이지/로그아웃)
    try {
        var raw = sessionStorage.getItem('sk_user') || localStorage.getItem('sk_user');
        var token = sessionStorage.getItem('sk_token') || localStorage.getItem('sk_token');
        if (!raw || !token) return;
        var user = JSON.parse(raw);

        document.getElementById('info-auth-guest').style.display = 'none';
        var userBox = document.getElementById('info-auth-user');
        document.querySelectorAll('[data-lr]').forEach(function (el) { el.style.display = ''; });   // 로그인해야 보이는 메뉴
        userBox.style.display = 'block';

        document.getElementById('info-auth-name').textContent = user.name || '';
        document.getElementById('info-auth-email').textContent = user.email || '';
        document.getElementById('info-auth-points').textContent = Number(user.points || 0).toLocaleString();
        document.getElementById('info-auth-entries').textContent = Number(user.entries || 0).toLocaleString();
        if (user.role === 'admin' || user.role === 'super_admin') document.getElementById('info-auth-admin').style.display = 'block';

        // ── 큰 글씨로 보기 (EasyViewButton.vue / useEasyView.js 와 같은 규칙·같은 저장 칸 ak_easy_view) ──
        var evBox = document.getElementById('info-ev');
        if (evBox) {
            evBox.style.display = 'block';
            var EV_KEY = 'ak_easy_view';
            var EV_LEVELS = [{ v: 'md', l: '보통', fs: 1, ui: 1 }, { v: 'lg', l: '크게', fs: 1.25, ui: 1.2 }, { v: 'xl', l: '아주 크게', fs: 1.5, ui: 1.4 }];
            var evLv = function (v) { for (var i = 0; i < EV_LEVELS.length; i++) if (EV_LEVELS[i].v === v) return EV_LEVELS[i]; return EV_LEVELS[0]; };
            var evPrefs = (function () { try { var o = JSON.parse(localStorage.getItem(EV_KEY)); if (o && EV_LEVELS.some(function (x) { return x.v === o.level; })) return { level: o.level, hc: !!o.hc, ts: Number(o.ts) || 0 }; } catch (e) {} return { level: 'md', hc: false, ts: 0 }; })();
            var evTimer = null;
            var evApply = function () {
                var root = document.documentElement, lv = evLv(evPrefs.level), on = lv.v !== 'md' || evPrefs.hc;
                root.classList.toggle('easy-view', on); root.classList.toggle('ev-hc', on && evPrefs.hc);
                if (on) { root.style.setProperty('--fs', String(lv.fs)); root.style.setProperty('--ui', String(lv.ui)); root.style.setProperty('--fs-h', String(+(1 + (lv.fs - 1) * 0.15).toFixed(3))); }
                else { root.style.removeProperty('--fs'); root.style.removeProperty('--ui'); root.style.removeProperty('--fs-h'); }
                document.getElementById('info-ev-dot').style.display = on ? 'block' : 'none';
            };
            var evPush = function () {
                clearTimeout(evTimer);
                evTimer = setTimeout(function () {
                    fetch('/api/user/easy-view', { method: 'PUT', headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ level: evPrefs.level, hc: evPrefs.hc, ts: evPrefs.ts }) }).catch(function () {});
                }, 600);
            };
            var evModal = null;
            var evRender = function () {
                if (!evModal) return;
                evModal.querySelectorAll('[data-lv]').forEach(function (b) {
                    var on = evPrefs.level === b.getAttribute('data-lv');
                    b.setAttribute('aria-pressed', on ? 'true' : 'false');
                    b.style.borderColor = on ? '#FC226B' : '#e5e7eb'; b.style.background = on ? '#fff1f5' : ''; b.style.color = on ? '#FC226B' : '';
                });
                var hcBox = evModal.querySelector('[data-hc]'); hcBox.checked = evPrefs.hc;
                var hcLab = hcBox.parentNode; hcLab.style.borderColor = evPrefs.hc ? '#FC226B' : '#e5e7eb'; hcLab.style.background = evPrefs.hc ? '#fff1f5' : '';
            };
            var evSet = function (p) {
                evPrefs = { level: p.level !== undefined ? p.level : evPrefs.level, hc: p.hc !== undefined ? p.hc : evPrefs.hc, ts: Date.now() };
                try { localStorage.setItem(EV_KEY, JSON.stringify(evPrefs)); localStorage.setItem('ak_easy_view_hint', '1'); } catch (e) {}
                evApply(); evPush(); evRender();
            };
            // 이 폰의 값과 내 계정의 값 중 더 최근에 바꾼 쪽을 따른다 (useEasyView.js 의 syncEasyViewFromUser 와 같음)
            if (typeof user.easy_view_prefs === 'string' && user.easy_view_prefs) {
                var pp = user.easy_view_prefs.split('|'), sts = Number(pp[2]) || 0;
                if (EV_LEVELS.some(function (x) { return x.v === pp[0]; }) && sts > evPrefs.ts) { evPrefs = { level: pp[0], hc: pp[1] === '1', ts: sts }; try { localStorage.setItem(EV_KEY, JSON.stringify(evPrefs)); } catch (e) {} }
            }
            evApply();
            var evOpen = function () {
                if (!evModal) {
                    evModal = document.createElement('div');
                    evModal.className = 'fixed inset-0 z-[9996] flex items-end sm:items-center justify-center';
                    var lvBtns = EV_LEVELS.map(function (o) {
                        return '<button type="button" data-lv="' + o.v + '" class="rounded-2xl border-2 flex flex-col items-center justify-center gap-0.5" style="min-height:72px;border-color:#e5e7eb"><span class="font-extrabold" style="font-size:' + (15 * o.fs) + 'px;line-height:1.1">가</span><span class="text-[13px] font-bold">' + o.l + '</span></button>';
                    }).join('');
                    evModal.innerHTML = '<div class="absolute inset-0 bg-black/40" data-close="1"></div>'
                        + '<div class="relative bg-white w-full sm:max-w-sm rounded-t-3xl sm:rounded-2xl p-5 space-y-4" style="padding-bottom:calc(20px + env(safe-area-inset-bottom,0px));font-size:16px" role="dialog" aria-label="큰 글씨로 보기">'
                        + '<div class="flex items-center justify-between"><div class="text-[19px] font-extrabold text-ink">큰 글씨로 보기</div><button type="button" data-close="1" class="w-11 h-11 -mr-2 rounded-full text-ink-light text-[22px]" aria-label="닫기">✕</button></div>'
                        + '<div><div class="text-[14px] text-ink-light mb-2">글씨 크기</div><div class="grid grid-cols-3 gap-2" role="group" aria-label="글씨 크기">' + lvBtns + '</div></div>'
                        + '<label class="flex items-center gap-3 rounded-2xl border-2 px-4 cursor-pointer" style="min-height:56px;border-color:#e5e7eb"><input type="checkbox" data-hc="1" style="width:24px;height:24px;accent-color:#FC226B"><span class="text-[16px] font-bold text-ink">글씨 진하게·선명하게</span></label>'
                        + '<div class="flex gap-2"><button type="button" data-reset="1" class="flex-1 rounded-2xl border-2 border-gray-200 text-[15px] font-bold text-ink-light" style="min-height:52px">처음 상태로</button><button type="button" data-close="1" class="flex-1 rounded-2xl bg-[#FC226B] text-white text-[16px] font-extrabold" style="min-height:52px">닫기</button></div>'
                        + '<p class="text-[12px] text-ink-faint text-center leading-snug">한 번 정하면 기억해요. 다시 들어오거나 다른 기기에서 로그인해도 같은 크기로 보여요.</p></div>';
                    evModal.addEventListener('click', function (e) {
                        var t = e.target;
                        if (t.closest && t.closest('[data-close]')) { evModal.style.display = 'none'; return; }
                        var lvb = t.closest && t.closest('[data-lv]'); if (lvb) { evSet({ level: lvb.getAttribute('data-lv') }); return; }
                        if (t.closest && t.closest('[data-reset]')) { evSet({ level: 'md', hc: false }); }
                    });
                    evModal.querySelector('[data-hc]').addEventListener('change', function (e) { evSet({ hc: e.target.checked }); });
                    document.body.appendChild(evModal);
                }
                evModal.style.display = 'flex'; evRender();
            };
            document.getElementById('info-ev-btn').addEventListener('click', evOpen);
        }

        // UserAvatar.vue 와 같은 규칙: 전체 크기 = 휴대폰(430px 이하) 46 / 그 외 58, 등급 링이 있으면 사진 지름 62%, 없으면 74%
        var avatar = document.getElementById('info-auth-avatar');
        var size = window.innerWidth <= 430 ? 46 : 58;
        var lvl = parseInt(user.grade_level || (user.grade && user.grade.level), 10);
        var hasRing = lvl >= 1 && lvl <= 15;
        var d = Math.round(size * (hasRing ? 0.62 : 0.74));
        avatar.style.cssText = 'width:' + size + 'px;height:' + size + 'px';
        var face = document.createElement('span');
        face.style.cssText = 'position:absolute;border-radius:9999px;overflow:hidden;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;'
            + 'width:' + d + 'px;height:' + d + 'px;left:' + ((size - d) / 2) + 'px;top:' + ((size - d) / 2) + 'px;background:' + (user.avatar ? '#f3f4f6' : '#fbbf24');
        var av = user.avatar || '';
        if (av && !/^(https?:)?\/\//.test(av) && av.charAt(0) !== '/' && av.indexOf('data:') !== 0) av = '/storage/' + av;
        if (av) {
            var img = document.createElement('img');
            img.src = av; img.alt = '';
            img.style.cssText = 'width:100%;height:100%;object-fit:cover';
            face.appendChild(img);
        } else {
            face.style.fontSize = Math.round(d * 0.42) + 'px';
            face.textContent = ((user.nickname || user.name || '?').trim().charAt(0)) || '?';
        }
        avatar.appendChild(face);
        if (hasRing) {
            var ring = document.createElement('img');
            ring.src = '/images/grades/level_' + String(lvl).padStart(2, '0') + '_s.png'; ring.alt = '';
            ring.setAttribute('aria-hidden', 'true');
            ring.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;pointer-events:none';
            avatar.appendChild(ring);
        }

        var dropdown = document.getElementById('info-auth-dropdown');
        avatar.addEventListener('click', function () {
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        });
        document.addEventListener('click', function (e) {
            if (!userBox.contains(e.target)) dropdown.style.display = 'none';
        });

        document.getElementById('info-auth-logout').addEventListener('click', function () {
            localStorage.removeItem('sk_token'); localStorage.removeItem('sk_user');
            sessionStorage.removeItem('sk_token'); sessionStorage.removeItem('sk_user');
            location.reload();
        });

        // 알림벨 — NavBar.vue와 동일한 /api/notifications 를 토큰 직접 첨부해서 호출
        function authHeaders() { return { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }; }

        function formatNotifDate(s) {
            if (!s) return '';
            var d = new Date(s.replace(' ', 'T'));
            if (isNaN(d.getTime())) return '';
            var now = new Date();
            var mm = String(d.getMinutes()).padStart(2, '0');
            var hh = String(d.getHours()).padStart(2, '0');
            var m = d.getMonth() + 1, day = d.getDate();
            if (d.getFullYear() === now.getFullYear()) return m + '/' + day + ' ' + hh + ':' + mm;
            return d.getFullYear() + '.' + m + '.' + day + ' ' + hh + ':' + mm;
        }

        // 알림 타입별 이동 경로 결정 (NavBar.vue의 resolveNotifRoute와 동일한 규칙)
        function resolveNotifRoute(n) {
            var d = n.data || {};
            if (d.url) return d.url;
            if (n.type === 'message') return '/dashboard?tab=messages';
            if (n.type === 'friend_request') return '/friends';
            if (n.type === 'elder_call_missed') return '/elder/guardian';
            if (n.type === 'elder_checkin_missed') return (d.elder_user_id || d.ward_id) ? '/elder/guardian' : '/elder/checkin';
            if (n.type && n.type.indexOf('elder_') === 0) return '/elder';
            if (n.type === 'market_reservation_expired' || n.type === 'reservation_expired') return d.item_id ? ('/market/' + d.item_id) : '/market';
            if (n.type === 'comment' && d.post_id) return '/community/post/' + d.post_id;
            if (n.type === 'system') return '/dashboard';
            return '/dashboard';
        }

        function clickNotif(n) {
            if (!n.read_at) {
                fetch('/api/notifications/' + n.id + '/read', { method: 'POST', headers: authHeaders() }).catch(function () {});
            }
            window.location.href = resolveNotifRoute(n);
        }

        function renderNotifs(list, unread) {
            var badge = document.getElementById('info-notif-badge');
            if (unread > 0) {
                badge.style.display = 'flex';
                badge.textContent = unread > 9 ? '9+' : String(unread);
            } else {
                badge.style.display = 'none';
            }
            document.getElementById('info-notif-readall').style.display = list.some(function (n) { return !n.read_at; }) ? 'inline-block' : 'none';
            document.getElementById('info-notif-clear').style.display = list.some(function (n) { return n.read_at; }) ? 'inline-block' : 'none';

            var container = document.getElementById('info-notif-list');
            container.innerHTML = '';
            if (!list.length) {
                var empty = document.createElement('div');
                empty.className = 'px-4 py-10 text-center text-sm text-ink-muted';
                empty.textContent = '알림이 없습니다';
                container.appendChild(empty);
                return;
            }
            list.forEach(function (n) {
                var item = document.createElement('div');
                item.className = 'px-4 py-2.5 border-b border-gray-50 last:border-0 cursor-pointer hover:bg-amber-50/40 transition-colors' + (n.read_at ? '' : ' bg-amber-50/60');
                var row = document.createElement('div');
                row.className = 'flex items-start gap-2';
                var dot = document.createElement('span');
                dot.className = n.read_at ? 'w-2 h-2 flex-shrink-0' : 'w-2 h-2 bg-amber-400 rounded-full flex-shrink-0 mt-1.5';
                var textWrap = document.createElement('div');
                textWrap.className = 'min-w-0 flex-1';
                textWrap.style.overflowWrap = 'anywhere';
                var titleEl = document.createElement('div');
                titleEl.className = 'text-xs font-semibold text-ink break-words line-clamp-2';
                titleEl.textContent = n.title || '';
                var contentEl = document.createElement('div');
                contentEl.className = 'text-xs text-ink-muted break-words line-clamp-2';
                contentEl.textContent = n.content || '';
                var dateEl = document.createElement('div');
                dateEl.className = 'text-[11px] text-ink-faint mt-0.5';
                dateEl.textContent = formatNotifDate(n.created_at);
                textWrap.appendChild(titleEl); textWrap.appendChild(contentEl); textWrap.appendChild(dateEl);
                var del = document.createElement('button');
                del.type = 'button'; del.textContent = '✕'; del.title = '지우기'; del.setAttribute('aria-label', '이 알림 지우기');
                del.className = 'flex-shrink-0 w-6 h-6 -mr-1 rounded-full text-ink-faint hover:bg-gray-100 hover:text-red-600 flex items-center justify-center text-sm leading-none';
                del.addEventListener('click', function (e) {
                    e.stopPropagation();
                    fetch('/api/notifications/' + n.id, { method: 'DELETE', headers: authHeaders() }).then(loadNotifs).catch(function () {});
                });
                row.appendChild(dot); row.appendChild(textWrap); row.appendChild(del);
                item.appendChild(row);
                item.addEventListener('click', function () { clickNotif(n); });
                container.appendChild(item);
            });
        }

        function loadNotifs() {
            fetch('/api/notifications?limit=8', { headers: authHeaders() })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var list = (data.data && data.data.data) || data.data || [];
                    renderNotifs(list, data.unread_count || 0);
                })
                .catch(function () {});
        }

        var notifBox = document.getElementById('info-auth-notif');
        notifBox.style.display = 'block';
        var notifDropdown = document.getElementById('info-notif-dropdown');
        document.getElementById('info-notif-bell').addEventListener('click', function () {
            var opening = notifDropdown.style.display === 'none';
            notifDropdown.style.display = opening ? 'block' : 'none';
            if (opening) loadNotifs();
        });
        document.getElementById('info-notif-readall').addEventListener('click', function () {
            fetch('/api/notifications/read', { method: 'POST', headers: authHeaders() }).then(loadNotifs).catch(function () {});
        });
        document.getElementById('info-notif-clear').addEventListener('click', function () {
            fetch('/api/notifications/clear', { method: 'POST', headers: Object.assign({ 'Content-Type': 'application/json' }, authHeaders()), body: JSON.stringify({ scope: 'read' }) }).then(loadNotifs).catch(function () {});
        });
        document.addEventListener('click', function (e) {
            if (!notifBox.contains(e.target)) notifDropdown.style.display = 'none';
        });
        loadNotifs();
    } catch (e) {}
})();
</script>
