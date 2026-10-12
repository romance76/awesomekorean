<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// MONEY 허브 (qa/money_hub_plan.md) — 정보 공유 카페 · 카페 글 · 머니 가이드. 기존 표는 건드리지 않는다.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('money_cafes')) {
            Schema::create('money_cafes', function (Blueprint $t) {
                $t->id();
                $t->string('slug', 60)->unique();
                $t->string('name', 60);
                $t->string('icon', 16)->nullable();
                $t->string('summary', 200)->nullable();     // 한 줄 소개
                $t->longText('intro')->nullable();           // 대문 (HTML, 관리자 편집)
                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->foreignId('manager_user_id')->nullable(); // 나중에 카페 운영자
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('money_posts')) {
            Schema::create('money_posts', function (Blueprint $t) {
                $t->id();
                $t->foreignId('cafe_id')->index();
                $t->foreignId('user_id')->index();
                $t->string('kind', 20)->default('info');    // info | question | talk
                $t->string('title', 200);
                $t->longText('content');
                $t->unsignedInteger('view_count')->default(0);
                $t->unsignedInteger('comment_count')->default(0);
                $t->boolean('is_hidden')->default(false);
                $t->timestamps();
                $t->index(['cafe_id', 'is_hidden', 'created_at']);
            });
        }
        if (!Schema::hasTable('money_guides')) {
            Schema::create('money_guides', function (Blueprint $t) {
                $t->id();
                $t->string('slug', 80)->unique();
                $t->string('icon', 16)->nullable();
                $t->string('title', 200);
                $t->string('summary', 300)->nullable();
                $t->longText('content')->nullable();
                $t->boolean('is_published')->default(false);
                $t->unsignedInteger('sort_order')->default(0);
                $t->timestamps();
            });
        }

        $now = now();
        $cafes = [
            ['earn', '💼', '돈 벌기', '온라인 부업부터 작은 비즈니스까지', '아마존 부업, Etsy·이베이 판매, 1099 프리랜서, 작은 가게 시작까지 — 미국에서 수입을 늘리는 방법을 서로 나눠요.'],
            ['save', '🛍️', '돈 아끼기', '미국 생활비를 똑똑하게 줄이는 방법', '마트 할인·캐시백·쿠폰, 통신비·보험료·공과금 줄이기 등 생활비를 아끼는 실전 정보를 모아요.'],
            ['grow', '📈', '돈 불리기', '저축과 투자, 목표를 향한 계획', '비상금, 고금리 예금, 401k·IRA, 투자 기초까지 — 돈을 모으고 키우는 이야기를 나눠요.'],
            ['home', '🏠', '부동산·주택', '내 집 마련부터 유지비 절약까지', '렌트·첫 집 장만·모기지·재산세·집 관리비 등 주거비에 관한 정보를 나눠요.'],
            ['insurance', '🛡️', '보험·금융 보호', '보장은 이해하고 보험료는 비교하기', '자동차·건강·생명·주택 보험과 금융 사기 예방 정보를 나눠요.'],
            ['tax', '🧾', '세금·정부 혜택', '미국 세금과 놓치기 쉬운 지원 제도', '세금 신고, 공제·크레딧, 정부 지원 프로그램처럼 놓치기 쉬운 정보를 모아요.'],
            ['cards', '💳', '신용카드·포인트', '캐시백부터 여행 마일리지까지', '신용점수, 카드 고르기, 캐시백·마일리지 활용법을 나눠요.'],
            ['stocks', '📊', '주식', '종목 정보와 시장 이야기', '시장 소식, 종목 공부, 장기 투자 경험을 나눠요. 특정 종목 권유·홍보는 금지예요.'],
            ['kids', '🎓', '자녀·교육비', '교육비를 미리 준비하는 방법', '529 플랜, 장학금, 학자금 대출, 방과 후 비용까지 교육비 이야기를 나눠요.'],
            ['retire', '🌅', '은퇴·노후', '은퇴 후 현금흐름을 설계하기', '소셜연금, 은퇴 계좌, 메디케어, 노후 생활비 계획을 나눠요.'],
            ['relay', '🏅', '돈 벌었어요 인증 릴레이', '"이걸로 이만큼 벌었어요" 인증을 이어가는 곳', '부업·절약·투자로 실제로 얻은 결과를 인증하고 다음 사람에게 릴레이로 이어가요. 개인정보는 가려 주세요.'],
            ['talk', '💬', '돈 이야기', '미국 한인들의 경험과 질문을 모으는 곳', '어느 카페에 올릴지 애매한 돈 이야기, 경험담, 질문을 자유롭게 나눠요.'],
        ];
        foreach ($cafes as $i => [$slug, $icon, $name, $summary, $about]) {
            if (DB::table('money_cafes')->where('slug', $slug)->exists()) continue;
            $intro = '<h3>이 카페는요</h3><p>' . e($about) . '</p>'
                . '<h3>이용 규칙</h3><ul><li>가입 없이 누구나 보고 답글을 달 수 있어요. 새 글은 로그인 후에 쓸 수 있어요.</li>'
                . '<li>광고·홍보·특정 상품 권유 글은 지워져요.</li><li>개인정보(계좌·전화번호 등)는 올리지 마세요.</li>'
                . '<li>여기 정보는 회원들의 경험이에요. 금융·세무·보험 결정은 전문가와 상담하세요.</li></ul>';
            DB::table('money_cafes')->insert(['slug' => $slug, 'name' => $name, 'icon' => $icon, 'summary' => $summary, 'intro' => $intro, 'sort_order' => $i + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $guides = [
            ['amazon-associates', '💼', '아마존 어소시에이트 시작하기', '가입 조건부터 제휴 링크 운영까지'],
            ['cashback-compare', '🛒', '마트 할인과 캐시백 비교', '실제 절약액을 계산하는 방법'],
            ['mortgage-pmi', '🏡', '모기지·PMI 기본 가이드', '월 납입금과 보험료 이해하기'],
            ['card-annual-fee', '💳', '신용카드 연회비 계산', '혜택을 실제로 쓰는지 확인하기'],
            ['insurance-checklist', '🛡️', '보험 비교 체크리스트', '보험료보다 보장 조건부터 확인'],
        ];
        foreach ($guides as $i => [$slug, $icon, $title, $summary]) {
            if (DB::table('money_guides')->where('slug', $slug)->exists()) continue;
            DB::table('money_guides')->insert(['slug' => $slug, 'icon' => $icon, 'title' => $title, 'summary' => $summary,
                'content' => '<p>내용 준비 중이에요. (관리자 › MONEY 관리에서 쓰고 "공개"로 바꾸면 보여요)</p>',
                'is_published' => false, 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // 데이터 보호: 표를 지우지 않는다 (테이블 DROP 금지 규칙). 되돌릴 때는 라우트·화면만 내린다.
    }
};
