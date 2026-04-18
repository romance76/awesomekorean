<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, \Spatie\Permission\Traits\HasRoles;

    // Spatie Permission: API·Web 동일 guard 사용 (JWT 인증이 web guard 기반)
    protected $guard_name = 'web';

    // Issue #6: 민감 필드(role, is_banned, points, game_points)는 mass assignment 금지.
    // 이들은 내부 서비스 로직에서만 forceFill / update(['key' => ...]) 로 명시 설정.
    protected $fillable = [
        'name', 'nickname', 'email', 'password', 'phone',
        'address', 'city', 'state', 'zipcode', 'latitude', 'longitude', 'default_radius',
        'avatar', 'bio', 'language',
        'address1', 'address2',
        'allow_friend_request', 'allow_messages', 'allow_elder_service',
        'last_login_at', 'last_active_at', 'login_count',
        'provider', 'provider_id',
        'fcm_token', 'push_platform',
        'notification_preferences',
    ];

    // 민감 필드 명시적 보호 (가이드 주석)
    // - role, is_banned, ban_reason: 관리자 컨트롤러에서만 직접 update
    // - points, game_points: addPoints()/usePoints() 헬퍼 경유

    protected $hidden = ['password', 'remember_token'];

    protected $appends = ['display_name'];

    /**
     * 표시 이름: 닉네임 → 이메일 앞부분 → 실명 순서
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->nickname) return $this->nickname;
        if ($this->email) return explode('@', $this->email)[0];
        return $this->attributes['name'] ?? '회원';
    }

    /**
     * API 응답에서 name → display_name으로 자동 교체
     * 실명은 real_name 필드로 별도 접근 가능 (부동산 등)
     */
    public function toArray()
    {
        $array = parent::toArray();
        $array['real_name'] = $this->attributes['name'] ?? '';
        $array['name'] = $this->display_name; // name 필드를 display_name으로 대체
        return $array;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_banned' => 'boolean',
            'points' => 'integer',
            'game_points' => 'integer',
            'login_count' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'allow_friend_request' => 'boolean',
            'allow_messages' => 'boolean',
            'allow_elder_service' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    // JWT
    public function getJWTIdentifier() { return $this->getKey(); }
    // P2B-20: JWT claims 에 role 포함 (프론트 권한 체크 효율화)
    public function getJWTCustomClaims() { return ['role' => $this->role]; }

    // Accessors
    public function getIsAdminAttribute(): bool { return in_array($this->role, ['admin', 'super_admin', 'moderator']); }

    // Relationships
    public function posts() { return $this->hasMany(Post::class); }
    public function jobPosts() { return $this->hasMany(JobPost::class); }
    public function marketItems() { return $this->hasMany(MarketItem::class); }
    public function clubs() { return $this->hasMany(Club::class); }
    public function pointLogs() { return $this->hasMany(PointLog::class); }
    public function notifications() { return $this->hasMany(Notification::class); }
    public function friends() { return $this->hasMany(Friend::class); }
    public function elderSetting() { return $this->hasOne(ElderSetting::class); }

    // Distance scope (miles)
    public function scopeNearby($query, $lat, $lng, $radius = 50)
    {
        return $query->selectRaw("*, (3959 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance", [$lat, $lng, $lat])
            ->having('distance', '<', $radius)
            ->orderBy('distance');
    }

    /**
     * 포인트 증감 + 이력 기록.
     *
     * Issue #12: related_type/related_id 인수 추가 — 특정 리소스와 포인트 변동을 연관지어
     * 감사·환불·중복 방지 로직에서 활용 가능.
     *
     * @param int $amount 증감량 (음수면 차감)
     * @param string $reason 사람이 읽는 설명
     * @param string $type 분류 코드 (earn/spend/groupbuy_join 등)
     * @param array|null $related ['type' => 'App\Models\Post', 'id' => 123] 형태
     */
    public function addPoints(int $amount, string $reason, string $type = 'earn', ?array $related = null)
    {
        $this->increment('points', $amount);
        $payload = [
            'amount' => $amount,
            'type' => $type,
            'reason' => $reason,
            'balance_after' => $this->fresh()->points,
        ];
        if ($related) {
            $payload['related_type'] = $related['type'] ?? null;
            $payload['related_id'] = $related['id'] ?? null;
        }
        $this->pointLogs()->create($payload);
    }
}
