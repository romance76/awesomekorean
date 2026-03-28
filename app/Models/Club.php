<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Club extends Model
{
    protected $fillable = [
        'creator_id',
        'name',
        'category',
        'description',
        'region',
        'cover_image',
        'is_approval',
        'member_count',
        'address',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'is_approval' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function members()
    {
        return $this->hasMany(ClubMember::class);
    }

    public function approvedMembers()
    {
        return $this->hasMany(ClubMember::class)->where('status', 'approved');
    }
}
