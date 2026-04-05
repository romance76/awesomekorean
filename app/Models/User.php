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
        'name', 'email', 'password',
        'nickname', 'phone', 'address', 'city', 'state', 'zipcode',
        'latitude', 'longitude', 'avatar', 'bio', 'language',
        'points', 'game_points', 'role',
        'is_banned', 'ban_reason', 'last_login_at', 'login_count',
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

    // ── JWT ──────────────────────────────────────────────────────
    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() { return []; }

    // ── Scopes ───────────────────────────────────────────────────
    public function scopeNearby($query, float $lat, float $lng, float $radiusMiles = 25)
    {
        $haversine = "(3959 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";
        return $query->selectRaw("*, {$haversine} AS distance", [$lat, $lng, $lat])
                     ->havingRaw("distance <= ?", [$radiusMiles])
                     ->orderBy('distance');
    }

    // ── Relationships ────────────────────────────────────────────
    public function posts()           { return $this->hasMany(Post::class); }
    public function comments()        { return $this->hasMany(Comment::class); }
    public function jobPosts()        { return $this->hasMany(JobPost::class); }
    public function marketItems()     { return $this->hasMany(MarketItem::class); }
    public function businesses()      { return $this->hasMany(Business::class, 'owner_id'); }
    public function clubs()           { return $this->hasMany(Club::class); }
    public function clubMemberships() { return $this->hasMany(ClubMember::class); }
    public function events()          { return $this->hasMany(EventAttendee::class); }
    public function qaPosts()         { return $this->hasMany(QaPost::class); }
    public function qaAnswers()       { return $this->hasMany(QaAnswer::class); }
    public function recipePosts()     { return $this->hasMany(RecipePost::class); }
    public function shorts()          { return $this->hasMany(Short::class); }
    public function pointLogs()       { return $this->hasMany(PointLog::class); }
    public function notifications()   { return $this->hasMany(Notification::class); }
    public function bookmarks()       { return $this->hasMany(Bookmark::class); }
    public function chatRooms()       { return $this->belongsToMany(ChatRoom::class, 'chat_room_users')->withPivot('last_read_at'); }
    public function playlists()       { return $this->hasMany(UserPlaylist::class); }
    public function elderSetting()    { return $this->hasOne(ElderSetting::class); }
    public function friendsOf()       { return $this->hasMany(Friend::class, 'user_id'); }
    public function friendRequests()  { return $this->hasMany(Friend::class, 'friend_id'); }
    public function gameRooms()       { return $this->hasMany(GameRoom::class, 'host_id'); }
    public function payments()        { return $this->hasMany(Payment::class); }
    public function realEstateListings() { return $this->hasMany(RealEstateListing::class); }
    public function groupBuys()       { return $this->hasMany(GroupBuy::class); }

    // ── Accessors ───────────────────────────────────────────────
    public function getIsAdminAttribute(): bool { return $this->role === 'admin'; }

    // ── Helpers ──────────────────────────────────────────────────
    public function isAdmin(): bool   { return $this->role === 'admin'; }
    public function isGuardian(): bool { return $this->role === 'guardian'; }

    public function addPoints(int $amount, string $reason, string $type = 'earn', ?string $relatedType = null, ?int $relatedId = null): void
    {
        $this->increment('points', $amount);
        $this->refresh();
        PointLog::create([
            'user_id'       => $this->id,
            'amount'        => $amount,
            'type'          => $type,
            'reason'        => $reason,
            'related_type'  => $relatedType,
            'related_id'    => $relatedId,
            'balance_after' => $this->points,
        ]);
    }
}
