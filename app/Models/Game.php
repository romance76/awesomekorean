<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    protected $fillable = [
        'slug','name','description','icon','category','group_slug','path','is_active','sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /** 게임별 커스텀 설정 (game_settings 테이블 기존 것 재사용) */
    public function settings()
    {
        return $this->hasMany(GameSetting::class, 'game_type', 'slug');
    }
}
