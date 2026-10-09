<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

/**
 * 관리자 "사이트 설정"에서 업로드한 앱 아이콘/파비콘을 한 곳에서 해석한다.
 * 바탕화면·홈 화면 바로가기, 브라우저 탭, PWA 설치가 전부 같은 아이콘을 쓰도록
 * <head> 링크(partials/head-icons), /manifest.json, /favicon.ico 가 이 클래스를 공유.
 *
 * 업로드된 파일이 없으면 저장소에 커밋된 기본 아이콘(public/icons)으로 폴백.
 */
class BrandIcons
{
    public const SIZES = [72, 96, 128, 192, 512];

    /** SiteSetting 값에서 캐시 버스터(?v=…)만 추출 — 교체 직후 브라우저/기기가 새 아이콘을 받게 함 */
    private static function versionOf(string $key): string
    {
        $v = SiteSetting::where('key', $key)->value('value');
        return $v && str_contains($v, '?v=') ? substr($v, strpos($v, '?v=')) : '';
    }

    private static function uploaded(string $file): bool
    {
        return Storage::disk('public')->exists("branding/{$file}");
    }

    /** 정사각형 앱 아이콘 URL (업로드본 → 기본 아이콘) */
    public static function icon(int $size): string
    {
        $file = "icon-{$size}x{$size}.png";
        return self::uploaded($file)
            ? "/storage/branding/{$file}" . self::versionOf('app_icon_url')
            : "/icons/{$file}";
    }

    public static function appleTouch(): string
    {
        return self::uploaded('apple-touch-icon.png')
            ? '/storage/branding/apple-touch-icon.png' . self::versionOf('app_icon_url')
            : '/icons/icon-192x192.png';
    }

    /** 탭/바로가기용 작은 아이콘: 업로드한 파비콘 → 없으면 앱 아이콘(96px) */
    public static function favicon(): string
    {
        return self::uploaded('favicon-32x32.png')
            ? '/storage/branding/favicon-32x32.png' . self::versionOf('favicon_url')
            : self::icon(96);
    }

    /** /favicon.ico 요청에 내려줄 실제 파일 경로 */
    public static function faviconFile(): string
    {
        foreach (['branding/favicon-32x32.png', 'branding/icon-96x96.png'] as $rel) {
            if (Storage::disk('public')->exists($rel)) return Storage::disk('public')->path($rel);
        }
        return public_path('icons/icon-96x96.png');
    }

    /** PWA 매니페스트 — 아이콘 경로에 버전을 붙여 교체가 바로 반영되게 함 */
    public static function manifest(): array
    {
        return [
            'name' => 'AwesomeKorean',
            'short_name' => '어썸코리안',
            'description' => '미국 한인 커뮤니티 통합 플랫폼',
            'id' => '/',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            // Chrome 이 "이 사이트의 앱이 이미 설치됐는지" 를 알려주도록 자기 자신을 등록 (설치 안내를 숨기는 데 사용)
            'related_applications' => [['platform' => 'webapp', 'url' => 'https://awesomekorean.com/manifest.json']],
            'prefer_related_applications' => false,
            // 설치된 앱이 있으면 링크를 앱에서 열도록 요청 (지원하는 최신 Chrome 에서만 동작)
            'handle_links' => 'preferred',
            'launch_handler' => ['client_mode' => ['navigate-existing', 'auto']],
            'background_color' => '#ffffff',
            'theme_color' => '#FFFFFF',
            'orientation' => 'portrait-primary',
            // 업로드한 정사각형 이미지를 그대로(잘림 없이) 쓰도록 purpose 는 any
            'icons' => array_map(fn($s) => [
                'src' => self::icon($s), 'sizes' => "{$s}x{$s}", 'type' => 'image/png', 'purpose' => 'any',
            ], self::SIZES),
            'screenshots' => [],
            'categories' => ['social', 'lifestyle', 'communication'],
            'lang' => 'ko',
            'dir' => 'ltr',
        ];
    }
}
