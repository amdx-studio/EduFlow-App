<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function update(User $user, Course $course): bool
    {
        return $user->id === $course->teacher_id;
    }

    public function archive(User $user, Course $course): bool
    {
        return $user->id === $course->teacher_id;
    }
}
