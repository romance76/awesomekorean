<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\QaPost;
use App\Models\QaAnswer;

/**
 * Q&A 본문/제목에서 네이버 출처 흔적 정리:
 * - 네이버/naver/지식인 → 어썸코리안/awesomekorean/Q&A
 * - 변호사 프로필 보기/답변 더 보기 등 잔여 링크 텍스트 제거
 * - tistory.com 등 외부 도메인 링크 제거
 * - 원래 질문에 대한 링크 < 클릭 패턴 제거
 * - 빈 #/| 라인 정리
 *
 * 사용:
 *   php artisan qa:clean-text          # 실제 실행
 *   php artisan qa:clean-text --dry    # 변경 카운트만 확인 (저장 안 함)
 */
class CleanQaText extends Command
{
    protected $signature = 'qa:clean-text {--dry : 변경 사항만 카운트하고 저장 안 함}';
    protected $description = 'Q&A 본문/제목에서 네이버 출처/잔여 링크/노이즈 정리';

    /** [pattern, replacement] — 순서대로 적용. 긴 문구 먼저! */
    private array $replacements = [
        // ─── 네이버 지식인 → 어썸코리안 Q&A (가장 긴 문구 먼저)
        ['/네이버\s*지식\s*[인iI]?[nN]?/u',                    '어썸코리안 Q&A'],
        ['/지식\s*[i I][n N]/u',                                'Q&A'],
        ['/지식인/u',                                            'Q&A'],
        // ─── 네이버 도메인
        ['/(?<![a-zA-Z])naver\.com/i',                           'awesomekorean.com'],
        ['/네이버\.?닷컴/u',                                      'awesomekorean.com'],
        // ─── 네이버 단독
        ['/네이버/u',                                            '어썸코리안'],
        // ─── never 단어 (영문)
        ['/(?<![a-zA-Z])naver(?![a-zA-Z])/i',                    'awesomekorean'],
    ];

    /** 제거할 정규식 패턴들 */
    private array $removals = [
        // 변호사 / 답변 / 프로필 등 잔여 링크 텍스트 (대괄호 안)
        '/\[\s*변호사\s+프로필\s+보기\s*\]/u',
        '/\[\s*변호사님의?\s+답변\s+더?\s*보기\s*\]/u',
        '/\[\s*더\s+많은\s+문의\s*사례?\s+보기\s*\]/u',
        '/\[\s*[^\]]*?(?:프로필|답변)\s+(?:더\s*)?보기\s*\]/u',
        // [이미지 클릭] / 변호사 프로필 링크 / 상담번호 보기 같은 ▼...▼ 또는 ⬇️...⬇️ 광고성 라인
        '/▼[^\n▼]{0,200}▼/u',
        '/⬇️?[^\n⬇️]{0,200}⬇️?/u',
        '/\[?이미지\s*클릭\]?[^\n]{0,80}(?:프로필|답변|상담)[^\n]{0,80}/u',
        // 변호사 프로필 + 바로가기/링크 라인 단독
        '/[^\n]*변호사\s*프로필[^\n]{0,40}(?:바로가기|링크)[^\n]{0,40}/u',
        // 변호사/이민/노무 프로필 라인 (컬론 + 어썸코리안 Q&A 패턴)
        '/[가-힣A-Za-z0-9_\s]{1,30}변호사\s*프로필\s*[:：][^\n]*어썸코리안\s*Q&A/u',
        '/[가-힣A-Za-z0-9_\s]{1,30}(?:변호사|이민\s*컨설턴트|노무사)\s*프로필\s*[:：][^\n]{0,100}/u',
        // 원래 질문에 대한 링크 < 클릭
        '/원래\s*질문에\s*대한\s*링크\s*[<＜]?\s*클릭\s*하?\s*[기여]?\s*/u',
        // tistory 풀 URL — 한글 도메인 포함 ( unicode property class 사용 )
        '/https?:\/\/[^\s\)\]]*\.tistory\.com[^\s\)\]]*/iu',
        '/[\p{L}\p{N}_\-\s]{1,40}\.tistory\.com(?:\/[^\s\)\]]*)?/iu',
        // navercafe / 외부 mass mail URL
        '/https?:\/\/[^\s\)\]]*navercafe[^\s\)\]]*/iu',
        '/https?:\/\/[^\s\)\]]*\/navercafe\/[^\s\)\]]*/iu',
        // utm_source=naver* 가 포함된 외부 추적 URL 전체 제거
        '/https?:\/\/[^\s\)\]]*utm_source=naver[^\s\)\]]*/iu',
        // 변형 tistory: daumtistory, adsensefarm/tistory 등
        '/https?:\/\/[^\s\)\]]*tistory[^\s\)\]]*/iu',
        '/[a-zA-Z0-9_\-]*tistory\.com[^\s\)\]]*/iu',
        // 괄호 안의 tistory 표기 (tistory.com)
        '/\(\s*[a-zA-Z0-9_\-]*tistory\.com\s*\)/iu',
        // See More + 뒤따르는 가비지 (URL 인코딩 또는 영숫자)
        '/\s*See\s+More\s*[\-:]?\s*[%A-Za-z0-9_\-]*/iu',
        // 결과적으로 남은 빈 대괄호 [ ] [ ]
        '/\[\s*\]/u',
        // ─── 로톡/Rotalk/로지콤/로시콤 광고/면책 disclaimer 라인 (한 줄 통째 제거) ───
        // 이 키워드들은 우리 사이트와 무관한 외부 서비스명이므로 등장하는 라인 모두 제거
        '/^[^\n]*(?:로톡|Rotalk|로지콤|로시콤)[^\n]*$/imu',
        // 콘텐츠 제휴 disclaimer (외부 서비스명 없이도)
        '/^[^\n]*(?:Q&A|지식\s*[i인])[^\n]*변호사\s*답변[^\n]*콘텐츠\s*제휴[^\n]*$/imu',
        '/^[^\n]*콘텐츠\s*제휴[^\n]*$/imu',
        // "변호사와 의뢰인을 더욱 가깝게 만드는 ..." 광고
        '/^[^\n]*변호사와\s*의뢰인[^\n]*가깝게\s*만드는[^\n]*$/imu',
        // ─── 법무법인 광고/실시간 응답 disclaimer (라인 통째 제거) ───
        // "로이즈 법무법인은 실시간 광고응답..." / "법무법인 미담은 실시간 광고성 답변..."
        '/^[^\n]*법무법인[^\n]*실시간\s*광고[^\n]*$/imu',
        '/^[^\n]*법무법인[^\n]*광고(?:성|응답)[^\n]*$/imu',
        // 광고성 답변 일반 disclaimer
        '/^[^\n]*광고성\s*답변[^\n]*제공하지\s*않[^\n]*$/imu',
        '/^[^\n]*응답량을?\s*(?:늘리|높이)[^\n]*불필요한\s*내용[^\n]*$/imu',
        // "문의하신 분에게 즉각적인 답변..." 면책 라인
        '/^[^\n]*즉각적인\s*답변[^\n]*법률에\s*근거[^\n]*$/imu',
        // ─── blog.awesomekorean.com 잔여 (원래 blog.naver.com 변환된 결과) ───
        '/blog\.awesomekorean\.com[^\s\n]*/iu',
        // ─── 법무법인 + 광고 + 변호사 이름 광고 라인 (특정 변호사 홍보) ───
        '/^[^\n]*법무법인\s*광고\s*변호사[^\n]*$/imu',
        // ☎ 또는 ☎️ + 전화번호 + 변호사 이름 (광고 콜 라인)
        '/^[^\n]*[☎📞]\s*[\d\-\s\*]+(?:변호사|법무법인)[^\n]*$/imu',
        // 법률상담 전화 + 번호 + 변호사 이름
        '/^[^\n]*법률\s*상담[^\n]*[\d\-\*]+[^\n]*변호사[^\n]*$/imu',
        // 카톡 @ID + 변호사 이름 광고
        '/^[^\n]*문의\s*카톡[^\n]*@[^\n]*변호사[^\n]*$/imu',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $stats = [
            'posts_title' => 0,
            'posts_content' => 0,
            'answers_content' => 0,
            'total_replacements' => 0,
        ];

        // ─── Posts: title + content ───
        $this->info('📝 qa_posts 처리...');
        $posts = QaPost::select('id', 'title', 'content')->get();
        $bar = $this->output->createProgressBar($posts->count());
        $bar->start();
        foreach ($posts as $p) {
            $newTitle = $this->cleanText($p->title, $stats);
            $newContent = $this->cleanText($p->content, $stats);
            $changed = false;
            $update = [];
            if ($newTitle !== $p->title) { $update['title'] = $newTitle; $stats['posts_title']++; $changed = true; }
            if ($newContent !== $p->content) { $update['content'] = $newContent; $stats['posts_content']++; $changed = true; }
            if ($changed && !$dry) {
                QaPost::where('id', $p->id)->update($update);
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        // ─── Answers: content ───
        $this->info('💬 qa_answers 처리...');
        $answers = QaAnswer::select('id', 'content')->get();
        $bar = $this->output->createProgressBar($answers->count());
        $bar->start();
        foreach ($answers as $a) {
            $newContent = $this->cleanText($a->content, $stats);
            if ($newContent !== $a->content) {
                $stats['answers_content']++;
                if (!$dry) {
                    QaAnswer::where('id', $a->id)->update(['content' => $newContent]);
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $this->info($dry ? '🔍 [DRY RUN] 결과' : '✅ 정리 완료');
        $this->table(
            ['항목', '변경된 수'],
            [
                ['질문 제목 (qa_posts.title)',     $stats['posts_title']],
                ['질문 본문 (qa_posts.content)',   $stats['posts_content']],
                ['답변 본문 (qa_answers.content)', $stats['answers_content']],
                ['총 치환/제거 횟수',              $stats['total_replacements']],
            ]
        );

        return 0;
    }

    private function cleanText(?string $text, array &$stats): string
    {
        if ($text === null || $text === '') return $text ?? '';

        $original = $text;

        // 1) 치환
        foreach ($this->replacements as [$pat, $repl]) {
            $text = preg_replace($pat, $repl, $text, -1, $count);
            if ($count > 0) $stats['total_replacements'] += $count;
        }

        // 2) 제거 (빈 문자열로 치환)
        foreach ($this->removals as $pat) {
            $text = preg_replace($pat, '', $text, -1, $count);
            if ($count > 0) $stats['total_replacements'] += $count;
        }

        // 3) 결과 정리
        // 라인이 # 또는 | 또는 공백/대시 만 있는 경우 제거
        $lines = preg_split('/\r?\n/', $text);
        $cleanLines = [];
        foreach ($lines as $line) {
            $stripped = trim($line);
            // 노이즈만 남은 라인 제거: # 만, | 만, < 만, < 클릭 만, 등
            if (preg_match('/^[#\|<>＜＞\-\s]*$/u', $stripped)) {
                continue;
            }
            // 줄 끝의 고립된 # | 정리
            $cleanLines[] = rtrim($line);
        }
        $text = implode("\n", $cleanLines);

        // 4) 연속 빈 줄 압축 (3개 이상 → 2개)
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // 5) trim
        $text = trim($text);

        return $text;
    }
}
