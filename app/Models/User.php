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
        'name', 'username', 'email', 'password',
        'phone', 'avatar', 'region', 'address', 'lat', 'lng',
        'level', 'points_total', 'cash_balance',
        'nickname', 'is_elder', 'is_driver', 'lang', 'status', 'is_admin', 'bio',
        'address2', 'city', 'state', 'zip_code', 'payment_method', 'payment_last4',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_elder' => 'boolean',
            'is_driver' => 'boolean',
            'is_admin' => 'boolean',
        ];
    }

    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() { return []; }

    public function posts() { return $this->hasMany(Post::class); }
    public function comments() { return $this->hasMany(Comment::class); }
    public function pointLogs() { return $this->hasMany(PointLog::class); }
    public function sentMessages() { return $this->hasMany(Message::class, 'sender_id'); }
    public function receivedMessages() { return $this->hasMany(Message::class, 'receiver_id'); }
    public function checkins() { return $this->hasMany(Checkin::class); }
    public function businesses() { return $this->hasMany(Business::class, 'owner_id'); }

    public function addPoints(int $amount, string $action, string $type = 'earn', ?int $refId = null, ?string $memo = null): void {
        $this->increment('points_total', $amount);
        $this->refresh();
        PointLog::create([
            'user_id' => $this->id,
            'type' => $type,
            'action' => $action,
            'amount' => $amount,
            'balance_after' => $this->points_total,
            'ref_id' => $refId,
            'memo' => $memo,
        ]);
        // 레벨 업데이트
        $this->updateLevel();
    }

    public function updateLevel(): void {
        $points = $this->points_total;
        $level = match(true) {
            $points >= 50000 => '참나무',
            $points >= 20000 => '숲',
            $points >= 5000  => '나무',
            $points >= 1000  => '새싹',
            default          => '씨앗',
        };
        if ($this->level !== $level) {
            $this->update(['level' => $level]);
        }
    }
}
