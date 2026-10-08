{{-- 정보 페이지 공통 푸터: 소개/문의/방침 링크 + 참고용 정보 고지.
     (정보 글은 법률·세금·이민·금융 주제가 많아 면책 문구를 모든 글/목록 하단에 둔다.) --}}
<footer class="bg-slate-800 mt-8">
    <div class="max-w-7xl mx-auto px-4 py-6 text-center">
        <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-xs text-gray-300 mb-3">
            <a href="/about" class="hover:text-amber-400">소개</a>
            <a href="/contact" class="hover:text-amber-400">문의하기</a>
            <a href="/privacy" class="hover:text-amber-400">개인정보처리방침</a>
            <a href="/terms" class="hover:text-amber-400">이용약관</a>
            <a href="/info" class="hover:text-amber-400">정보 전체</a>
        </nav>
        <p class="text-[11px] leading-relaxed text-gray-400 max-w-3xl mx-auto">
            이 사이트의 정보 글은 미국 한인 생활에 도움이 되도록 정리한 일반적인 참고 자료이며, 법률·세금·이민·금융·의료 등 전문가의 상담이나 공식 기관의 안내를 대체하지 않습니다.
            제도와 금액은 수시로 바뀔 수 있으니 중요한 결정 전에는 반드시 해당 기관의 최신 공지를 확인하세요. 잘못된 내용을 발견하시면 <a href="/contact" class="underline hover:text-amber-400">문의하기</a>로 알려 주세요.
        </p>
        <p class="text-[11px] text-gray-500 mt-3">© {{ date('Y') }} AwesomeKorean. All rights reserved.</p>
    </div>
</footer>
