<?php

namespace Tests\Unit;

use App\Support\NewsCategorizer;
use PHPUnit\Framework\TestCase;

class NewsCategorizerTest extends TestCase
{
    public function test_assigns_expected_categories(): void
    {
        $this->assertSame('international', NewsCategorizer::guess("美, 이란 '그림자 선단' 17척 제재…경제 압박 가속"));
        $this->assertSame('politics', NewsCategorizer::guess('국회 국정감사 시작…여야 공방'));
        $this->assertSame('economy', NewsCategorizer::guess('코스피 급등, 환율 하락', '외국인 매수세가 증시를 이끌었다'));
        $this->assertSame('sports', NewsCategorizer::guess('프로야구 우승팀 결정'));
        $this->assertSame('education', NewsCategorizer::guess('수능 원서 접수 시작'));
    }

    public function test_weak_evidence_falls_back_to_society(): void
    {
        $this->assertSame('society', NewsCategorizer::guess('어제 밤 도심에서 화재 발생'));
        $this->assertSame('society', NewsCategorizer::guess('', null));
    }
}
