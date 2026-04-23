<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PopupBanner extends Model
{
    protected $fillable = [
        'title','type','image_url','content','width','height',
        'link_url','display_mode','is_active','start_at','end_at','sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'width'     => 'integer',
        'height'    => 'integer',
        'sort_order'=> 'integer',
        'start_at'  => 'datetime',
        'end_at'    => 'datetime',
    ];

    /** 현재 시각에 게시 중인 활성 배너 */
    public function scopeActiveNow($q)
    {
        $now = now();
        return $q->where('is_active', true)
            ->where(function ($q2) use ($now) {
                $q2->whereNull('start_at')->orWhere('start_at','<=',$now);
            })
            ->where(function ($q2) use ($now) {
                $q2->whereNull('end_at')->orWhere('end_at','>=',$now);
            });
    }
}
