{{-- 본 사이트 NavBar.vue와 같은 모양(로고/검색/알림/아바타/EN 번역 토글/메뉴 행)을
     서버사이드 Blade로 재현. 이 페이지는 Vue Router 밖(일반 네비게이션)이라 서버는
     로그인 상태를 모르지만, 로그인 토큰 자체는 (세션 쿠키가 아니라) localStorage/
     sessionStorage의 sk_token/sk_user에 있으므로, 아래 작은 스크립트로 로드 직후
     그것만 읽어서 로그인 중이면 게스트 버튼 대신 알림벨+아바타(마이페이지/로그아웃)로
     바꿔치기한다. --}}
@php
    // 본 사이트(siteStore.logoUrl)와 같은 로고 — 관리자 설정(site_settings.logo_url), 없으면 기본 로고
    $logoUrl = \App\Models\SiteSetting::where('key', 'logo_url')->value('value') ?: '/images/logo.png';
    // 상단 메뉴 묶음 (관리자 › 메뉴 묶음이 켜져 있을 때만) — NavBar.vue 와 같은 모양
    $navGroups = \App\Support\MenuGroups::publicGroups($menus ?? []);
    $isInfoGroup = fn ($g) => ($g['type'] === 'hub' && str_starts_with($g['path'], '/info')) || collect($g['items'])->contains(fn ($m) => str_starts_with($m['path'], '/info'));
@endphp
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

        {{-- 알림벨 — 로그인 중일 때만 표시 --}}
        <div id="info-auth-notif" class="relative flex-shrink-0" style="display:none">
            <button id="info-notif-bell" type="button" class="relative p-2 text-ink-light hover:text-amber-500 transition-colors">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span id="info-notif-badge" class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] w-4 h-4 rounded-full items-center justify-center font-bold" style="display:none">0</span>
            </button>
            <div id="info-notif-dropdown" class="absolute right-0 top-10 bg-white rounded-2xl shadow-lift z-50 overflow-hidden" style="display:none; width:min(320px, calc(100vw - 2rem));">
                <div class="px-4 py-3 flex items-center justify-between border-b border-gray-50">
                    <span class="text-sm font-bold text-ink">알림</span>
                    <button id="info-notif-readall" type="button" class="text-xs text-amber-600 hover:text-amber-700 font-semibold transition-colors" style="display:none">전체 읽음</button>
                </div>
                <div id="info-notif-list" class="max-h-80 overflow-y-auto">
                    <div class="px-4 py-10 text-center text-sm text-ink-muted">알림이 없습니다</div>
                </div>
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
                </div>
                <a href="/dashboard" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-light hover:bg-amber-50/60 hover:text-ink transition-colors">마이페이지</a>
                <a href="/dashboard?tab=messages" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-light hover:bg-amber-50/60 hover:text-ink transition-colors">쪽지</a>
                <a href="/friends" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-light hover:bg-amber-50/60 hover:text-ink transition-colors">친구</a>
                <div class="border-t border-gray-50 my-1"></div>
                <button id="info-auth-logout" type="button" class="w-full flex items-center gap-2.5 text-left px-4 py-2.5 text-sm text-ink-muted hover:bg-gray-50 transition-colors">로그아웃</button>
            </div>
        </div>

        {{-- EN 번역 토글 — 로그인 여부와 무관하게 항상 표시 (NavBar.vue와 동일하게 Google Translate 쿠키 플립 방식) --}}
        <button id="info-lang-toggle" type="button" translate="no" class="notranslate text-[11px] font-bold px-2.5 py-1.5 rounded-full text-ink-muted bg-surface hover:bg-line transition-colors flex-shrink-0" title="Translate to English">
            <span translate="no" class="notranslate">EN</span>
        </button>
    </div>
    @if ($navGroups)
    <div class="border-t border-gray-50 hidden md:block">
        <div class="site-hdr-menu">
            @foreach ($navGroups as $g)
                <div class="site-hdr-navitem">
                    <a href="{{ $g['path'] }}" class="site-hdr-tab {{ $isInfoGroup($g) ? 'site-hdr-tab-on' : 'site-hdr-tab-off' }}">
                        {{ $g['label'] }}@if ($g['type'] !== 'single' && count($g['items']))<span class="text-[9px] opacity-50">▼</span>@endif
                    </a>
                    @if ($g['type'] !== 'single' && count($g['items']))
                        <div class="site-hdr-drop">
                            @if ($g['type'] === 'hub')<a href="{{ $g['path'] }}" class="site-hdr-drop-item text-ink-light">{{ $g['icon'] }} {{ $g['label'] }} 홈</a>@endif
                            @foreach ($g['items'] as $m)
                                <a href="{{ $m['path'] }}" class="site-hdr-drop-item {{ str_starts_with($m['path'], '/info') ? 'text-amber-700' : 'text-ink-light' }}">{{ $m['label'] }}</a>
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
                <a href="{{ $item['path'] }}"
                   class="text-[13px] font-semibold px-3 py-2.5 border-b-2 whitespace-nowrap transition-colors duration-150 {{ $item['path'] === '/info' ? 'border-amber-400 text-amber-600' : 'border-transparent text-ink-light hover:text-ink' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
    </div>
    @endif

</nav>

    {{-- 모바일 메뉴 (슬라이드) — nav 밖에 둬야 fixed 가 화면 기준으로 동작(NavBar.vue 는 body 로 Teleport). NavBar.vue 와 동일한 구성. 하단바 즐겨찾기(별)는 SPA 전용이라 제외 --}}
    <div id="info-menu-overlay" class="fixed inset-0 bg-black/40 z-[999]" style="display:none"></div>
    <div id="info-menu-panel" class="fixed top-0 left-0 bottom-0 w-[85vw] max-w-sm bg-white z-[1000] shadow-2xl overflow-y-auto" style="display:none">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-50">
            <span class="text-sm font-bold text-ink">전체 메뉴</span>
            <button id="info-menu-close" type="button" class="p-1 text-ink-faint hover:text-ink transition-colors" aria-label="닫기">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-3 py-2.5 border-b border-gray-50">
            <form action="/search" method="GET">
                <input type="text" name="q" placeholder="궁금한 것을 검색해 보세요"
                    class="w-full bg-surface border-[1.5px] border-line rounded-full px-4 py-3 text-sm text-ink outline-none placeholder:text-ink-faint">
            </form>
        </div>
        <div class="py-2">
            @if ($navGroups)
                @foreach ($navGroups as $g)
                    @if ($g['type'] === 'single')
                        @php $m = $g['items'][0]; @endphp
                        <a href="{{ $m['path'] }}" class="flex items-center gap-3 px-4 py-2 text-sm {{ $m['path'] === '/info' ? 'bg-amber-50/70 text-amber-700 font-bold' : 'text-ink-light hover:bg-gray-50' }}">
                            <span class="icon-chip w-8 h-8 bg-surface text-base">{{ $g['icon'] ?: '•' }}</span><span>{{ $g['label'] }}</span>
                        </a>
                    @else
                        <div class="px-4 pt-3 pb-1 text-[12px] font-extrabold text-ink-muted">{{ $g['icon'] }} @if ($g['type'] === 'hub')<a href="{{ $g['path'] }}" class="text-ink">{{ $g['label'] }} 홈 ›</a>@else{{ $g['label'] }}@endif</div>
                        @foreach ($g['items'] as $m)
                            <a href="{{ $m['path'] }}" class="flex items-center gap-3 pl-7 pr-4 py-2 text-sm {{ $m['path'] === '/info' ? 'bg-amber-50/70 text-amber-700 font-bold' : 'text-ink-light hover:bg-gray-50' }}">
                                <span class="icon-chip w-8 h-8 bg-surface text-base">{{ $m['icon'] ?: '•' }}</span><span>{{ $m['label'] }}</span>
                            </a>
                        @endforeach
                    @endif
                @endforeach
            @else
            @foreach ($menus as $item)
                <a href="{{ $item['path'] }}"
                   class="flex items-center gap-3 px-4 py-2 text-sm {{ $item['path'] === '/info' ? 'bg-amber-50/70 text-amber-700 font-bold' : 'text-ink-light hover:bg-gray-50' }}">
                    <span class="icon-chip w-8 h-8 bg-surface text-base">{{ $item['icon'] ?: '•' }}</span>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
            @endif
        </div>
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
        userBox.style.display = 'block';

        document.getElementById('info-auth-name').textContent = user.name || '';
        document.getElementById('info-auth-email').textContent = user.email || '';

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
                var titleEl = document.createElement('div');
                titleEl.className = 'text-xs font-semibold text-ink truncate';
                titleEl.textContent = n.title || '';
                var contentEl = document.createElement('div');
                contentEl.className = 'text-xs text-ink-muted truncate';
                contentEl.textContent = n.content || '';
                var dateEl = document.createElement('div');
                dateEl.className = 'text-[11px] text-ink-faint mt-0.5';
                dateEl.textContent = formatNotifDate(n.created_at);
                textWrap.appendChild(titleEl); textWrap.appendChild(contentEl); textWrap.appendChild(dateEl);
                row.appendChild(dot); row.appendChild(textWrap);
                item.appendChild(row);
                item.addEventListener('click', function () { clickNotif(n); });
                container.appendChild(item);
            });
        }

        function loadNotifs() {
            fetch('/api/notifications', { headers: authHeaders() })
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
        document.addEventListener('click', function (e) {
            if (!notifBox.contains(e.target)) notifDropdown.style.display = 'none';
        });
        loadNotifs();
    } catch (e) {}
})();
</script>
