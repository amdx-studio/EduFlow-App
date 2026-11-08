<?php

namespace App\Services;

use App\Models\AttemptConceptResult;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use App\Models\RemediationAssignment;
use Illuminate\Support\Collection;

/**
 * Rekomendasi remedial berbasis aturan (§8.3, BR-011/012).
 * Dipanggil AttemptService::submit di dalam transaksi yang sama, setelah MasteryService.
 */
class RemediationService
{
    /**
     * Buat assignment untuk konsep lemah pada percobaan terkirim.
     * Aman dipanggil ulang: UNIQUE (attempt, konsep, materi) dan firstOrCreate mencegah duplikat.
     *
     * @return Collection<int, RemediationAssignment> assignment baru maupun yang sudah ada
     */
    public function generate(QuizAttempt $attempt): Collection
    {
        $attempt->loadMissing('quiz');

        $courseId = (int) $attempt->quiz->course_id;
        $limit = (int) config('eduflow.remedial_per_concept');
        $assignments = collect();

        foreach ($this->weakResults($attempt) as $result) {
            $lessons = Lesson::where('course_id', $courseId)
                ->where('kind', 'remedial')
                ->where('is_published', true)
                ->whereHas('concepts', fn ($q) => $q->whereKey($result->concept_id))
                ->orderBy('position')
                ->orderBy('id')
                ->limit($limit)
                ->get();

            foreach ($lessons as $lesson) {
                $assignments->push(RemediationAssignment::firstOrCreate(
                    [
                        'attempt_id' => $attempt->id,
                        'concept_id' => $result->concept_id,
                        'lesson_id' => $lesson->id,
                    ],
                    [
                        'student_id' => $attempt->student_id,
                        'source' => 'rule',
                        'reason' => $this->reason($result, $lesson),
                        'status' => 'assigned',
                        'assigned_at' => now(),
                    ]
                ));
            }
        }

        return $assignments;
    }

    /**
     * Konsep lemah pada percobaan yang tidak punya satu pun assignment (US-013 kriteria 3).
     *
     * @return Collection<int, AttemptConceptResult>
     */
    public function gaps(QuizAttempt $attempt): Collection
    {
        $assigned = $attempt->remediationAssignments()->pluck('concept_id')->unique();

        return $this->weakResults($attempt)
            ->reject(fn ($result) => $assigned->contains($result->concept_id))
            ->values();
    }

    /**
     * Konsep developing dan not_mastered; not_mastered dahulu, lalu persen terendah.
     *
     * @return Collection<int, AttemptConceptResult>
     */
    private function weakResults(QuizAttempt $attempt): Collection
    {
        $attempt->load('conceptResults.concept');

        return $attempt->conceptResults
            ->whereIn('mastery_level', [MasteryService::NOT_MASTERED, MasteryService::DEVELOPING])
            ->sort(fn ($a, $b) => [$a->mastery_level !== MasteryService::NOT_MASTERED, (float) $a->mastery_percent, $a->concept_id]
                <=> [$b->mastery_level !== MasteryService::NOT_MASTERED, (float) $b->mastery_percent, $b->concept_id])
            ->values();
    }

    private function reason(AttemptConceptResult $result, Lesson $lesson): string
    {
        return sprintf(
            'Penguasaan konsep "%s" %s%% (%d/%d soal benar), di bawah ambang %d%%. Materi "%s" ditautkan ke konsep ini.',
            $result->concept->name,
            $this->formatPercent((float) $result->mastery_percent),
            $result->correct_count,
            $result->total_count,
            $result->mastery_threshold_used,
            $lesson->title,
        );
    }

    /** 50.0 menjadi "50", 66.67 menjadi "66,67", 66.5 menjadi "66,5". */
    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');
    }
}
