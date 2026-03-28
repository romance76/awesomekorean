<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatRoom extends Model
{
    protected $fillable = [
        'name', 'slug', 'type', 'region', 'theme',
        'description', 'icon', 'is_open', 'max_members',
        'member_count', 'created_by',
    ];

    protected $casts = [
        'is_open' => 'boolean',
    ];

    public function messages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function latestMessages()
    {
        return $this->hasMany(ChatMessage::class)->latest()->limit(50);
    }
}
