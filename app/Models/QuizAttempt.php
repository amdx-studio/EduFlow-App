<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['quiz_id', 'student_id', 'attempt_no', 'status', 'started_at', 'submitted_at', 'earned_points', 'possible_points', 'score_percent'])]
class QuizAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score_percent' => 'decimal:2',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'attempt_id');
    }

    public function conceptResults(): HasMany
    {
        return $this->hasMany(AttemptConceptResult::class, 'attempt_id');
    }

    public function remediationAssignments(): HasMany
    {
        return $this->hasMany(RemediationAssignment::class, 'attempt_id');
    }
}
