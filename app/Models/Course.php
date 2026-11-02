<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['teacher_id', 'title', 'description', 'join_code', 'mastery_threshold', 'weak_threshold', 'min_questions_per_concept', 'is_archived'])]
class Course extends Model
{
    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_enrollments', 'course_id', 'student_id')->withPivot('enrolled_at');
    }

    public function concepts(): HasMany
    {
        return $this->hasMany(LearningConcept::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }
}
