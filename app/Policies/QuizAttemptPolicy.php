<?php

namespace App\Policies;

use App\Models\QuizAttempt;
use App\Models\User;

class QuizAttemptPolicy
{
    /** Menyimpan jawaban dan mengirim: hanya siswa pemilik percobaan. */
    public function answer(User $user, QuizAttempt $attempt): bool
    {
        return $user->id === $attempt->student_id;
    }
}
