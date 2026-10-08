<?php

namespace Tests\Feature;

use App\Models\News;
use App\Models\NewsCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsCategoryNewTodayTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_report_todays_news_count(): void
    {
        $a = NewsCategory::firstOrCreate(['slug' => 'politics'], ['name' => '정치']);
        $b = NewsCategory::firstOrCreate(['slug' => 'economy'], ['name' => '경제']);

        $mk = fn ($cat, $at, $active = true) => News::create([
            'title' => 't' . uniqid(), 'summary' => str_repeat('요약 ', 30), 'image_url' => 'https://example.com/a.jpg', 'content' => 'c', 'source' => 'x',
            'category_id' => $cat->id, 'published_at' => $at, 'is_active' => $active,
        ]);
        $mk($a, now());
        $mk($a, now());
        $mk($b, now()->subDays(3));
        $mk($b, now(), false);

        $res = $this->getJson('/api/news/categories')->assertOk();
        $by = collect($res->json('data'))->keyBy('slug');
        $this->assertSame(2, $by['politics']['new_today']);
        $this->assertSame(0, $by['economy']['new_today']);
        $this->assertSame(2, $res->json('meta.all_new_today'));
    }

    public function test_list_hides_news_without_image_or_with_tiny_summary(): void
    {
        $cat = NewsCategory::firstOrCreate(['slug' => 'society'], ['name' => '사회']);
        $base = ['content' => 'c', 'source' => 'x', 'category_id' => $cat->id, 'published_at' => now(), 'is_active' => true];
        $long = str_repeat('요약 ', 30);
        $ok = News::create($base + ['title' => 'ok', 'summary' => $long, 'image_url' => 'https://example.com/a.jpg']);
        News::create($base + ['title' => 'no image', 'summary' => $long]);
        News::create($base + ['title' => 'tiny', 'summary' => '후속기사가 이어집니다', 'image_url' => 'https://example.com/b.jpg']);

        $ids = collect($this->getJson('/api/news')->assertOk()->json('data.data'))->pluck('id')->all();
        $this->assertSame([$ok->id], $ids);
        $this->assertSame(1, $this->getJson('/api/news/categories')->json('meta.all_new_today'));
    }
}
