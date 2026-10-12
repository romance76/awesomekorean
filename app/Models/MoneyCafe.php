<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// MONEY 허브의 분야별 정보 공유 카페 (가입 없이 보기·답글, 새 글은 로그인)
class MoneyCafe extends Model
{
    protected $fillable = ['slug', 'name', 'icon', 'summary', 'headline', 'theme', 'art', 'first_steps', 'intro', 'sort_order', 'is_active', 'manager_user_id'];

    protected $casts = ['is_active' => 'boolean', 'first_steps' => 'array'];

    public function posts()
    {
        return $this->hasMany(MoneyPost::class, 'cafe_id');
    }

    public function categories()
    {
        return $this->hasMany(MoneyCategory::class, 'cafe_id')->orderBy('sort_order')->orderBy('id');
    }
}
