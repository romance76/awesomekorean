<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * SNS 카드뉴스 이미지(1080×1350 JPEG) — 인스타그램은 사진이 꼭 있어야 해서 글마다 만든다.
 * 모양: 위쪽 색 띠(카페 색) + 작은 꼬리표 + 큰 제목 + 핵심 3줄 + 아래 awesomekorean.com.
 * 글꼴: resources/fonts/NanumGothic-*.ttf (OFL). 이모지는 글꼴에 없어 빼고 그린다.
 * 저장: storage/app/public/social/YYYYMM/<이름>.jpg → https://awesomekorean.com/storage/social/... (인스타가 이 주소에서 가져감)
 */
class SocialCard
{
    public const W = 1080;
    public const H = 1350;

    // moneyThemes.js 와 같은 색 (accent, from, ink)
    public const THEMES = [
        'emerald' => ['#008968', '#e6f7ef', '#0b5c45'], 'orange' => ['#e8641b', '#fff1e6', '#9a3f0c'],
        'teal' => ['#0f8b8d', '#e5f6f6', '#0b5d5f'], 'sky' => ['#1f7fd1', '#e7f3fd', '#14558f'],
        'indigo' => ['#4c55d6', '#ecedfc', '#2f3596'], 'slate' => ['#46617f', '#edf1f5', '#2c3e55'],
        'violet' => ['#7c3aed', '#f3ebfd', '#5420ad'], 'blue' => ['#2563eb', '#e8f0fe', '#1d4aa8'],
        'amber' => ['#c98a00', '#fff7e0', '#7a5300'], 'rose' => ['#e05a3a', '#fff0ea', '#93361f'],
        'yellow' => ['#b88400', '#fff8db', '#6e4f00'], 'pink' => ['#e0145a', '#fff0f5', '#a00d40'],
    ];

    /**
     * @param array $c ['tag' => '작은 꼬리표', 'title' => '제목', 'lines' => ['핵심1','핵심2','핵심3'], 'theme' => 'emerald', 'footer' => '아래 안내']
     * @return string 공개 주소(https)
     */
    public static function make(array $c, string $name): string
    {
        $bold = resource_path('fonts/NanumGothic-Bold.ttf');
        $xbold = resource_path('fonts/NanumGothic-ExtraBold.ttf');
        [$accentHex, $softHex, $inkHex] = self::THEMES[$c['theme'] ?? 'emerald'] ?? self::THEMES['emerald'];

        $im = imagecreatetruecolor(self::W, self::H);
        $col = fn (string $hex) => imagecolorallocate($im, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
        $white = $col('#ffffff'); $accent = $col($accentHex); $soft = $col($softHex); $ink = $col($inkHex);
        $dark = $col('#1f1a17'); $muted = $col('#6b625b');

        // 배경: 연한 카페 색 → 흰색 (세로 그라데이션)
        [$r1, $g1, $b1] = sscanf($softHex, '#%02x%02x%02x');
        for ($y = 0; $y < self::H; $y++) {
            $t = min(1, $y / (self::H * 0.75));
            $line = imagecolorallocate($im, (int) ($r1 + (255 - $r1) * $t), (int) ($g1 + (255 - $g1) * $t), (int) ($b1 + (255 - $b1) * $t));
            imageline($im, 0, $y, self::W, $y, $line);
        }
        // 위쪽 색 띠
        imagefilledrectangle($im, 0, 0, self::W, 18, $accent);

        $x = 90; $maxW = self::W - 180; $y = 150;

        // 꼬리표 (둥근 알약 모양 대신 색 사각형 + 흰 글자)
        $tag = self::clean($c['tag'] ?? 'AwesomeKorean');
        if ($tag !== '') {
            $bb = imagettfbbox(30, 0, $bold, $tag);
            $tw = $bb[2] - $bb[0];
            self::roundRect($im, $x, $y - 46, $x + $tw + 48, $y + 18, 32, $accent);
            imagettftext($im, 30, 0, $x + 24, $y, $white, $bold, $tag);
            $y += 120;
        }

        // 제목 (최대 4줄, 글자 크기 자동)
        $title = self::clean($c['title'] ?? '');
        $size = mb_strlen($title) > 46 ? 58 : (mb_strlen($title) > 28 ? 66 : 76);
        $lines = self::wrap($title, $xbold, $size, $maxW, 4);
        foreach ($lines as $ln) {
            imagettftext($im, $size, 0, $x, $y, $dark, $xbold, $ln);
            $y += (int) ($size * 1.45);
        }

        // 구분선
        $y += 30;
        imagefilledrectangle($im, $x, $y, $x + 120, $y + 8, $accent);
        $y += 90;

        // 핵심 줄 (최대 3개, 각 2줄까지)
        foreach (array_slice($c['lines'] ?? [], 0, 3) as $p) {
            $p = self::clean($p);
            if ($p === '') continue;
            $sub = self::wrap($p, $bold, 36, $maxW - 70, 2);
            imagefilledellipse($im, $x + 18, $y - 13, 22, 22, $accent);
            foreach ($sub as $ln) {
                imagettftext($im, 36, 0, $x + 60, $y, $ink, $bold, $ln);
                $y += 56;
            }
            $y += 30;
            if ($y > self::H - 260) break;
        }

        // 아래: 사이트 주소 + 안내
        imagefilledrectangle($im, 0, self::H - 170, self::W, self::H, $accent);
        imagettftext($im, 44, 0, $x, self::H - 92, $white, $xbold, 'awesomekorean.com');
        $foot = self::clean($c['footer'] ?? '자세한 단계별 안내는 프로필 링크에서');
        imagettftext($im, 28, 0, $x, self::H - 42, $white, $bold, $foot);

        $rel = 'social/' . now('America/New_York')->format('Ym') . '/' . preg_replace('/[^A-Za-z0-9_\-]/', '', $name) . '.jpg';
        ob_start();
        imagejpeg($im, null, 88);
        $bin = ob_get_clean();
        imagedestroy($im);
        Storage::disk('public')->put($rel, $bin);

        return rtrim(config('app.url'), '/') . '/storage/' . $rel . '?v=' . substr(md5($bin), 0, 8);
    }

    /** 이모지·글꼴에 없는 기호를 빼고, 공백 정리 */
    public static function clean(string $s): string
    {
        $s = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}\x{2B00}-\x{2BFF}]/u', '', $s);
        $s = str_replace(['**', "\r"], '', $s);
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /** 폭에 맞게 줄바꿈 (띄어쓰기 우선, 너무 긴 단어는 글자 단위). 넘치면 마지막 줄 … */
    public static function wrap(string $text, string $font, int $size, int $maxW, int $maxLines): array
    {
        $width = function (string $s) use ($font, $size) { $b = imagettfbbox($size, 0, $font, $s); return $b[2] - $b[0]; };
        $lines = []; $cur = '';
        foreach (preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $try = $cur === '' ? $word : $cur . ' ' . $word;
            if ($width($try) <= $maxW) { $cur = $try; continue; }
            if ($cur !== '') { $lines[] = $cur; $cur = ''; }
            // 단어 하나가 너무 길면 글자 단위로 자름
            foreach (mb_str_split($word) as $ch) {
                if ($width($cur . $ch) > $maxW) { $lines[] = $cur; $cur = ''; }
                $cur .= $ch;
            }
        }
        if ($cur !== '') $lines[] = $cur;
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $last = $lines[$maxLines - 1];
            while ($last !== '' && $width($last . '…') > $maxW) $last = mb_substr($last, 0, -1);
            $lines[$maxLines - 1] = rtrim($last) . '…';
        }
        return $lines;
    }

    private static function roundRect($im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $color);
        }
    }
}
