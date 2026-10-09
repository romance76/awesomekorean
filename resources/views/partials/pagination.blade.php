{{-- 사이트 공통 페이지네이션 (Vue Pagination.vue 와 같은 모양: « ‹ 1 2 3 › », 현재 페이지 강조) --}}
@if ($paginator->lastPage() > 1)
@php
    $cur = $paginator->currentPage();
    $total = $paginator->lastPage();
    $maxShow = 7;
    $start = max(1, $cur - intdiv($maxShow, 2));
    $end = min($total, $start + $maxShow - 1);
    if ($end - $start + 1 < $maxShow) $start = max(1, $end - $maxShow + 1);
    $base = 'inline-flex items-center justify-center transition-colors';
    $box = 'min-width:32px;height:32px;border-radius:8px;font-size:13px;';
@endphp
<nav class="flex justify-center items-center gap-1 mt-4 flex-wrap" aria-label="페이지 이동">
    @foreach ([['«', 1, $cur <= 1], ['‹', $cur - 1, $cur <= 1]] as [$label, $to, $off])
        @if ($off)
            <span class="{{ $base }} text-gray-300" style="{{ $box }}" aria-disabled="true">{{ $label }}</span>
        @else
            <a href="{{ $paginator->url($to) }}" class="{{ $base }} text-ink-muted hover:bg-gray-100" style="{{ $box }}">{{ $label }}</a>
        @endif
    @endforeach

    @for ($i = $start; $i <= $end; $i++)
        @if ($i === $cur)
            <span class="{{ $base }} bg-amber-400 text-white font-bold" style="{{ $box }}" aria-current="page">{{ $i }}</span>
        @else
            <a href="{{ $paginator->url($i) }}" class="{{ $base }} text-ink-muted hover:bg-gray-100" style="{{ $box }}">{{ $i }}</a>
        @endif
    @endfor

    @foreach ([['›', $cur + 1, $cur >= $total], ['»', $total, $cur >= $total]] as [$label, $to, $off])
        @if ($off)
            <span class="{{ $base }} text-gray-300" style="{{ $box }}" aria-disabled="true">{{ $label }}</span>
        @else
            <a href="{{ $paginator->url($to) }}" class="{{ $base }} text-ink-muted hover:bg-gray-100" style="{{ $box }}">{{ $label }}</a>
        @endif
    @endforeach
</nav>
@endif
