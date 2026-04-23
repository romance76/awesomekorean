<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    protected $fillable = [
        'game_slug','level','answer','wrong_answers','image_url','emoji_hex','hint','sound','is_active','sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'level'      => 'integer',
        'sort_order' => 'integer',
    ];

    public function scopeActive($q) { return $q->where('is_active', true); }

    public function scopeForGame($q, string $slug) { return $q->where('game_slug', $slug); }

    public function getResolvedImageAttribute(): string
    {
        if ($this->image_url) return $this->image_url;
        if ($this->emoji_hex) return "https://fonts.gstatic.com/s/e/notoemoji/latest/{$this->emoji_hex}/512.png";
        return '';
    }

    public function getWrongArrayAttribute(): array
    {
        if (!$this->wrong_answers) return [];
        return array_values(array_filter(array_map('trim', explode('|||', $this->wrong_answers))));
    }
}
