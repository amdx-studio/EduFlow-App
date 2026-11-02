<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attempt_id', 'student_id', 'concept_id', 'lesson_id', 'source', 'reason', 'status', 'assigned_by', 'note', 'assigned_at', 'completed_at'])]
class RemediationAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(LearningConcept::class, 'concept_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
