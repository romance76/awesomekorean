{{-- 정보 페이지 제목 [묶음 이름 + 하위 탭] — Vue 의 GroupTitle.vue 와 같은 모양(app.css 의 .grp-* 클래스 공용).
     $stack = true 이면 휴대폰처럼 제목 아래 줄에 탭. 묶음이 꺼져 있으면 원래 '📘 정보' 제목. --}}
@php
    $infoGrp = collect(\App\Support\MenuGroups::publicGroups($menus ?? []) ?? [])
        ->first(fn ($g) => $g['type'] === 'group' && count($g['items']) > 1 && collect($g['items'])->contains('key', 'info'));
@endphp
@if ($infoGrp)
    <div class="flex min-w-0 {{ !empty($stack) ? 'flex-col items-start gap-2' : 'items-center gap-3' }}">
        <a href="{{ $infoGrp['items'][0]['path'] }}" class="grp-title"><span class="grp-icon">{{ $infoGrp['icon'] ?: '📁' }}</span>{{ $infoGrp['label'] }}</a>
        <div class="grp-tabs" role="tablist" aria-label="{{ $infoGrp['label'] }} 메뉴">
            @foreach ($infoGrp['items'] as $m)
                <a href="{{ $m['path'] }}" role="tab" aria-selected="{{ $m['key'] === 'info' ? 'true' : 'false' }}" class="grp-chip {{ $m['key'] === 'info' ? 'grp-chip-on' : 'grp-chip-off' }}">{{ $m['label'] }}</a>
            @endforeach
        </div>
    </div>
@else
    <a href="{{ route('info.index') }}" class="grp-title"><span class="grp-icon !bg-sky-50">📘</span>정보</a>
@endif
