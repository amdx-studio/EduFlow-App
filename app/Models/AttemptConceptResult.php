<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attempt_id', 'concept_id', 'correct_count', 'total_count', 'earned_points', 'possible_points', 'mastery_percent', 'mastery_level', 'mastery_threshold_used', 'weak_threshold_used'])]
class AttemptConceptResult extends Model
{
    protected function casts(): array
    {
        return [
            'mastery_percent' => 'decimal:2',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    public function concept(): BelongsTo
    {
        return $this->belongsTo(LearningConcept::class, 'concept_id');
    }
}
