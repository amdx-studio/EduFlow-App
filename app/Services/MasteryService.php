<?php

namespace App\Services;

use App\Models\QuizAttempt;

/**
 * Penguasaan per konsep per percobaan (§8.2, BR-009/010).
 */
class MasteryService
{
    public const MASTERED = 'mastered';

    public const DEVELOPING = 'developing';

    public const NOT_MASTERED = 'not_mastered';

    public const INSUFFICIENT = 'insufficient';

    /**
     * Klasifikasi dari nilai MENTAH (bukan yang dibulatkan).
     * Bukti tidak cukup (jumlah soal < minimum) diperiksa lebih dulu dan tidak memicu remedial.
     */
    public static function classify(
        float|int $raw,
        int $questionCount,
        int $minQuestions,
        int $masteryThreshold,
        int $weakThreshold,
    ): string {
        if ($questionCount < $minQuestions) {
            return self::INSUFFICIENT;
        }

        if ($raw >= $masteryThreshold) {
            return self::MASTERED;
        }

        if ($raw >= $weakThreshold) {
            return self::DEVELOPING;
        }

        return self::NOT_MASTERED;
    }

    /**
     * Persentase mentah. Dikali 100 lebih dulu agar hasil bulat tidak meleset karena pecahan biner
     * (mis. 7 dari 10 poin tepat 70.0).
     */
    public static function rawPercent(int $earned, int $possible): float
    {
        return $possible > 0 ? (float) ($earned * 100 / $possible) : 0.0;
    }

    /**
     * Hitung dan simpan hasil per konsep untuk satu percobaan.
     * Dipanggil setelah jawaban dinilai. Aman dipanggil ulang (updateOrCreate).
     */
    public function compute(QuizAttempt $attempt): void
    {
        $attempt->loadMissing(['quiz.course', 'quiz.questions', 'answers']);

        $course = $attempt->quiz->course;
        $answers = $attempt->answers->keyBy('question_id');

        foreach ($attempt->quiz->questions->groupBy('concept_id') as $conceptId => $questions) {
            $possible = (int) $questions->sum('points');
            $earned = 0;
            $correct = 0;

            foreach ($questions as $question) {
                $answer = $answers->get($question->id);
                $earned += (int) ($answer?->points_awarded ?? 0);
                $correct += ($answer?->is_correct ?? false) ? 1 : 0;
            }

            $raw = self::rawPercent($earned, $possible);

            $level = self::classify(
                $raw,
                $questions->count(),
                (int) $course->min_questions_per_concept,
                (int) $course->mastery_threshold,
                (int) $course->weak_threshold,
            );

            $attempt->conceptResults()->updateOrCreate(
                ['concept_id' => (int) $conceptId],
                [
                    'correct_count' => $correct,
                    'total_count' => $questions->count(),
                    'earned_points' => $earned,
                    'possible_points' => $possible,
                    'mastery_percent' => round($raw, 2),
                    'mastery_level' => $level,
                    // Snapshot ambang (NFR-009): perubahan ambang kelas tidak mengubah hasil lama.
                    'mastery_threshold_used' => (int) $course->mastery_threshold,
                    'weak_threshold_used' => (int) $course->weak_threshold,
                ]
            );
        }
    }
}
