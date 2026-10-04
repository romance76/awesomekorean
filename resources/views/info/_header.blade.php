{{-- 본 사이트 NavBar.vue와 같은 모양(로고/검색/메뉴 행)을 서버사이드 Blade로 재현.
     이 페이지는 Vue Router 밖(일반 네비게이션)이라 서버는 로그인 상태를 모르지만,
     로그인 토큰 자체는 (세션 쿠키가 아니라) localStorage/sessionStorage의
     sk_token/sk_user에 있으므로, 아래 작은 스크립트로 로드 직후 그것만 읽어서
     로그인 중이면 게스트 버튼 대신 아바타(마이페이지/로그아웃)로 바꿔치기한다. --}}
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
        <div id="info-auth-guest" class="flex items-center gap-1.5 flex-shrink-0">
            <a href="/login" class="text-[13px] font-semibold text-ink-light hover:text-ink hover:bg-surface px-3 py-1.5 rounded-full transition-colors">로그인</a>
            <a href="/register" class="text-[13px] text-white font-bold px-4 py-1.5 rounded-full transition-all shadow-btn" style="background-image:linear-gradient(135deg,#FF7A30,#FF4D12)">시작하기</a>
        </div>
        <div id="info-auth-user" class="relative flex-shrink-0" style="display:none">
            <button id="info-auth-avatar" type="button" class="relative w-8 h-8 rounded-full bg-amber-400 text-white flex items-center justify-center text-xs font-bold overflow-hidden ring-2 ring-amber-100"></button>
            <div id="info-auth-dropdown" class="absolute right-0 top-10 bg-white rounded-2xl shadow-lift py-2 w-44 z-50" style="display:none">
                <a href="/dashboard" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink-light hover:bg-amber-50/60 hover:text-ink transition-colors">마이페이지</a>
                <button id="info-auth-logout" type="button" class="w-full flex items-center gap-2.5 text-left px-4 py-2.5 text-sm text-ink-muted hover:bg-gray-50 transition-colors">로그아웃</button>
            </div>
        </div>
    </div>
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
</nav>
<script>
(function () {
    try {
        var raw = sessionStorage.getItem('sk_user') || localStorage.getItem('sk_user');
        var token = sessionStorage.getItem('sk_token') || localStorage.getItem('sk_token');
        if (!raw || !token) return;
        var user = JSON.parse(raw);

        document.getElementById('info-auth-guest').style.display = 'none';
        var userBox = document.getElementById('info-auth-user');
        userBox.style.display = 'block';

        var avatar = document.getElementById('info-auth-avatar');
        if (user.avatar) {
            var img = document.createElement('img');
            img.src = user.avatar;
            img.alt = '';
            img.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;object-fit:cover';
            avatar.appendChild(img);
        } else {
            avatar.textContent = (user.name || '?').charAt(0);
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
    } catch (e) {}
})();
</script>
