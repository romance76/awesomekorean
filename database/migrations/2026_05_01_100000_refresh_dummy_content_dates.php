<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026년 4월에 시딩된 더미 콘텐츠들의 날짜가 전부 그 시점 기준으로 생성되어
 * (예: rDate($n) = now()->subDays(rand(0,$n)) — 시딩 당시의 now()) 지금(9월)
 * 기준으로는 전부 4~5개월 전처럼 보임. 뉴스(news 테이블, 실제 크론으로
 * 가져온 기사 포함)는 그대로 두고, 나머지 더미 콘텐츠 날짜를 "지금 막 새로
 * 올라온 것처럼" 다시 랜덤 배정한다. 원래 시더가 쓰던 것과 동일한 일수
 * 범위를 NOW() 기준으로 재적용 — 로직/구조는 그대로, 날짜만 신선하게.
 *
 * 손대지 않는 것: news/news_categories(명시적 제외 요청), users(가입일),
 * events id 124~127(관리자가 직접 설정한 실제 이벤트, 별도 작업으로 관리 중),
 * chat_rooms/chat_messages 중 공개(public)가 아닌 방(더미 DM/그룹방은 이미
 * 자동 잠금/삭제 기능에 의해 정리 대상이므로 별도로 손대지 않음),
 * point_settings/chat_settings/site_settings/game_settings.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── 게시글 ──
        if (Schema::hasTable('posts')) {
            DB::statement("
                UPDATE posts SET
                    created_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*90) DAY) - INTERVAL FLOOR(RAND()*86400) SECOND,
                    updated_at = created_at
            ");
        }

        // ── 댓글 (게시글 댓글은 원글 이후 0~72시간 이내로) ──
        if (Schema::hasTable('comments') && Schema::hasTable('posts')) {
            DB::statement("
                UPDATE comments c
                JOIN posts p ON c.commentable_type = 'App\\\\Models\\\\Post' AND c.commentable_id = p.id
                SET c.created_at = LEAST(NOW(), p.created_at + INTERVAL FLOOR(RAND()*72) HOUR),
                    c.updated_at = c.created_at
            ");
            // Q&A(App\Models\QaPost, App\Models\QaAnswer) 댓글은 아래 qa:randomize-dates 커맨드가
            // 부모 질문/답변 작성일과 체인으로 맞춰서 처리하므로 여기서는 제외.
            // 그 외(혹시 있다면)는 독립적으로 최근 60일 내로.
            DB::statement("
                UPDATE comments SET
                    created_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*60) DAY),
                    updated_at = created_at
                WHERE commentable_type NOT IN ('App\\\\Models\\\\Post', 'App\\\\Models\\\\QaPost', 'App\\\\Models\\\\QaAnswer')
            ");
        }

        // ── 구인구직 (마감일도 다시 미래로) ──
        if (Schema::hasTable('job_posts')) {
            DB::statement("
                UPDATE job_posts SET
                    created_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*45) DAY) - INTERVAL FLOOR(RAND()*86400) SECOND,
                    updated_at = created_at,
                    expires_at = DATE_ADD(NOW(), INTERVAL FLOOR(7 + RAND()*53) DAY)
            ");
        }

        // ── 중고장터 ──
        if (Schema::hasTable('market_items')) {
            DB::statement("
                UPDATE market_items SET
                    created_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*60) DAY) - INTERVAL FLOOR(RAND()*86400) SECOND,
                    updated_at = created_at
            ");
        }

        // ── 부동산 ──
        if (Schema::hasTable('real_estate_listings')) {
            DB::statement("
                UPDATE real_estate_listings SET
                    created_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*60) DAY) - INTERVAL FLOOR(RAND()*86400) SECOND,
                    updated_at = created_at
            ");
        }

        // ── 동호회 + 멤버 + 게시글 ──
        if (Schema::hasTable('clubs')) {
            DB::statement("
                UPDATE clubs SET
                    created_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*120) DAY),
                    updated_at = created_at
            ");
        }
        if (Schema::hasTable('club_members')) {
            DB::statement("
                UPDATE club_members SET joined_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*90) DAY)
            ");
        }
        if (Schema::hasTable('club_posts')) {
            DB::statement("
                UPDATE club_posts SET
                    created_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*30) DAY) - INTERVAL FLOOR(RAND()*86400) SECOND,
                    updated_at = created_at
            ");
        }

        // ── 레시피 ──
        if (Schema::hasTable('recipe_posts')) {
            DB::statement("
                UPDATE recipe_posts SET
                    created_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*90) DAY) - INTERVAL FLOOR(RAND()*86400) SECOND,
                    updated_at = created_at
            ");
        }

        // ── 업소록 + 리뷰 ──
        if (Schema::hasTable('businesses')) {
            DB::statement("
                UPDATE businesses SET
                    created_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*180) DAY),
                    updated_at = created_at
            ");
        }
        if (Schema::hasTable('business_reviews')) {
            DB::statement("
                UPDATE business_reviews SET
                    created_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*60) DAY) - INTERVAL FLOOR(RAND()*86400) SECOND,
                    updated_at = created_at
            ");
        }

        // ── Q&A: 기존에 만들어져 있던 전용 커맨드 재사용 (질문→답변→댓글 체인까지 정확히 처리) ──
        if (Schema::hasTable('qa_posts')) {
            Artisan::call('qa:randomize-dates', ['--months' => 3]);
        }

        // ── 숏츠 ──
        if (Schema::hasTable('shorts')) {
            DB::statement("
                UPDATE shorts SET
                    created_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*30) DAY) - INTERVAL FLOOR(RAND()*86400) SECOND,
                    updated_at = created_at
            ");
        }

        // ── 공동구매: 진행중인 것만 마감일을 미래로, 종료/취소된 것은 최근 과거로 ──
        if (Schema::hasTable('group_buys')) {
            DB::statement("
                UPDATE group_buys SET
                    created_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*30) DAY) - INTERVAL FLOOR(RAND()*86400) SECOND,
                    updated_at = created_at,
                    deadline = CASE
                        WHEN status = 'recruiting' THEN DATE_ADD(NOW(), INTERVAL (3 + FLOOR(RAND()*28)) DAY)
                        ELSE DATE_SUB(NOW(), INTERVAL (1 + FLOOR(RAND()*14)) DAY)
                    END
            ");
        }

        // ── 이벤트: 관리자가 직접 만든 공식 이벤트(124~127)는 제외 ──
        if (Schema::hasTable('events')) {
            DB::statement("
                UPDATE events SET
                    start_date = (@ns := DATE_ADD(NOW(), INTERVAL (FLOOR(RAND()*120) - 30) DAY)),
                    end_date = IF(end_date IS NULL, NULL, DATE_ADD(@ns, INTERVAL (2 + FLOOR(RAND()*6)) HOUR)),
                    created_at = DATE_SUB(@ns, INTERVAL (7 + FLOOR(RAND()*23)) DAY),
                    updated_at = NOW()
                WHERE id NOT IN (124, 125, 126, 127)
            ");
        }

        // ── 쇼핑 특가: 마감일을 다시 미래로 ──
        if (Schema::hasTable('shopping_deals')) {
            DB::statement("
                UPDATE shopping_deals SET
                    expires_at = DATE_ADD(NOW(), INTERVAL (3 + FLOOR(RAND()*27)) DAY)
            ");
        }

        // ── 지역 공개 채팅방 더미 메시지: 최근 24시간 이내로 (공개방만, 잠금 기능 대상 아님) ──
        if (Schema::hasTable('chat_messages') && Schema::hasTable('chat_rooms')) {
            DB::statement("
                UPDATE chat_messages m
                JOIN chat_rooms r ON m.chat_room_id = r.id AND r.type = 'public'
                SET m.created_at = DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*1440) MINUTE),
                    m.updated_at = m.created_at
            ");
            DB::statement("
                UPDATE chat_rooms SET updated_at = NOW() WHERE type = 'public'
            ");
        }
    }

    public function down(): void
    {
        // 원래 날짜를 코드에 하드코딩하지 않았으므로 되돌리지 않음 (다른 데이터 마이그레이션과 동일한 정책).
    }
};
