<?php

namespace App\Console\Commands;

use App\Models\News;
use Illuminate\Console\Command;

class DownloadNewsImages extends Command
{
    protected $signature = 'news:download-images {--limit=500}';
    protected $description = '기존 뉴스의 외부 이미지를 로컬에 다운로드';

    public function handle()
    {
        $limit = (int) $this->option('limit');

        $news = News::whereNotNull('image_url')
            ->whereNull('local_image')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $this->info("다운로드 대상: {$news->count()}개");
        $success = 0;
        $failed = 0;

        foreach ($news as $n) {
            $localPath = $this->downloadImage($n->image_url, $n->published_at ?? $n->created_at);
            if ($localPath) {
                $n->update(['local_image' => $localPath]);
                $success++;
            } else {
                $failed++;
            }

            if (($success + $failed) % 50 === 0) {
                $this->line("  진행: {$success} 성공 / {$failed} 실패");
            }
        }

        $this->info("완료: {$success} 성공 / {$failed} 실패");
    }

    private function downloadImage(string $url, $date): ?string
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SomeKorean/1.0)',
            ]);
            $data = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if ($code !== 200 || !$data || strlen($data) < 500) return null;
            if (!str_contains($type ?? '', 'image')) return null;

            $ext = 'jpg';
            if (str_contains($type, 'png')) $ext = 'png';
            elseif (str_contains($type, 'webp')) $ext = 'webp';

            $month = $date ? date('Y-m', strtotime($date)) : now()->format('Y-m');
            $dir = 'news/' . $month;
            $filename = md5($url) . '.' . $ext;

            $absDir = storage_path('app/public/' . $dir);
            if (!is_dir($absDir)) mkdir($absDir, 0775, true);

            file_put_contents(storage_path('app/public/' . $dir . '/' . $filename), $data);
            return '/storage/' . $dir . '/' . $filename;
        } catch (\Exception $e) {
            return null;
        }
    }
}
