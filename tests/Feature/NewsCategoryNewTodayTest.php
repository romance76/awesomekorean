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
            'title' => 't' . uniqid(), 'summary' => 's', 'content' => 'c', 'source' => 'x',
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
}
