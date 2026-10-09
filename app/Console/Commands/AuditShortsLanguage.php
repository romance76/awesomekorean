<?php

namespace App\Console\Commands;

use App\Models\Short;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * 이미 저장된 숏츠 중 한국어·영어권이 아닌(인도·동남아 등) 영상을 찾아낸다.
 * 수집 때와 같은 기준(FetchYoutubeShorts 의 2026-10-09 추가 필터)을 저장된 영상에 다시 적용.
 *
 * 기본은 점검만(dry-run) — 삭제하지 않고, --apply 를 줘도 삭제가 아니라 is_active=false 로 숨김만 한다
 * (되돌리려면 is_active=1). 영상 조회는 videos.list 라 50개당 쿼터 1.
 */
class AuditShortsLanguage extends Command
{
    protected $signature = 'shorts:audit-lang {--apply : 걸린 영상을 비활성화(숨김)} {--show=15 : 점검 결과로 보여줄 예시 개수}';
    protected $description = '저장된 숏츠 중 한국어·영어권이 아닌 영상을 점검(기본 dry-run)';

    public function handle()
    {
        $apiKey = config('services.youtube.api_key');
        if (!$apiKey && file_exists(base_path('.env')) && preg_match('/YOUTUBE_API_KEY=(.+)/', file_get_contents(base_path('.env')), $m)) $apiKey = trim($m[1]);
        if (!$apiKey) { $this->error('YOUTUBE_API_KEY not set'); return 1; }

        $flagged = []; // id => [reason, title]
        $checked = 0;

        Short::whereNull('user_id')->where('is_active', true)->whereNotNull('youtube_id')->orderBy('id')
            ->chunkById(50, function ($rows) use ($apiKey, &$flagged, &$checked) {
                $res = Http::timeout(15)->get('https://www.googleapis.com/youtube/v3/videos', [
                    'key' => $apiKey, 'id' => $rows->pluck('youtube_id')->implode(','), 'part' => 'snippet',
                ]);
                if (!$res->ok()) { $this->warn('API 오류 ' . $res->status() . ' — 중단'); return false; }
                $byYt = collect($res->json('items', []))->keyBy('id');
                foreach ($rows as $row) {
                    $v = $byYt[$row->youtube_id] ?? null;
                    if (!$v) continue; // 조회 안 되는 영상은 기존 revalidateExisting 이 처리
                    $checked++;
                    $reason = $this->judge($v['snippet'] ?? [], $row->title);
                    if ($reason) $flagged[$row->id] = [$reason, $row->title];
                }
            });

        $this->info("점검 {$checked}개 중 외국 영상 의심 " . count($flagged) . '개');
        foreach (collect($flagged)->countBy(fn ($f) => $f[0]) as $reason => $n) $this->line("  - {$reason}: {$n}");
        foreach (array_slice($flagged, 0, (int) $this->option('show'), true) as $id => [$reason, $title]) {
            $this->line("  #{$id} [{$reason}] " . mb_substr($title, 0, 70));
        }

        if ($this->option('apply') && $flagged) {
            $n = Short::whereIn('id', array_keys($flagged))->update(['is_active' => false]);
            $this->info("🚫 {$n}개 비활성화(숨김) 완료 — 삭제하지 않았습니다");
        } elseif ($flagged) {
            $this->comment('점검만 했습니다. 숨기려면 --apply');
        }
        return 0;
    }

    private function judge(array $sn, string $storedTitle): ?string
    {
        $title = $sn['title'] ?? $storedTitle;
        $desc = $sn['description'] ?? '';
        $all = $title . ' ' . ($sn['channelTitle'] ?? '') . ' ' . $desc;
        $hasHangul = (bool) preg_match('/[\x{AC00}-\x{D7AF}]/u', $all);

        if (preg_match('/[\x{0900}-\x{097F}]|[\x{0600}-\x{06FF}]|[\x{0E00}-\x{0E7F}]|[\x{0980}-\x{09FF}]|[\x{0B80}-\x{0BFF}]|[\x{0C00}-\x{0C7F}]|[\x{0400}-\x{04FF}]|[\x{3040}-\x{30FF}]/u', $title . ' ' . $desc)) return '비한국 문자';
        if (preg_match('/pubg|bgmi|freefire|free fire|garena|zodiac|astrolog|horoscope|rashifal|cricket|\bipl\b|chhath|diwali|hindu|\bindia(n|ns)?\b|bharat|\bmodi\b|\bpuja\b|sadhu|ganga|punjab|pakistan|bangladesh|nepal|sri ?lanka|mumbai|delhi|kolkata|\bdesi\b|tamil|telugu|kerala|chennai|hyderabad|bengal/i', $all)) return '인도권 키워드';
        if (!$hasHangul) {
            $audio = strtolower($sn['defaultAudioLanguage'] ?? $sn['defaultLanguage'] ?? '');
            if ($audio === '' || !(str_starts_with($audio, 'en') || str_starts_with($audio, 'ko'))) return $audio === '' ? '언어 표시 없음(한글 없음)' : "다른 언어({$audio})";
            if (preg_match_all('/#\w+/u', $all) >= 6) return '해시태그 도배';
        }
        return null;
    }
}
