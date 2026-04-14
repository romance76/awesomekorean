<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resume extends Model
{
    protected $fillable = [
        'user_id', 'title', 'name', 'phone', 'email', 'summary',
        'category', 'desired_type', 'desired_salary', 'desired_salary_type',
        'city', 'state', 'lat', 'lng',
        'experience', 'education', 'skills', 'certifications',
        'is_public', 'is_active', 'view_count',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopePublic($q) { return $q->where('is_public', true); }
}
