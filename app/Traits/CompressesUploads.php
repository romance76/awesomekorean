<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

/**
 * 사용자 업로드 파일을 서버에 저장하기 전 자동 압축.
 *
 * 사용 예시:
 *   $path = $this->storeCompressedImage($request->file('logo'), 'job_logos', 1200, 80);
 *   // -> '/storage/job_logos/xxxxx.jpg'  (base64 prefix 포함된 URL)
 */
trait CompressesUploads
{
    /**
     * 이미지를 리사이즈 + 재인코딩해서 저장.
     *
     * @param  UploadedFile  $file
     * @param  string        $dir        저장 폴더 (public disk 기준)
     * @param  int           $maxWidth   가로 최대 px (세로는 비율 유지)
     * @param  int           $quality    JPEG/WEBP 품질 (1-100)
     * @return string        '/storage/…' 형태 URL
     */
    protected function storeCompressedImage(UploadedFile $file, string $dir, int $maxWidth = 1600, int $quality = 82): string
    {
        try {
            $manager = new ImageManager(new GdDriver());
            $img = $manager->read($file->getRealPath());

            // 가로가 maxWidth보다 크면 비율 유지하며 축소
            if ($img->width() > $maxWidth) {
                $img->scale(width: $maxWidth);
            }

            // EXIF 회전 보정 (가능한 경우)
            if (method_exists($img, 'orient')) {
                $img->orient();
            }

            // 확장자 결정: png/gif/webp 는 유지, 나머지 jpg 로 변환
            $origExt = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
            $ext = in_array($origExt, ['png', 'gif', 'webp']) ? $origExt : 'jpg';

            $filename = $dir . '/' . uniqid('img_', true) . '.' . $ext;

            $encoded = match ($ext) {
                'png'  => $img->toPng(),
                'gif'  => $img->toGif(),
                'webp' => $img->toWebp($quality),
                default => $img->toJpeg($quality),
            };

            Storage::disk('public')->put($filename, (string) $encoded);

            return '/storage/' . $filename;
        } catch (\Throwable $e) {
            // 압축 실패 시 원본 저장 (업로드 자체는 성공시킴)
            \Log::warning('Image compression failed, storing original', [
                'err' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);
            return '/storage/' . $this->storeSafeFile($file, $dir, ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif']);
        }
    }

    /**
     * 여러 이미지 일괄 압축 저장.
     *
     * @param  array<UploadedFile>  $files
     * @return array<string>        URL 배열
     */
    protected function storeCompressedImages(array $files, string $dir, int $maxWidth = 1600, int $quality = 82): array
    {
        $urls = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $urls[] = $this->storeCompressedImage($file, $dir, $maxWidth, $quality);
            }
        }
        return $urls;
    }

    /**
     * storeCompressedImage 와 동일하지만 '/storage/' prefix 를 붙이지 않고
     * disk 상의 상대 경로만 반환 (기존 스키마가 그렇게 저장 중이면 이쪽 사용).
     */
    protected function storeCompressedImageRaw(UploadedFile $file, string $dir, int $maxWidth = 1600, int $quality = 82): string
    {
        $url = $this->storeCompressedImage($file, $dir, $maxWidth, $quality);
        // '/storage/xxx' → 'xxx'
        return ltrim(preg_replace('#^/storage/#', '', $url), '/');
    }

    /**
     * PDF/일반 파일 저장 (용량 제한만 적용, 압축은 하지 않음).
     *
     * @return string '/storage/…' 형태 URL
     */
    protected function storeDocument(UploadedFile $file, string $dir): string
    {
        return '/storage/' . $this->storeSafeFile($file, $dir, self::SAFE_DOC_TYPES);
    }

    /** 문서/첨부로 받을 수 있는 파일 종류 (파일 내용으로 확인한 MIME → 저장할 확장자) */
    protected const SAFE_DOC_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/zip' => 'zip',
    ];

    /**
     * 업로드 파일을 안전하게 저장한다.
     *  - 파일 "내용"으로 판별한 종류가 허용 목록에 있어야 하고(이름/확장자는 믿지 않음)
     *  - 저장 이름은 무작위 + 허용 목록의 확장자로 우리가 정한다 (.php/.html 등이 그대로 저장돼 실행/스크립트로 열리는 것 방지)
     * @return string public 디스크 안의 상대 경로
     */
    protected function storeSafeFile(UploadedFile $file, string $dir, array $allowed): string
    {
        $mime = (string) $file->getMimeType();
        $ext = $allowed[$mime] ?? null;
        // docx/xlsx 는 내용상 zip 으로 잡히는 경우가 많다 → 원래 이름의 확장자가 허용된 오피스 확장자면 그것을 쓴다
        if ($mime === 'application/zip' && isset($allowed['application/zip'])) {
            $orig = strtolower($file->getClientOriginalExtension());
            $ext = in_array($orig, ['docx', 'xlsx'], true) && in_array($orig, $allowed, true) ? $orig : 'zip';
        }
        if (!$ext) {
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => '허용되지 않는 파일 형식이에요. (PDF, 이미지, 문서, 압축 파일만 가능)']);
        }
        $name = \Illuminate\Support\Str::random(40) . '.' . $ext;
        return $file->storeAs($dir, $name, 'public');
    }

    /**
     * content HTML 안의 base64 <img> 를 파일로 추출 + 압축 저장.
     *
     * 리치에디터 (contenteditable) 에 삽입된 이미지가 base64 data URI 로
     * DB 에 바로 저장되면 TEXT/LONGTEXT 컬럼을 부풀리고 페이지 로딩도 느려짐.
     * 이 메서드로 파일화하면 DB 는 짧은 URL 만 갖게 됨.
     */
    protected function extractAndCompressBase64Images(string $html, string $dir, int $maxWidth = 1200, int $quality = 80): string
    {
        return preg_replace_callback(
            '/<img[^>]+src=["\'](data:image\/(png|jpe?g|gif|webp);base64,([^"\']+))["\']([^>]*)>/i',
            function ($m) use ($dir, $maxWidth, $quality) {
                try {
                    $bin = base64_decode($m[3]);
                    if ($bin === false || strlen($bin) > 10 * 1024 * 1024) return '';

                    $manager = new ImageManager(new GdDriver());
                    $img = $manager->read($bin);
                    if ($img->width() > $maxWidth) {
                        $img->scale(width: $maxWidth);
                    }
                    $ext = strtolower($m[2]) === 'png' ? 'png' : (strtolower($m[2]) === 'webp' ? 'webp' : 'jpg');
                    $encoded = match ($ext) {
                        'png'  => $img->toPng(),
                        'webp' => $img->toWebp($quality),
                        default => $img->toJpeg($quality),
                    };
                    $name = $dir . '/' . uniqid('inline_', true) . '.' . $ext;
                    Storage::disk('public')->put($name, (string) $encoded);

                    $rest = $m[4] ?? '';
                    // 혹시 남아있는 width/height/style 은 보존
                    return '<img src="/storage/' . $name . '"' . $rest . '>';
                } catch (\Throwable $e) {
                    return ''; // 실패 시 이미지 제거 (깨진 base64 보다 나음)
                }
            },
            $html
        );
    }
}
