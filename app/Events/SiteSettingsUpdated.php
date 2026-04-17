<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 사이트 설정 변경 브로드캐스트 (Phase 2-C 묶음 7).
 * 관리자 저장 시 dispatch → Reverb 'site-settings' 채널 → 프론트 siteStore.forceReload().
 */
class SiteSettingsUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public array $keys;
    public string $scope;

    public function __construct(array $keys, string $scope = 'general')
    {
        $this->keys  = $keys;
        $this->scope = $scope;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('site-settings');
    }

    public function broadcastAs(): string
    {
        return 'settings.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'keys'       => $this->keys,
            'scope'      => $this->scope,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
