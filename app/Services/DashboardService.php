<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\RemediationAssignment;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard kelas per kuis dasar (§8.7, KT-15). Semua angka memakai percobaan
 * tersubmit TERAKHIR tiap siswa terdaftar.
 */
class DashboardService
{
    public function __construct(private readonly ComparisonService $comparison) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Course $course, ?int $quizId): array
    {
        $quizzes = Quiz::where('course_id', $course->id)
            ->where('kind', 'assessment')
            ->where('status', 'published')
            ->orderBy('id')
            ->get(['id', 'title'])
            ->map(fn (Quiz $q) => ['id' => $q->id, 'title' => $q->title])
            ->all();

        $students = $course->students()->orderBy('users.name')->get(['users.id', 'users.name'])->keyBy('id');
        $quiz = $this->selectQuiz($course, $quizId);

        if ($quiz === null) {
            return [
                'quiz' => null,
                'quizzes' => $quizzes,
                'totals' => $this->totals($students->count(), 0, 0, 0),
                'concepts' => [],
                'attention' => [],
                'resource_gaps' => [],
                'comparison' => [],
            ];
        }

        $latest = $this->latestSubmitted($quiz, $students);

        $rows = $latest->flatMap(fn (QuizAttempt $a) => $a->conceptResults);

        $statuses = RemediationAssignment::whereIn('attempt_id', $latest->pluck('id'))->pluck('status');

        return [
            'quiz' => ['id' => $quiz->id, 'title' => $quiz->title],
            'quizzes' => $quizzes,
            'totals' => $this->totals(
                $students->count(),
                $latest->count(),
                $statuses->count(),
                $statuses->where(fn ($s) => $s === 'completed')->count()
            ),
            'concepts' => $this->concepts($rows),
            'attention' => $this->attention($latest, $students),
            'resource_gaps' => $this->resourceGaps($course, $rows),
            'comparison' => $this->comparisons($course, $quiz, $students),
        ];
    }

    /**
     * Jumlah siswa perlu perhatian pada kuis dasar bawaan kelas. Aturannya sama dengan
     * dashboard karena memakai latestSubmitted() dan attention() yang sama (KT-15).
     */
    public function attentionCount(Course $course): int
    {
        $quiz = $this->selectQuiz($course, null);

        if ($quiz === null) {
            return 0;
        }

        $students = $course->students()->orderBy('users.name')->get(['users.id', 'users.name'])->keyBy('id');

        return count($this->attention($this->latestSubmitted($quiz, $students), $students));
    }

    /**
     * Percobaan tersubmit terakhir tiap siswa terdaftar pada satu kuis.
     */
    private function latestSubmitted(Quiz $quiz, $students)
    {
        return QuizAttempt::where('quiz_id', $quiz->id)
            ->where('status', 'submitted')
            ->whereIn('student_id', $students->keys())
            ->with('conceptResults.concept')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($group) => $group->sortByDesc('attempt_no')->first())
            ->values();
    }

    private function selectQuiz(Course $course, ?int $quizId): ?Quiz
    {
        $base = Quiz::where('course_id', $course->id)->where('kind', 'assessment');

        if ($quizId !== null) {
            return $base->findOrFail($quizId);
        }

        return $base
            ->whereHas('attempts', fn ($q) => $q->where('status', 'submitted'))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function totals(int $enrolled, int $submitted, int $assignments, int $completed): array
    {
        return [
            'students_enrolled' => $enrolled,
            'students_submitted' => $submitted,
            'assignments_total' => $assignments,
            'assignments_completed' => $completed,
            'assignments_completed_percent' => $assignments > 0 ? round($completed * 100 / $assignments, 1) : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function concepts($rows): array
    {
        return $rows->groupBy('concept_id')
            ->map(function ($group, $conceptId) {
                $scored = $group->where('mastery_level', '!=', MasteryService::INSUFFICIENT);

                return [
                    'concept_id' => (int) $conceptId,
                    'concept' => $group->first()->concept->name,
                    'average_percent' => $scored->isEmpty() ? null : round($scored->avg(fn ($r) => (float) $r->mastery_percent), 2),
                    'students_count' => $scored->count(),
                    'levels' => [
                        'mastered' => $group->where('mastery_level', MasteryService::MASTERED)->count(),
                        'developing' => $group->where('mastery_level', MasteryService::DEVELOPING)->count(),
                        'not_mastered' => $group->where('mastery_level', MasteryService::NOT_MASTERED)->count(),
                        'insufficient' => $group->where('mastery_level', MasteryService::INSUFFICIENT)->count(),
                    ],
                ];
            })
            ->sortKeys()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attention($latest, $students): array
    {
        return $latest
            ->map(function (QuizAttempt $attempt) use ($students) {
                $weak = $attempt->conceptResults->where('mastery_level', MasteryService::NOT_MASTERED);

                if ($weak->isEmpty()) {
                    return null;
                }

                return [
                    'student' => ['id' => $attempt->student_id, 'name' => $students->get($attempt->student_id)?->name],
                    'attempt_id' => $attempt->id,
                    'concepts' => $weak->sortBy(fn ($r) => (float) $r->mastery_percent)
                        ->map(fn ($r) => [
                            'concept_id' => (int) $r->concept_id,
                            'concept' => $r->concept->name,
                            'percent' => (float) $r->mastery_percent,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->sortBy(fn ($entry) => $entry['student']['name'])
            ->values()
            ->all();
    }

    /**
     * Konsep lemah (developing atau not_mastered) pada >= 1 siswa yang tidak
     * punya materi remedial terbit di kelas ini (FR-022).
     *
     * @return array<int, array<string, mixed>>
     */
    private function resourceGaps(Course $course, $rows): array
    {
        $weak = $rows->whereIn('mastery_level', [MasteryService::DEVELOPING, MasteryService::NOT_MASTERED]);

        if ($weak->isEmpty()) {
            return [];
        }

        $covered = DB::table('concept_lesson')
            ->join('lessons', 'lessons.id', '=', 'concept_lesson.lesson_id')
            ->where('lessons.course_id', $course->id)
            ->where('lessons.kind', 'remedial')
            ->where('lessons.is_published', true)
            ->whereIn('concept_lesson.concept_id', $weak->pluck('concept_id')->unique())
            ->pluck('concept_lesson.concept_id')
            ->unique();

        return $weak->groupBy('concept_id')
            ->reject(fn ($group, $conceptId) => $covered->contains($conceptId))
            ->map(fn ($group, $conceptId) => [
                'concept_id' => (int) $conceptId,
                'concept' => $group->first()->concept->name,
                'students_count' => $group->count(),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    /**
     * Rata-rata sebelum, sesudah, selisih per konsep untuk tiap asesmen ulang
     * terbit berbasis kuis ini (siswa dengan perbandingan lengkap saja).
     *
     * @return array<int, array<string, mixed>>
     */
    private function comparisons(Course $course, Quiz $quiz, $students): array
    {
        return Quiz::where('course_id', $course->id)
            ->where('kind', 'reassessment')
            ->where('base_quiz_id', $quiz->id)
            ->where('status', 'published')
            ->orderBy('id')
            ->get()
            ->map(function (Quiz $reassessment) use ($students) {
                $perConcept = [];
                $complete = 0;

                foreach ($students as $student) {
                    $result = $this->comparison->compare($student, $reassessment);

                    if ($result['status'] !== 'complete') {
                        continue;
                    }

                    $complete++;

                    foreach ($result['concepts'] as $row) {
                        $perConcept[$row['concept_id']]['concept'] = $row['concept'];
                        $perConcept[$row['concept_id']]['before'][] = $row['before'];
                        $perConcept[$row['concept_id']]['after'][] = $row['after'];
                    }
                }

                ksort($perConcept);

                return [
                    'quiz' => ['id' => $reassessment->id, 'title' => $reassessment->title],
                    'students_count' => $complete,
                    'note' => ComparisonService::NOTE,
                    'concepts' => collect($perConcept)->map(function ($data, $conceptId) {
                        $before = array_sum($data['before']) / count($data['before']);
                        $after = array_sum($data['after']) / count($data['after']);

                        return [
                            'concept_id' => (int) $conceptId,
                            'concept' => $data['concept'],
                            'before' => round($before, 2),
                            'after' => round($after, 2),
                            'delta' => round($after - $before, 2),
                            'students_count' => count($data['before']),
                        ];
                    })->values()->all(),
                ];
            })
            ->all();
    }
}
