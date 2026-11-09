<?php

namespace App\Services;

use App\Models\AttemptConceptResult;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\RemediationAssignment;
use App\Models\User;

/**
 * Detail satu siswa untuk guru (API-41, FR-020).
 */
class StudentDetailService
{
    public function __construct(private readonly ComparisonService $comparison) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Course $course, User $student): array
    {
        $attempts = QuizAttempt::query()
            ->whereHas('quiz', fn ($q) => $q->where('course_id', $course->id))
            ->where('student_id', $student->id)
            ->where('status', 'submitted')
            ->with(['quiz:id,title,kind', 'conceptResults.concept'])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        $latest = $attempts->groupBy('quiz_id')
            ->map(fn ($group) => $group->sortByDesc('attempt_no')->first())
            ->sortKeys()
            ->values();

        $assignments = RemediationAssignment::query()
            ->where('student_id', $student->id)
            ->whereHas('lesson', fn ($q) => $q->where('course_id', $course->id))
            ->with(['concept', 'lesson'])
            ->orderBy('id')
            ->get();

        $percents = AttemptConceptResult::whereIn('attempt_id', $assignments->pluck('attempt_id')->unique())
            ->get()
            ->mapWithKeys(fn ($r) => ["{$r->attempt_id}:{$r->concept_id}" => (float) $r->mastery_percent]);

        $comparisons = Quiz::where('course_id', $course->id)
            ->where('kind', 'reassessment')
            ->where('status', 'published')
            ->orderBy('id')
            ->get()
            ->map(fn (Quiz $q) => ['quiz' => ['id' => $q->id, 'title' => $q->title]] + $this->comparison->compare($student, $q))
            ->all();

        return [
            'student' => ['id' => $student->id, 'name' => $student->name, 'email' => $student->email],
            'attempts' => $attempts->map(fn (QuizAttempt $a) => [
                'id' => $a->id,
                'quiz' => ['id' => $a->quiz->id, 'title' => $a->quiz->title, 'kind' => $a->quiz->kind],
                'attempt_no' => $a->attempt_no,
                'score_percent' => (float) $a->score_percent,
                'earned_points' => $a->earned_points,
                'possible_points' => $a->possible_points,
                'submitted_at' => $a->submitted_at?->toIso8601String(),
            ])->all(),
            'latest_concepts' => $latest->map(fn (QuizAttempt $a) => [
                'quiz' => ['id' => $a->quiz->id, 'title' => $a->quiz->title],
                'attempt_id' => $a->id,
                'concepts' => $a->conceptResults->sortBy('concept_id')->map(fn ($r) => [
                    'concept_id' => (int) $r->concept_id,
                    'concept' => $r->concept->name,
                    'correct' => $r->correct_count,
                    'total' => $r->total_count,
                    'percent' => (float) $r->mastery_percent,
                    'level' => $r->mastery_level,
                ])->values()->all(),
            ])->all(),
            'remediation' => $assignments->map(fn (RemediationAssignment $r) => [
                'id' => $r->id,
                'attempt_id' => $r->attempt_id,
                'concept_id' => $r->concept_id,
                'concept' => $r->concept->name,
                'mastery_percent' => $percents->get("{$r->attempt_id}:{$r->concept_id}"),
                'lesson' => ['id' => $r->lesson->id, 'title' => $r->lesson->title],
                'reason' => $r->reason,
                'source' => $r->source,
                'status' => $r->status,
                'note' => $r->note,
                'assigned_at' => $r->assigned_at?->toIso8601String(),
                'completed_at' => $r->completed_at?->toIso8601String(),
            ])->all(),
            'comparisons' => $comparisons,
        ];
    }
}
