<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;

/**
 * Gate kuis bagi siswa (§8.5, BR-013, KT-14).
 * Cabang asesmen ulang (kuis dasar, remedial tertunda) ditambahkan di Phase 8.
 */
class GateService
{
    /**
     * @return array{status: string, reason: ?string, pending: array<int, mixed>}
     */
    public function check(User $student, Quiz $quiz): array
    {
        $quiz->loadMissing('course');

        if ($quiz->course->is_archived) {
            return $this->locked('Kelas diarsipkan.');
        }

        $attempts = QuizAttempt::where('quiz_id', $quiz->id)->where('student_id', $student->id);
        $hasActive = (clone $attempts)->where('status', 'in_progress')->exists();

        if (! $hasActive && (clone $attempts)->count() >= $quiz->max_attempts) {
            return $this->locked('Batas percobaan tercapai.');
        }

        return ['status' => 'available', 'reason' => null, 'pending' => []];
    }

    /**
     * @return array{status: string, reason: string, pending: array<int, mixed>}
     */
    private function locked(string $reason, array $pending = []): array
    {
        return ['status' => 'locked', 'reason' => $reason, 'pending' => $pending];
    }
}
