<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'nickname', 'email', 'password', 'phone',
        'address', 'city', 'state', 'zipcode', 'latitude', 'longitude',
        'avatar', 'bio', 'language', 'points', 'game_points',
        'role', 'is_banned', 'ban_reason', 'last_login_at', 'login_count',
        'provider', 'provider_id',
    ];

    protected $hidden = ['password', 'remember_token'];

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
        ];
    }

    // JWT
    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() { return []; }

    // Accessors
    public function getIsAdminAttribute(): bool { return $this->role === 'admin'; }

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

    // Helper
    public function addPoints(int $amount, string $reason, string $type = 'earn')
    {
        $this->increment('points', $amount);
        $this->pointLogs()->create([
            'amount' => $amount,
            'type' => $type,
            'reason' => $reason,
            'balance_after' => $this->fresh()->points,
        ]);
    }
}
