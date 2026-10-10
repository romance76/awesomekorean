<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    // Issue #6: 민감 필드(role, is_banned, points, game_points)는 mass assignment 금지.
    // 이들은 내부 서비스 로직에서만 forceFill / update(['key' => ...]) 로 명시 설정.
    protected $fillable = [
        'name', 'nickname', 'email', 'password', 'phone',
        'address', 'city', 'state', 'zipcode', 'latitude', 'longitude', 'default_radius',
        'free_public_room_id',
        'avatar', 'bio', 'language',
        'address1', 'address2',
        'allow_friend_request', 'allow_messages', 'allow_elder_service',
        'last_login_at', 'last_active_at', 'login_count',
        'provider', 'provider_id',
        'fcm_token', 'push_platform',
    ];

    // 민감 필드 명시적 보호 (가이드 주석)
    // - role, is_banned, ban_reason: 관리자 컨트롤러에서만 직접 update
    // - points, game_points: addPoints()/usePoints() 헬퍼 경유
    // - entries: Point와 완전히 분리된 별도 자산. App\Support\EntryService 경유만
    //   허용 — Point와의 교환 경로가 생기지 않도록 이 모델 안에 변환 헬퍼를 두지 않는다.

    protected $hidden = ['password', 'remember_token'];


    // 비밀번호가 바뀌면(재설정/변경/관리자 초기화 모두) 시각을 기록 — 이전에 발급된 로그인 토큰을 무효로 만드는 기준.
    protected static function booted(): void
    {
        static::updating(function (self $user) {
            if ($user->isDirty('password')) {
                $user->password_changed_at = now();
            }
        });
    }

    protected $appends = ['display_name', 'grade_level'];

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
     * 회원 등급 번호(1~15). 프로필 사진 테두리(링) 표시에 쓴다.
     * lifetime_points 컬럼을 select 하지 않은 조회에서는 null (추가 쿼리 없음) → 화면에서는 링을 그리지 않는다.
     */
    public function getGradeLevelAttribute(): ?int
    {
        if (!array_key_exists('lifetime_points', $this->attributes)) return null;
        return \App\Support\MemberGrade::levelFor((int) $this->attributes['lifetime_points']);
    }

    /**
     * API 응답에서 name → display_name으로 자동 교체
     * 실명은 real_name 필드로 별도 접근 가능 (부동산 등)
     */
    /** 로그인/가입 응답처럼 "본인에게 돌려주는" 직렬화에서만 true 로 켠다 (toArray 의 lifetime_points 제거 예외) */
    public bool $exposeLifetimePoints = false;

    public function toArray()
    {
        $array = parent::toArray();
        $array['real_name'] = $this->attributes['name'] ?? '';
        $array['name'] = $this->display_name; // name 필드를 display_name으로 대체

        // 누적 포인트는 등급 링(grade_level) 계산에만 쓰고, 본인/관리자 외에는 API 응답에 내보내지 않는다.
        if (!$this->exposeLifetimePoints && array_key_exists('lifetime_points', $array)) {
            $viewer = auth()->user();
            $isSelf = $viewer && (int) $viewer->id === (int) ($this->attributes['id'] ?? 0);
            $isAdmin = $viewer && in_array($viewer->role, ['admin', 'super_admin'], true);
            if (!$isSelf && !$isAdmin) unset($array['lifetime_points']);
        }
        return $array;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
            'is_banned' => 'boolean',
            'points' => 'integer',
            'game_points' => 'integer',
            'entries' => 'integer',
            'entry_checkin_progress' => 'integer',
            'entry_activity_progress' => 'integer',
            'login_count' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'allow_friend_request' => 'boolean',
            'allow_messages' => 'boolean',
            'allow_elder_service' => 'boolean',
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
    public function entryTransactions() { return $this->hasMany(EntryTransaction::class); }
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
        // 회원 등급은 지갑(points)과 무관하게 누적 획득량(lifetime_points)만으로
        // 산정 — 상위노출 등으로 포인트를 쓰다가 강등되는 문제 방지.
        if ($amount > 0) {
            $this->increment('lifetime_points', $amount);
        }
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
