<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 한인 유튜버 소개: 채널(관리자 승인 후 공개) + 채널의 영상·쇼츠(유튜브에서 자동 갱신) + 상단 메뉴(즐길거리 묶음 → 'YOUTUBE')
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('youtube_channels')) {
            Schema::create('youtube_channels', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable()->index();      // 신청한 회원 (관리자가 직접 등록하면 비어 있음)
                $t->string('channel_id', 40)->unique();                      // UC... 유튜브 채널 번호
                $t->string('handle', 80)->nullable();                        // @이름
                $t->string('title', 150);
                $t->text('description')->nullable();                         // 채널 소개 (유튜브에서 가져온 것)
                $t->string('intro', 300)->nullable();                        // 신청자가 쓴 한 줄 소개
                $t->string('thumbnail_url', 500)->nullable();
                $t->string('banner_url', 500)->nullable();
                $t->string('category', 30)->default('etc')->index();
                $t->unsignedBigInteger('subscriber_count')->default(0);
                $t->unsignedBigInteger('video_count')->default(0);
                $t->string('status', 12)->default('pending')->index();      // pending · approved · rejected
                $t->string('reject_reason', 200)->nullable();
                $t->boolean('is_featured')->default(false)->index();         // 추천 유튜버(맨 위 줄)
                $t->timestamp('featured_until')->nullable();
                $t->timestamp('latest_video_at')->nullable()->index();
                $t->timestamp('last_synced_at')->nullable();
                $t->unsignedInteger('view_count')->default(0);
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('youtube_videos')) {
            Schema::create('youtube_videos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('youtube_channel_id')->index();
                $t->string('video_id', 20)->unique();
                $t->string('title', 300);
                $t->string('thumbnail_url', 500)->nullable();
                $t->unsignedInteger('duration_seconds')->default(0);
                $t->boolean('is_short')->default(false)->index();
                $t->unsignedBigInteger('view_count')->default(0);
                $t->timestamp('published_at')->nullable()->index();
                $t->timestamps();
            });
        }

        // 상단 메뉴: menu_config 에 '한인 유튜버' 추가 (이미 있으면 건드리지 않음)
        $row = DB::table('site_settings')->where('key', 'menu_config')->first();
        $menus = $row && $row->value ? json_decode($row->value, true) : null;
        if (is_array($menus) && !collect($menus)->contains(fn ($m) => ($m['key'] ?? null) === 'youtubers')) {
            $menus[] = ['key' => 'youtubers', 'label' => '한인 유튜버', 'label_en' => 'YouTubers', 'icon' => '📺', 'path' => '/youtubers',
                'enabled' => true, 'order' => count($menus), 'login_required' => false, 'admin_only' => false, 'defaultView' => 'list', 'hasCardView' => false,
                'ads_enabled' => true, 'ads_type' => 'banner'];
            DB::table('site_settings')->where('key', 'menu_config')->update(['value' => json_encode($menus, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        }
        // 상단 묶음: '즐길거리' 묶음 이름을 'YOUTUBE' 로 바꾸고 맨 앞에 '한인 유튜버' 를 넣는다 (묶음을 누르면 첫 메뉴로 이동)
        $row = DB::table('site_settings')->where('key', 'menu_groups')->first();
        $groups = $row && $row->value ? json_decode($row->value, true) : null;
        if (is_array($groups) && !collect($groups)->contains(fn ($g) => in_array('youtubers', $g['items'] ?? [], true))) {
            $at = collect($groups)->search(fn ($g) => ($g['key'] ?? null) === 'fun');
            if ($at !== false) {
                $groups[$at]['label'] = 'YOUTUBE';
                $groups[$at]['label_en'] = 'YouTube';
                $groups[$at]['icon'] = '📺';
                array_unshift($groups[$at]['items'], 'youtubers');
            } else {
                $groups[] = ['key' => 'fun', 'label' => 'YOUTUBE', 'label_en' => 'YouTube', 'icon' => '📺', 'type' => 'group', 'items' => ['youtubers']];
            }
            DB::table('site_settings')->where('key', 'menu_groups')->update(['value' => json_encode($groups, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        }
        Cache::forget('site_settings_public');
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_videos');
        Schema::dropIfExists('youtube_channels');
    }
};
