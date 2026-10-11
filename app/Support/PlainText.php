<?php

namespace App\Support;

/**
 * 글·댓글·쪽지처럼 "그냥 글자"만 받는 곳에서 HTML 태그를 저장하지 않도록 지운다 (할 일: HTML 저장 방지).
 * 화면 출력은 이미 이스케이프돼 안전하지만, 나중에 출력하는 곳이 늘어나도 위험하지 않게 저장 단계에서 한 번 더 막는다.
 *
 * strip_tags() 는 쓰지 않는다: "I <3 you" 처럼 닫는 > 가 없는 < 를 만나면 뒤쪽 글을 통째로 잘라 버리기 때문이다.
 * 대신 "글자로 시작하는 태그 모양"(<script>, </b>, <img src=…>)만 정확히 지우고, 부등호·하트(<3) 같은 일반 글자는 그대로 둔다.
 */
class PlainText
{
    public static function clean(?string $text): ?string
    {
        if ($text === null || $text === '' || strpos($text, '<') === false) return $text;

        // 실행/삽입되는 덩어리는 안쪽 내용까지 통째로
        $text = preg_replace('#<(script|style|iframe|object|embed|noscript)\b[^>]*>.*?</\1\s*>#is', '', $text) ?? $text;
        // 남은 태그 모양(여는 태그·닫는 태그·주석) 제거
        // (실제 HTML 태그 이름만 — "x<y and y>z" 같은 수식·부등호 글은 건드리지 않는다)
        $tags = 'a|abbr|article|aside|audio|b|base|big|body|br|button|canvas|center|code|details|div|em|embed|font|footer|form|frame|frameset|h[1-6]|head|header|hr|html|i|iframe|img|input|label|li|link|main|marquee|math|meta|nav|object|ol|option|p|picture|pre|s|script|section|select|small|source|span|strike|strong|style|sub|sup|svg|table|tbody|td|textarea|th|thead|tr|u|ul|video';
        $text = preg_replace('#</?(?:' . $tags . ')\b[^>]*>#i', '', $text) ?? $text;
        $text = preg_replace('#<!--.*?-->#s', '', $text) ?? $text;
        // 짝 없는 <script 등 시작 태그가 닫히지 않은 경우 시작 부분만이라도 제거
        $text = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*$#i', '', $text) ?? $text;

        return $text;
    }
}
