<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 뉴스 AI 해설 연동(/api/news-ingest/*): 토큰 인증, 후보 목록, 저장/건너뜀, 원문 복사 거절, 하루 한도.
 * MariaDB 테스트 DB 로 실행 (마이그레이션이 MySQL 전용):
 *   DB_CONNECTION=mysql DB_DATABASE=<테스트DB> DB_USERNAME=.. DB_PASSWORD=.. php artisan test tests/Feature/NewsIngestTest.php
 */
class NewsIngestTest extends TestCase
{
    use RefreshDatabase;

    private const SUMMARY = "미국 정부가 전문직 취업 비자 소지자의 영주권 후원 요건을 손보겠다고 밝혔습니다. 발표에 따르면 일부 대형 IT 기업이 한동안 새로운 후원을 멈추게 되며, 이미 절차가 진행 중인 사람은 영향이 제한적일 것으로 보입니다.\n\n한인 취업 이민 준비자라면 고용주에게 현재 진행 상황과 앞으로의 일정을 직접 확인해 두는 것이 좋습니다. 세부 기준은 아직 확정되지 않아 후속 발표를 지켜봐야 합니다.";

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.info_ingest.token' => 'test-token', 'services.news_ai.daily_cap' => 2]);
    }

    private function auth(): array { return ['Authorization' => 'Bearer test-token']; }

    private function news(array $o = []): News
    {
        return News::create(array_merge([
            'title' => '美 영주권 후원 중단', 'content' => '원문 본문 일부입니다. 이 문장은 사이트에 저장된 짧은 본문이며 사십 자가 넘도록 충분히 길게 적어 둔 문장입니다 정말로요.',
            'summary' => '요약', 'source' => '뉴시스', 'source_url' => 'https://example.com/a/1', 'published_at' => now()->subHour(), 'is_active' => true,
        ], $o));
    }

    public function test_requires_token(): void
    {
        $this->getJson('/api/news-ingest/pending')->assertStatus(401);
        $this->postJson('/api/news-ingest/summary', [])->assertStatus(401);
    }

    public function test_pending_lists_only_recent_unsummarized(): void
    {
        $a = $this->news();
        $this->news(['title' => '이미 함', 'ai_status' => 'done', 'ai_summary' => 'x']);
        $this->news(['title' => '오래됨', 'published_at' => now()->subDays(5)]);
        $r = $this->getJson('/api/news-ingest/pending', $this->auth())->assertOk();
        $this->assertSame([$a->id], collect($r->json('data'))->pluck('id')->all());
        $this->assertSame(2, $r->json('remaining_today'));
    }

    public function test_store_saves_summary_and_cleans_markup(): void
    {
        $n = $this->news();
        $this->postJson('/api/news-ingest/summary', ['id' => $n->id, 'status' => 'done', 'ai_summary' => '<b>' . self::SUMMARY . '</b>'], $this->auth())->assertOk();
        $n->refresh();
        $this->assertSame('done', $n->ai_status);
        $this->assertStringNotContainsString('<b>', $n->ai_summary);
        $this->assertNotNull($n->ai_summarized_at);
        // 목록 API 는 본문 미리보기용 summary 와 해설 여부(ai_status)를 내려준다
        $row = $this->getJson('/api/news')->assertOk()->json('data.data.0');
        $this->assertSame('done', $row['ai_status']);
        $this->assertSame('요약', $row['summary']);
        $this->assertArrayNotHasKey('ai_summary', $row);
        $this->assertSame(self::SUMMARY, $this->getJson("/api/news/{$n->id}")->json('data.ai_summary'));
    }

    public function test_rejects_short_and_copied_summaries(): void
    {
        $n = $this->news();
        $this->postJson('/api/news-ingest/summary', ['id' => $n->id, 'status' => 'done', 'ai_summary' => '너무 짧아요'], $this->auth())->assertStatus(422);
        $copied = self::SUMMARY . "\n\n원문 본문 일부입니다. 이 문장은 사이트에 저장된 짧은 본문이며 사십 자가 넘도록 충분히 길게 적어 둔 문장입니다 정말로요.";
        $this->postJson('/api/news-ingest/summary', ['id' => $n->id, 'status' => 'done', 'ai_summary' => $copied], $this->auth())->assertStatus(422);
        $this->assertNull($n->fresh()->ai_status);
    }

    public function test_skip_and_idempotent(): void
    {
        $n = $this->news();
        $this->postJson('/api/news-ingest/summary', ['id' => $n->id, 'status' => 'skipped'], $this->auth())->assertOk();
        $this->assertSame('skipped', $n->fresh()->ai_status);
        $this->postJson('/api/news-ingest/summary', ['id' => $n->id, 'status' => 'done', 'ai_summary' => self::SUMMARY], $this->auth())->assertOk()->assertJsonPath('already', true);
        $this->assertNull($n->fresh()->ai_summary);
    }

    public function test_daily_cap_is_enforced(): void
    {
        foreach ([1, 2] as $i) {
            $n = $this->news(['source_url' => "https://example.com/a/$i"]);
            $this->postJson('/api/news-ingest/summary', ['id' => $n->id, 'status' => 'done', 'ai_summary' => self::SUMMARY . $i], $this->auth())->assertOk();
        }
        $third = $this->news(['source_url' => 'https://example.com/a/3']);
        $this->postJson('/api/news-ingest/summary', ['id' => $third->id, 'status' => 'done', 'ai_summary' => self::SUMMARY . '3'], $this->auth())->assertStatus(429);
        $r = $this->getJson('/api/news-ingest/pending', $this->auth())->assertOk();
        $this->assertSame(0, $r->json('remaining_today'));
        $this->assertSame([], $r->json('data'));
    }
}
