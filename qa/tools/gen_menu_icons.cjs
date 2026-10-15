// app/Support/MenuIcons.php 를 만든다 — 본 사이트 아이콘(AppIcon.vue, menuIcons.js)을 서버 화면(/info)에서도 쓰기 위한 사본.
// 사용: node qa/tools/gen_menu_icons.cjs   (아이콘을 바꾼 뒤 다시 돌릴 것)
const fs = require('fs')
const path = require('path')
const root = path.resolve(__dirname, '..', '..') + '/'
const app = fs.readFileSync(root + 'resources/js/components/AppIcon.vue', 'utf8')
const mi = fs.readFileSync(root + 'resources/js/utils/menuIcons.js', 'utf8')

const start = app.indexOf('const ICONS = {')
const block = app.slice(start + 14, app.indexOf('\n}\n', start))
const icons = {}
const re = /^\s*'([a-z0-9-]+)':\s*'((?:[^'\\]|\\.)*)'\s*,?\s*$/gm
let m
while ((m = re.exec(block))) icons[m[1]] = m[2].replace(/\\'/g, "'")

function obj(name) {
  const s = mi.slice(mi.indexOf('export const ' + name))
  const b = s.slice(s.indexOf('{') + 1, s.indexOf('}'))
  const o = {}
  const r = /(\w+):\s*'([^']*)'/g
  let x
  while ((x = r.exec(b))) o[x[1]] = x[2]
  return o
}
const MENU_ICONS = obj('MENU_ICONS')
const CHIP = obj('MENU_CHIP_COLORS')
const need = new Set(['grid', 'chevron-down', 'star', 'x', 'search', 'heart-handshake', 'mail', 'user', ...Object.values(MENU_ICONS)])
const out = {}
for (const n of need) {
  if (!icons[n]) console.error('missing icon', n)
  else out[n] = icons[n]
}
const json = JSON.stringify({ icons: out, menu: MENU_ICONS, chip: CHIP })
if (json.includes('\nJSON')) throw new Error('JSON 안에 nowdoc 종료 표시가 있음')

const php = `<?php

namespace App\\Support;

// 메뉴 아이콘 — 본 사이트(resources/js/components/AppIcon.vue 의 ICONS, resources/js/utils/menuIcons.js)와 같은 모양을
// 서버에서 그리는 /info 페이지의 햄버거 메뉴에도 쓰기 위한 사본. 직접 고치지 말고 \`node qa/tools/gen_menu_icons.cjs\` 로 다시 만들 것.
class MenuIcons
{
    private static ?array $data = null;

    private static function data(): array
    {
        return self::$data ??= json_decode(<<<'JSON'
${json}
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
`
fs.writeFileSync(root + 'app/Support/MenuIcons.php', php)
console.log('ok icons=' + Object.keys(out).length + ' menus=' + Object.keys(MENU_ICONS).length)
