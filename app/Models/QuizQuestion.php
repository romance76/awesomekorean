<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    protected $fillable = [
        'category', 'question', 'option_a', 'option_b',
        'option_c', 'option_d', 'correct_answer',
        'explanation', 'points', 'is_active', 'quiz_date',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'quiz_date' => 'date',
    ];

    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
