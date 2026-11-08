<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\RemediationAssignment;
use App\Models\User;

/**
 * Gate kuis bagi siswa (§8.5, BR-013, KT-14).
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

        if ($hasActive || $quiz->kind !== 'reassessment') {
            return ['status' => 'available', 'reason' => null, 'pending' => []];
        }

        return $this->reassessmentGate($student, $quiz);
    }

    /**
     * @return array{status: string, reason: string, pending: array<int, mixed>}
     */
    private function locked(string $reason, array $pending = []): array
    {
        return ['status' => 'locked', 'reason' => $reason, 'pending' => $pending];
    }

    /**
     * Gate asesmen ulang (BR-013): butuh percobaan dasar tersubmit dan remedial
     * dari percobaan dasar terakhir sudah selesai.
     *
     * @return array{status: string, reason: ?string, pending: array<int, mixed>}
     */
    private function reassessmentGate(User $student, Quiz $quiz): array
    {
        $lastBase = QuizAttempt::where('quiz_id', $quiz->base_quiz_id)
            ->where('student_id', $student->id)
            ->where('status', 'submitted')
            ->orderByDesc('attempt_no')
            ->first();

        if (! $lastBase) {
            return $this->locked('Kerjakan kuis dasar terlebih dahulu.');
        }

        $pending = RemediationAssignment::with(['concept', 'lesson'])
            ->where('attempt_id', $lastBase->id)
            ->where('status', 'assigned')
            ->orderBy('concept_id')
            ->orderBy('id')
            ->get()
            ->map(fn (RemediationAssignment $a) => [
                'id' => $a->id,
                'concept' => $a->concept->name,
                'lesson' => ['id' => $a->lesson->id, 'title' => $a->lesson->title],
            ])
            ->all();

        if ($pending !== []) {
            return $this->locked('Selesaikan remedial.', $pending);
        }

        return ['status' => 'available', 'reason' => null, 'pending' => []];
    }
}
