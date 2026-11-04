<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course_id', 'title', 'content', 'kind', 'position', 'is_published'])]
class Lesson extends Model
{
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function concepts(): BelongsToMany
    {
        return $this->belongsToMany(LearningConcept::class, 'concept_lesson', 'lesson_id', 'concept_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->whereHas('course', fn (Builder $q) => $q->visibleTo($user));

        if ($user->role !== 'teacher') {
            $query->where('is_published', true);
        }

        return $query;
    }
}
