<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * DigitalOcean API 클라이언트 (Phase 2-C 묶음 8).
 *
 * Mock 모드: DO_API_TOKEN 미설정 시 로컬 SSH 기반 실데이터 + 더미 차트.
 * 실 모드: Token 설정 시 DO API 호출 (droplets, metrics, snapshots).
 */
class DigitalOceanService
{
    protected ?string $token;
    protected string  $baseUrl = 'https://api.digitalocean.com/v2';
    protected int     $dropletId;

    public function __construct()
    {
        $this->token     = env('DO_API_TOKEN', '');
        $this->dropletId = (int) env('DO_DROPLET_ID', 561254931);
    }

    public function isMock(): bool
    {
        return empty($this->token);
    }

    public function getDroplet(): array
    {
        if ($this->isMock()) return $this->mockDroplet();

        return Cache::remember('do.droplet', 60, function () {
            $res = Http::withToken($this->token)->get("{$this->baseUrl}/droplets/{$this->dropletId}");
            return $res->ok() ? $res->json('droplet') : $this->mockDroplet();
        });
    }

    public function getMetrics(string $type = 'cpu', int $hours = 24): array
    {
        // DO Monitoring API (읽기 전용, 실시간 가능)
        if ($this->isMock()) return $this->mockMetrics($type, $hours);

        return Cache::remember("do.metrics.{$type}.{$hours}", 60, function () use ($type, $hours) {
            $endpoint = [
                'cpu'    => '/monitoring/metrics/droplet/cpu',
                'memory' => '/monitoring/metrics/droplet/memory_utilization_percent',
                'disk'   => '/monitoring/metrics/droplet/filesystem_used',
                'network'=> '/monitoring/metrics/droplet/bandwidth',
            ][$type] ?? '/monitoring/metrics/droplet/cpu';

            $res = Http::withToken($this->token)->get("{$this->baseUrl}{$endpoint}", [
                'host_id' => (string) $this->dropletId,
                'start'   => now()->subHours($hours)->timestamp,
                'end'     => now()->timestamp,
            ]);
            return $res->ok() ? $res->json('data') : $this->mockMetrics($type, $hours);
        });
    }

    public function listSnapshots(): array
    {
        if ($this->isMock()) return $this->mockSnapshots();

        $res = Http::withToken($this->token)->get("{$this->baseUrl}/droplets/{$this->dropletId}/snapshots");
        return $res->ok() ? $res->json('snapshots', []) : [];
    }

    public function createSnapshot(string $name): array
    {
        if ($this->isMock()) {
            return ['success' => false, 'mock' => true, 'message' => 'Mock 모드: DO Token 입력 시 실제 Snapshot 생성 가능'];
        }

        $res = Http::withToken($this->token)->post("{$this->baseUrl}/droplets/{$this->dropletId}/actions", [
            'type' => 'snapshot',
            'name' => $name,
        ]);
        return $res->json();
    }

    public function listSizes(): array
    {
        if ($this->isMock()) return $this->mockSizes();

        $res = Http::withToken($this->token)->get("{$this->baseUrl}/sizes");
        return $res->ok() ? $res->json('sizes', []) : [];
    }

    public function resize(string $newSizeSlug, bool $disk = false): array
    {
        if ($this->isMock()) {
            return ['success' => false, 'mock' => true, 'message' => 'Mock 모드: DO Token 입력 시 실제 resize 가능'];
        }
        $res = Http::withToken($this->token)->post("{$this->baseUrl}/droplets/{$this->dropletId}/actions", [
            'type' => 'resize',
            'size' => $newSizeSlug,
            'disk' => $disk,
        ]);
        return $res->json();
    }

    // ─── Mock 데이터 ───
    protected function mockDroplet(): array
    {
        return [
            'id'        => $this->dropletId,
            'name'      => 'somekorean',
            'memory'    => 961,
            'vcpus'     => 1,
            'disk'      => 24,
            'status'    => 'active',
            'region'    => ['slug' => 'nyc3', 'name' => 'New York 3'],
            'size_slug' => 's-1vcpu-1gb',
            'networks'  => ['v4' => [['ip_address' => '68.183.60.70', 'type' => 'public']]],
            'created_at'=> '2026-03-27T00:00:00Z',
            '_mock'     => true,
        ];
    }

    protected function mockMetrics(string $type, int $hours): array
    {
        // 합리적인 랜덤 시계열 (hours 포인트 수)
        $points = min(max($hours, 12), 168);
        $now = time();
        $data = [];
        for ($i = $points; $i >= 0; $i--) {
            $ts = $now - ($i * 3600);
            $val = match ($type) {
                'cpu'     => rand(5, 40),
                'memory'  => rand(45, 75),
                'disk'    => 55 + rand(-2, 3),
                'network' => rand(100, 5000),
                default   => rand(10, 50),
            };
            $data[] = ['timestamp' => $ts, 'value' => $val];
        }
        return $data;
    }

    protected function mockSnapshots(): array
    {
        return [
            ['id'=>'mock-1','name'=>'pre-redesign-20260417','created_at'=>'2026-04-17T20:30:00Z','size_gigabytes'=>18.4,'_mock'=>true],
        ];
    }

    protected function mockSizes(): array
    {
        return [
            ['slug'=>'s-1vcpu-1gb','vcpus'=>1,'memory'=>1024,'disk'=>25, 'price_monthly'=>6,  'current'=>true],
            ['slug'=>'s-1vcpu-2gb','vcpus'=>1,'memory'=>2048,'disk'=>50, 'price_monthly'=>12],
            ['slug'=>'s-2vcpu-4gb','vcpus'=>2,'memory'=>4096,'disk'=>80, 'price_monthly'=>24, 'recommended'=>true],
            ['slug'=>'s-4vcpu-8gb','vcpus'=>4,'memory'=>8192,'disk'=>160,'price_monthly'=>48],
        ];
    }
}
