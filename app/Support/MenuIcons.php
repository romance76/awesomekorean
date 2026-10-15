<?php

namespace App\Support;

// 메뉴 아이콘 — 본 사이트(resources/js/components/AppIcon.vue 의 ICONS, resources/js/utils/menuIcons.js)와 같은 모양을
// 서버에서 그리는 /info 페이지의 햄버거 메뉴에도 쓰기 위한 사본. 직접 고치지 말고 `node qa/tools/gen_menu_icons.cjs` 로 다시 만들 것.
class MenuIcons
{
    private static ?array $data = null;

    private static function data(): array
    {
        return self::$data ??= json_decode(<<<'JSON'
{"icons":{"grid":"<rect x=\"3\" y=\"3\" width=\"7\" height=\"7\" rx=\"1\"/><rect x=\"14\" y=\"3\" width=\"7\" height=\"7\" rx=\"1\"/><rect x=\"14\" y=\"14\" width=\"7\" height=\"7\" rx=\"1\"/><rect x=\"3\" y=\"14\" width=\"7\" height=\"7\" rx=\"1\"/>","chevron-down":"<polyline points=\"6 9 12 15 18 9\"/>","star":"<polygon points=\"12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2\"/>","x":"<line x1=\"18\" y1=\"6\" x2=\"6\" y2=\"18\"/><line x1=\"6\" y1=\"6\" x2=\"18\" y2=\"18\"/>","search":"<circle cx=\"11\" cy=\"11\" r=\"8\"/><line x1=\"21\" y1=\"21\" x2=\"16.65\" y2=\"16.65\"/>","heart-handshake":"<path d=\"M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z\"/><path d=\"M12 5.67 8.5 9.17a1.77 1.77 0 0 0 2.5 2.5l2.5-2.5\"/>","mail":"<path d=\"M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z\"/><polyline points=\"22,6 12,13 2,6\"/>","user":"<path d=\"M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2\"/><circle cx=\"12\" cy=\"7\" r=\"4\"/>","home":"<path d=\"m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z\"/><polyline points=\"9 22 9 12 15 12 15 22\"/>","message-circle":"<path d=\"M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z\"/>","help-circle":"<circle cx=\"12\" cy=\"12\" r=\"10\"/><path d=\"M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3\"/><line x1=\"12\" y1=\"17\" x2=\"12.01\" y2=\"17\"/>","briefcase":"<rect x=\"2\" y=\"7\" width=\"20\" height=\"14\" rx=\"2\"/><path d=\"M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16\"/>","shopping-cart":"<circle cx=\"9\" cy=\"21\" r=\"1\"/><circle cx=\"20\" cy=\"21\" r=\"1\"/><path d=\"M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6\"/>","store":"<path d=\"M3 9 4.7 3.6A1 1 0 0 1 5.7 3h12.6a1 1 0 0 1 1 .6L21 9\"/><path d=\"M3 9h18v2a3 3 0 0 1-3 3 3 3 0 0 1-3-3 3 3 0 0 1-3 3 3 3 0 0 1-3-3 3 3 0 0 1-3 3 3 3 0 0 1-3-3z\" fill=\"none\"/><path d=\"M5 14v6a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-6\"/><path d=\"M9 21v-5h6v5\"/>","building":"<rect x=\"4\" y=\"2\" width=\"16\" height=\"20\" rx=\"2\"/><line x1=\"9\" y1=\"22\" x2=\"9\" y2=\"18\"/><line x1=\"15\" y1=\"22\" x2=\"15\" y2=\"18\"/><line x1=\"8\" y1=\"6\" x2=\"10\" y2=\"6\"/><line x1=\"14\" y1=\"6\" x2=\"16\" y2=\"6\"/><line x1=\"8\" y1=\"10\" x2=\"10\" y2=\"10\"/><line x1=\"14\" y1=\"10\" x2=\"16\" y2=\"10\"/><line x1=\"8\" y1=\"14\" x2=\"10\" y2=\"14\"/><line x1=\"14\" y1=\"14\" x2=\"16\" y2=\"14\"/>","calendar":"<rect x=\"3\" y=\"4\" width=\"18\" height=\"18\" rx=\"2\"/><line x1=\"16\" y1=\"2\" x2=\"16\" y2=\"6\"/><line x1=\"8\" y1=\"2\" x2=\"8\" y2=\"6\"/><line x1=\"3\" y1=\"10\" x2=\"21\" y2=\"10\"/>","newspaper":"<path d=\"M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2\"/><path d=\"M18 14h-8\"/><path d=\"M15 18h-5\"/><path d=\"M10 6h8v4h-8z\"/>","book-open":"<path d=\"M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z\"/><path d=\"M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z\"/>","utensils":"<path d=\"M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2\"/><path d=\"M7 2v20\"/><path d=\"M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7\"/>","users":"<path d=\"M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2\"/><circle cx=\"9\" cy=\"7\" r=\"4\"/><path d=\"M23 21v-2a4 4 0 0 0-3-3.87\"/><path d=\"M16 3.13a4 4 0 0 1 0 7.75\"/>","gamepad":"<line x1=\"6\" y1=\"12\" x2=\"10\" y2=\"12\"/><line x1=\"8\" y1=\"10\" x2=\"8\" y2=\"14\"/><line x1=\"15\" y1=\"13\" x2=\"15.01\" y2=\"13\"/><line x1=\"18\" y1=\"11\" x2=\"18.01\" y2=\"11\"/><rect x=\"2\" y=\"6\" width=\"20\" height=\"12\" rx=\"2\"/>","video":"<path d=\"m22 8-6 4 6 4V8Z\"/><rect x=\"2\" y=\"6\" width=\"14\" height=\"12\" rx=\"2\"/>","music":"<path d=\"M9 18V5l12-2v13\"/><circle cx=\"6\" cy=\"18\" r=\"3\"/><circle cx=\"18\" cy=\"16\" r=\"3\"/>","shopping-bag":"<path d=\"M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z\"/><line x1=\"3\" y1=\"6\" x2=\"21\" y2=\"6\"/><path d=\"M16 10a4 4 0 0 1-8 0\"/>","message-square":"<path d=\"M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z\"/>","heart":"<path d=\"M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z\"/>","phone":"<path d=\"M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z\"/>","coins":"<circle cx=\"8\" cy=\"8\" r=\"6\"/><path d=\"M18.09 10.37A6 6 0 1 1 10.34 18\"/><path d=\"M7 6h1v4\"/><path d=\"m16.71 13.88.7.71-2.82 2.82\"/>"},"menu":{"home":"home","community":"message-circle","qa":"help-circle","jobs":"briefcase","market":"shopping-cart","directory":"store","realestate":"building","events":"calendar","news":"newspaper","info":"book-open","recipes":"utensils","clubs":"users","games":"gamepad","shorts":"video","music":"music","groupbuy":"shopping-bag","chat":"message-square","friends":"heart-handshake","elder":"heart","comms":"phone","shopping":"shopping-bag","points":"coins","money":"coins","dashboard":"user"},"chip":{"home":"bg-amber-50 text-amber-600","community":"bg-blue-50 text-blue-600","qa":"bg-amber-50 text-amber-600","jobs":"bg-emerald-50 text-emerald-600","market":"bg-amber-50 text-amber-600","directory":"bg-amber-50 text-amber-600","realestate":"bg-violet-50 text-violet-600","events":"bg-amber-50 text-amber-600","news":"bg-sky-50 text-sky-600","info":"bg-sky-50 text-sky-600","recipes":"bg-amber-50 text-amber-600","clubs":"bg-teal-50 text-teal-600","games":"bg-indigo-50 text-indigo-600","shorts":"bg-fuchsia-50 text-fuchsia-600","music":"bg-purple-50 text-purple-600","groupbuy":"bg-lime-50 text-lime-600","chat":"bg-cyan-50 text-cyan-600","friends":"bg-amber-50 text-amber-600","elder":"bg-red-50 text-red-500","comms":"bg-emerald-50 text-emerald-600","shopping":"bg-lime-50 text-lime-600","points":"bg-amber-50 text-amber-600","money":"bg-yellow-50 text-yellow-600","dashboard":"bg-gray-100 text-ink-light"}}
JSON, true);
    }

    /** 메뉴 key → 아이콘 이름 */
    public static function name(string $key): string
    {
        return self::data()['menu'][$key] ?? 'grid';
    }

    /** 메뉴 key → 파스텔 칩 색(Tailwind 클래스) */
    public static function chip(string $key): string
    {
        return self::data()['chip'][$key] ?? 'bg-amber-50 text-amber-600';
    }

    /** 라인 아이콘 SVG (AppIcon.vue 와 같은 속성) */
    public static function svg(string $icon, int $size = 20, bool $filled = false): string
    {
        $markup = self::data()['icons'][$icon] ?? self::data()['icons']['grid'];
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="' . ($filled ? 'currentColor' : 'none') . '" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0">' . $markup . '</svg>';
    }

    /** 메뉴 key 로 바로 그리기 */
    public static function forKey(string $key, int $size = 18): string
    {
        return self::svg(self::name($key), $size);
    }
}
