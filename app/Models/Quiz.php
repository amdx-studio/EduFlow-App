<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course_id', 'title', 'kind', 'base_quiz_id', 'max_attempts', 'status', 'published_at'])]
class Quiz extends Model
{
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function baseQuiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'base_quiz_id');
    }

    public function reassessments(): HasMany
    {
        return $this->hasMany(Quiz::class, 'base_quiz_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
