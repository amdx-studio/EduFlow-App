<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\RemediationAssignment;
use App\Models\User;

/**
 * Ringkasan beranda per peran (API-42, FR-027, US-021). Angka perlu perhatian guru
 * memakai DashboardService agar sama dengan dashboard (KT-15, TC-059).
 */
class HomeService
{
    public const LIST_LIMIT = 10;

    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly GateService $gate,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        return $user->role === 'teacher' ? $this->teacher($user) : $this->student($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function teacher(User $user): array
    {
        $all = Course::visibleTo($user)
            ->withCount([
                'students',
                'quizzes as published_quizzes_count' => fn ($q) => $q->where('status', 'published'),
            ])
            ->get();

        $active = $all->reject(fn (Course $course) => (bool) $course->is_archived)->sortBy('title')->values();

        return [
            'role' => 'teacher',
            'courses_count' => $all->count(),
            'courses' => $active->map(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
                'students_count' => (int) $course->students_count,
                'published_quizzes_count' => (int) $course->published_quizzes_count,
                'attention_count' => $this->dashboard->attentionCount($course),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function student(User $user): array
    {
        $all = Course::visibleTo($user)->get();
        $courses = $all->reject(fn (Course $course) => (bool) $course->is_archived)->sortBy('title')->values()->keyBy('id');
        $courseRef = fn (int $id) => ['id' => $id, 'title' => $courses->get($id)->title];

        $running = QuizAttempt::where('student_id', $user->id)
            ->where('status', 'in_progress')
            ->orderBy('started_at')
            ->get();

        $quizzes = Quiz::whereIn('id', $running->pluck('quiz_id'))
            ->whereIn('course_id', $courses->keys())
            ->get()
            ->keyBy('id');

        $continue = $running
            ->filter(fn (QuizAttempt $attempt) => $quizzes->has($attempt->quiz_id))
            ->map(fn (QuizAttempt $attempt) => [
                'attempt_id' => $attempt->id,
                'attempt_no' => $attempt->attempt_no,
                'started_at' => $attempt->started_at?->toIso8601ZuluString(),
                'quiz' => ['id' => $attempt->quiz_id, 'title' => $quizzes->get($attempt->quiz_id)->title],
                'course' => $courseRef((int) $quizzes->get($attempt->quiz_id)->course_id),
            ])
            ->values()
            ->all();

        $remediation = RemediationAssignment::where('student_id', $user->id)
            ->where('status', 'assigned')
            ->with('lesson')
            ->get()
            ->groupBy(fn (RemediationAssignment $item) => $item->lesson->course_id)
            ->filter(fn ($group, $courseId) => $courses->has((int) $courseId))
            ->map(fn ($group, $courseId) => [
                'course' => $courseRef((int) $courseId),
                'pending_count' => $group->count(),
            ])
            ->sortBy(fn (array $row) => $row['course']['title'])
            ->values()
            ->all();

        $published = Quiz::whereIn('course_id', $courses->keys())
            ->where('status', 'published')
            ->orderBy('course_id')
            ->orderBy('id')
            ->get();

        $used = QuizAttempt::where('student_id', $user->id)
            ->whereIn('quiz_id', $published->pluck('id'))
            ->selectRaw('quiz_id, count(*) as total')
            ->groupBy('quiz_id')
            ->pluck('total', 'quiz_id');

        $runningQuizIds = $running->pluck('quiz_id')->all();
        $available = [];
        $locked = [];

        foreach ($published as $quiz) {
            if (in_array($quiz->id, $runningQuizIds, true)) {
                continue;
            }

            $quiz->setRelation('course', $courses->get($quiz->course_id));
            $gate = $this->gate->check($user, $quiz);

            $item = [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'kind' => $quiz->kind,
                'attempts_used' => (int) ($used->get($quiz->id) ?? 0),
                'max_attempts' => (int) $quiz->max_attempts,
                'course' => $courseRef((int) $quiz->course_id),
            ];

            if ($gate['status'] === 'available') {
                $available[] = $item;
            } else {
                $locked[] = $item + ['gate' => ['reason' => $gate['reason'], 'pending_count' => count($gate['pending'])]];
            }
        }

        return [
            'role' => 'student',
            'courses_count' => $all->count(),
            'continue' => $continue,
            'remediation' => $remediation,
            'quizzes' => [
                'available' => array_slice($available, 0, self::LIST_LIMIT),
                'available_total' => count($available),
                'locked' => array_slice($locked, 0, self::LIST_LIMIT),
                'locked_total' => count($locked),
            ],
        ];
    }
}
