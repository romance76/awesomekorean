<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// 머니 카페 주제(카테고리) — 대문의 주제 카드이자 게시판 글 나누기 기준
class MoneyCategory extends Model
{
    protected $fillable = ['cafe_id', 'name', 'icon', 'hint', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function cafe()
    {
        return $this->belongsTo(MoneyCafe::class, 'cafe_id');
    }
}
