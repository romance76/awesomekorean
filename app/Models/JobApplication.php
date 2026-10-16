<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    protected $fillable = [
        'job_post_id', 'user_id', 'resume_id', 'resume_snapshot', 'message', 'status',
    ];

    protected $casts = ['resume_snapshot' => 'array'];

    public function jobPost() { return $this->belongsTo(JobPost::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function resume() { return $this->belongsTo(Resume::class); }
}
