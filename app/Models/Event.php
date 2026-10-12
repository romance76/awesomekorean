<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Event extends Model
{
    protected $fillable = ['user_id','title','description','content','image_url','banner_image','banner_subtitle','banner_color','category','event_type','is_pinned','organizer','venue','address','city','state','zipcode','lat','lng','start_date','end_date','price','reward_points','reward_entries','is_free','url','event_url','source','source_id','view_count','attendee_count','max_attendees','is_active','is_online'];
    protected $casts = ['start_date'=>'datetime','end_date'=>'datetime','lat'=>'decimal:7','lng'=>'decimal:7','is_active'=>'boolean','is_free'=>'boolean','is_pinned'=>'boolean','is_online'=>'boolean'];
    // 완료 인증 보상 Entry 개수 (Kay 결정 2026-10-11): 만들 때 정한 개수, 정하지 않았으면 1개. 경품 추첨(sweepstakes) 이벤트는 0.
    protected $appends = ['completion_entries'];
    public function getCompletionEntriesAttribute(): int
    {
        if (($this->attributes['event_type'] ?? null) === 'sweepstakes') return 0;
        $v = $this->attributes['reward_entries'] ?? null;
        return $v === null ? 1 : max(0, (int) $v);
    }
    // 어썸코리안 이벤트·경품 이벤트가 만들어지면 SNS 대기열에 자동으로 (실패해도 이벤트 저장은 그대로)
    protected static function booted()
    {
        static::created(function (Event $ev) {
            try {
                if (in_array($ev->event_type, \App\Support\SocialQueue::EVENT_TYPES, true) && \App\Support\SocialQueue::settings()['auto_events']
                    && \Illuminate\Support\Facades\Schema::hasTable('social_posts')) {
                    \App\Support\SocialQueue::enqueueEvent($ev, auth()->id());
                }
            } catch (\Throwable $e) { report($e); }
        });
    }
    public function user() { return $this->belongsTo(User::class); }
    public function attendees() { return $this->hasMany(EventAttendee::class); }
    public function sweepstakes() { return $this->hasOne(Sweepstakes::class); }
    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeUpcoming($q) { return $q->where('start_date', '>=', now()); }
    public function scopeNearby($q,$lat,$lng,$r=50) { return $q->selectRaw("*, (3959*acos(cos(radians(?))*cos(radians(lat))*cos(radians(lng)-radians(?))+sin(radians(?))*sin(radians(lat)))) AS distance",[$lat,$lng,$lat])->having('distance','<',$r); }
}
