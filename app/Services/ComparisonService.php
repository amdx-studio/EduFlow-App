<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;

/**
 * Perbandingan sebelum-sesudah (BR-014, BR-017).
 */
class ComparisonService
{
    public const NOTE = 'Perubahan skor, bukan bukti sebab-akibat.';

    /**
     * @return array{status: string, note: string, concepts: array<int, array<string, mixed>>}
     */
    public function compare(User $student, Quiz $reassessment): array
    {
        $before = $this->lastSubmitted($student, (int) $reassessment->base_quiz_id);
        $after = $this->lastSubmitted($student, (int) $reassessment->id);

        if ($before === null || $after === null) {
            return ['status' => 'incomplete', 'note' => self::NOTE, 'concepts' => []];
        }

        $beforeResults = $before->conceptResults->keyBy('concept_id');
        $threshold = (float) config('eduflow.comparison_delta', 10);
        $concepts = [];

        foreach ($after->conceptResults->sortBy('concept_id') as $afterResult) {
            $beforeResult = $beforeResults->get($afterResult->concept_id);

            if ($beforeResult === null
                || $beforeResult->mastery_level === MasteryService::INSUFFICIENT
                || $afterResult->mastery_level === MasteryService::INSUFFICIENT) {
                continue;
            }

            $b = (float) $beforeResult->mastery_percent;
            $a = (float) $afterResult->mastery_percent;
            $delta = round($a - $b, 2);

            $concepts[] = [
                'concept_id' => (int) $afterResult->concept_id,
                'concept' => $afterResult->concept->name,
                'before' => $b,
                'after' => $a,
                'delta' => $delta,
                'trend' => self::trend($delta, $threshold),
                'reached_mastery' => $afterResult->mastery_level === MasteryService::MASTERED,
            ];
        }

        return ['status' => 'complete', 'note' => self::NOTE, 'concepts' => $concepts];
    }

    /** BR-014: selisih >= +delta meningkat, <= -delta menurun, selain itu tetap. */
    public static function trend(float $delta, float $threshold): string
    {
        return $delta >= $threshold ? 'improved' : ($delta <= -$threshold ? 'declined' : 'unchanged');
    }

    private function lastSubmitted(User $student, int $quizId): ?QuizAttempt
    {
        return QuizAttempt::where('quiz_id', $quizId)
            ->where('student_id', $student->id)
            ->where('status', 'submitted')
            ->orderByDesc('attempt_no')
            ->with('conceptResults.concept')
            ->first();
    }
}
