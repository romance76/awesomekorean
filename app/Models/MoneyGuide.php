<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// 머니 가이드 — 운영자가 쓰는 설명 페이지 (게시판 아님)
class MoneyGuide extends Model
{
    protected $fillable = ['slug', 'cafe_id', 'icon', 'title', 'summary', 'content', 'blocks', 'is_published', 'sort_order'];

    protected $casts = ['is_published' => 'boolean', 'blocks' => 'array'];
}
