<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 외부/내부 이미지를 다운로드 → 지정된 크기로 리사이즈 → 로컬 캐시에 저장 후 서빙.
 *
 * 사용: <img src="/api/thumb?url=https://...&w=240" />
 * - 첫 요청: 원본 다운로드 + GD 로 리사이즈 + storage/app/public/thumbs/{hash}_{w}.jpg 에 저장
 * - 이후 요청: 파일 존재하면 즉시 response()->file() 로 서빙 (Nginx sendfile)
 * - 브라우저/프록시 캐시: 1년 immutable
 */
class ThumbnailController extends Controller
{
    // 허용된 source host (SSRF 방지)
    private array $allowedHosts = [
        'foodsafetykorea.go.kr',
        'www.foodsafetykorea.go.kr',
        'i.ytimg.com',
        'img.youtube.com',
        'yt3.ggpht.com',
        'lh3.googleusercontent.com',
        'lh4.googleusercontent.com',
        'lh5.googleusercontent.com',
        'lh6.googleusercontent.com',
        'maps.googleapis.com',
        'maps.gstatic.com',
        // 한국 뉴스 이미지 호스트
        'www.chosun.com', 'chosun.com', 'img.chosun.com',
        'flexible.img.hani.co.kr', 'img.hani.co.kr', 'www.hani.co.kr',
        'dimg.donga.com', 'image.donga.com', 'img.donga.com',
        'img.sbs.co.kr', 'mimgnews.pstatic.net', 'imgnews.pstatic.net',
        'news.kbs.co.kr', 'img.kbs.co.kr',
        'somekorean.com',
        'www.somekorean.com',
    ];

    public function show(Request $request)
    {
        $url = (string) $request->query('url', '');
        $width = max(32, min(1200, (int) $request->query('w', 240)));

        if ($url === '') abort(400, 'url required');

        // 내부 경로면 직접 리사이즈
        if (str_starts_with($url, '/storage/')) {
            $sourcePath = public_path($url);
            if (!file_exists($sourcePath)) abort(404);
            return $this->resizeFromFile($sourcePath, $width, $url);
        }

        // 외부 URL: SSRF 방지 (허용 호스트만)
        $parsed = parse_url($url);
        $host = strtolower($parsed['host'] ?? '');
        if (!in_array($host, $this->allowedHosts)) {
            // 허용 안 된 외부는 원본으로 302 리다이렉트 (캐시 안 됨)
            return redirect($url);
        }

        return $this->resizeFromUrl($url, $width);
    }

    private function resizeFromUrl(string $url, int $width): BinaryFileResponse
    {
        $cachePath = $this->cachePath($url, $width);

        if (!file_exists($cachePath)) {
            $body = $this->fetchUrl($url);
            if ($body === null || strlen($body) < 100) {
                \Log::warning('thumb fetch failed', ['url' => $url, 'size' => $body ? strlen($body) : 0]);
                abort(404, 'source fetch failed');
            }
            try {
                $this->writeResized($body, $cachePath, $width);
            } catch (\Exception $e) {
                \Log::warning('thumb resize failed', ['url' => $url, 'err' => $e->getMessage()]);
                abort(404, 'thumb generation failed: ' . $e->getMessage());
            }
        }

        return $this->serveFile($cachePath);
    }

    // 순수 curl 사용 (Guzzle 은 일부 환경에서 connection reset 발생)
    private function fetchUrl(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SomeKorean-Thumb/1.0)',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 400) {
            \Log::warning('thumb curl error', ['url' => $url, 'code' => $code, 'err' => $err]);
            return null;
        }
        return $body;
    }

    private function resizeFromFile(string $sourcePath, int $width, string $cacheKey): BinaryFileResponse
    {
        $cachePath = $this->cachePath($cacheKey, $width);

        if (!file_exists($cachePath) || filemtime($cachePath) < filemtime($sourcePath)) {
            $this->writeResized(file_get_contents($sourcePath), $cachePath, $width);
        }

        return $this->serveFile($cachePath);
    }

    private function cachePath(string $key, int $width): string
    {
        $hash = md5($key);
        $dir = storage_path('app/public/thumbs/' . substr($hash, 0, 2));
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir . '/' . substr($hash, 2) . '_' . $width . '.jpg';
    }

    private function writeResized(string $binary, string $cachePath, int $width): void
    {
        $img = @imagecreatefromstring($binary);
        if (!$img) throw new \Exception('invalid image');

        $srcW = imagesx($img);
        $srcH = imagesy($img);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($img);
            throw new \Exception('invalid dimensions');
        }

        // 원본보다 크면 그대로 저장
        $newW = min($width, $srcW);
        $newH = (int) round($srcH * ($newW / $srcW));

        $dst = imagecreatetruecolor($newW, $newH);
        // 투명 배경 흰색으로
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $white);

        imagecopyresampled($dst, $img, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        imagejpeg($dst, $cachePath, 82);

        imagedestroy($img);
        imagedestroy($dst);
    }

    private function serveFile(string $path): BinaryFileResponse
    {
        return response()
            ->file($path, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'X-Thumb-Cache' => 'HIT',
            ]);
    }
}
