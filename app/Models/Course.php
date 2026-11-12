<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['teacher_id', 'title', 'description', 'join_code', 'mastery_threshold', 'weak_threshold', 'min_questions_per_concept', 'is_archived'])]
class Course extends Model
{
    public const JOIN_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

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

    public function forumThreads(): HasMany
    {
        return $this->hasMany(ForumThread::class);
    }

    /** Guru: kelas miliknya. Siswa: kelas yang diikuti. Selain itu tidak terlihat (404). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->role === 'teacher'
            ? $query->where('teacher_id', $user->id)
            : $query->whereHas('students', fn ($q) => $q->where('users.id', $user->id));
    }

    /** BR-002: 8 karakter, unik, tanpa karakter ambigu. */
    public static function generateJoinCode(): string
    {
        $max = strlen(self::JOIN_CODE_ALPHABET) - 1;

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::JOIN_CODE_ALPHABET[random_int(0, $max)];
            }
        } while (static::where('join_code', $code)->exists());

        return $code;
    }
}
