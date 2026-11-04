<?php

namespace App\Policies;

use App\Models\LearningConcept;
use App\Models\User;

class LearningConceptPolicy
{
    public function update(User $user, LearningConcept $concept): bool
    {
        return $user->id === $concept->course->teacher_id;
    }

    public function delete(User $user, LearningConcept $concept): bool
    {
        return $user->id === $concept->course->teacher_id;
    }
}
