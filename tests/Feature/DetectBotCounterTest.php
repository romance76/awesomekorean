<?php

namespace Tests\Feature;

use App\Http\Middleware\DetectBot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * DetectBot 비로그인 요청 제한: 분 단위 창(window) 카운터.
 * 예전 방식(읽고 → 0이면 TTL 저장, 아니면 +1)은 요청이 동시에 올 때 TTL 없는 카운터가 생겨 그 IP 를 계속 막을 수 있었다.
 */
class DetectBotCounterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // 분 경계를 넘으며 카운터가 리셋돼 테스트가 흔들리지 않게, 분이 거의 끝났으면 다음 분까지 기다린다
        if (time() % 60 > 54) {
            sleep(61 - (time() % 60));
        }
    }

    private function hit(string $method, string $ip, string $path = 'api/posts', ?string $bearer = null): Response
    {
        $server = ['REMOTE_ADDR' => $ip];
        if ($bearer) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $bearer;
        }
        $req = Request::create('/' . $path, $method, [], [], [], $server);

        return (new DetectBot())->handle($req, fn () => response('ok', 200));
    }

    public function test_guest_reads_are_never_blocked(): void
    {
        for ($i = 1; $i <= 3000; $i++) {
            $this->assertSame(200, $this->hit('GET', '203.0.113.10')->getStatusCode(), "GET #{$i}");
        }
        $this->assertSame(200, $this->hit('HEAD', '203.0.113.10')->getStatusCode());
    }

    public function test_guest_writes_keep_300_per_minute_and_reads_do_not_use_up_the_write_budget(): void
    {
        for ($i = 0; $i < 600; $i++) {
            $this->hit('GET', '203.0.113.11');
        }
        for ($i = 1; $i <= 300; $i++) {
            $this->assertSame(200, $this->hit('POST', '203.0.113.11', 'api/qa')->getStatusCode(), "POST #{$i}");
        }
        $this->assertSame(429, $this->hit('POST', '203.0.113.11', 'api/qa')->getStatusCode());
        // 같은 IP 라도 읽기는 계속 허용(읽기 카운터는 따로)
        $this->assertSame(200, $this->hit('GET', '203.0.113.11')->getStatusCode());
    }

    public function test_login_endpoint_keeps_20_per_minute(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->assertSame(200, $this->hit('POST', '203.0.113.12', 'api/login')->getStatusCode(), "login #{$i}");
        }
        $this->assertSame(429, $this->hit('POST', '203.0.113.12', 'api/login')->getStatusCode());
    }

    public function test_limits_are_per_ip(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->hit('POST', '203.0.113.13', 'api/login');
        }
        $this->assertSame(429, $this->hit('POST', '203.0.113.13', 'api/login')->getStatusCode());
        $this->assertSame(200, $this->hit('POST', '203.0.113.14', 'api/login')->getStatusCode());
    }

    public function test_bearer_token_requests_are_never_counted(): void
    {
        for ($i = 0; $i < 1600; $i++) {
            $this->assertSame(200, $this->hit('GET', '203.0.113.15', 'api/posts', 'sometoken')->getStatusCode());
        }
    }

    public function test_stuck_counter_without_ttl_from_an_earlier_minute_no_longer_blocks(): void
    {
        $ip = '203.0.113.16';
        $prev = intdiv(time(), 60) - 1;
        // 예전 결함으로 생긴 "영원히 남은 카운터"를 흉내: 지난 창들의 키에 큰 값을 TTL 없이 저장
        Cache::forever("api_read:{$ip}", 99999);                 // 옛 키 형식
        Cache::forever("api_read:{$ip}:{$prev}", 99999);          // 지난 분의 키
        $this->assertSame(200, $this->hit('GET', $ip)->getStatusCode());
    }
}
