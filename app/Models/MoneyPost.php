<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// 머니 카페 글 — kind: info(정보) · question(질문) · talk(소통)
class MoneyPost extends Model
{
    public const KINDS = ['info', 'question', 'talk'];

    protected $fillable = ['cafe_id', 'category_id', 'user_id', 'kind', 'title', 'content'];

    protected $casts = ['is_hidden' => 'boolean', 'is_pinned' => 'boolean', 'blocks' => 'array'];

    public function cafe()
    {
        return $this->belongsTo(MoneyCafe::class, 'cafe_id');
    }

    public function category()
    {
        return $this->belongsTo(MoneyCategory::class, 'category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
