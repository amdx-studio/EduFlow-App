<?php

namespace Tests\Concerns;

use App\Models\Course;
use App\Models\LearningConcept;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\AttemptService;
use Illuminate\Testing\TestResponse;

trait BuildsAttemptData
{
    private function signIn(User $user): void
    {
        // Guard sanctum menyimpan user antar request dalam satu test; reset agar pergantian user nyata.
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'web');
    }

    private function teacher(): User
    {
        return User::factory()->create(['role' => 'teacher']);
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'student']);
    }

    private function course(User $teacher, array $extra = []): Course
    {
        return Course::create(array_merge([
            'teacher_id' => $teacher->id,
            'title' => 'Matematika',
            'join_code' => Course::generateJoinCode(),
        ], $extra))->refresh();
    }

    private function enroll(Course $course, User $student): void
    {
        $course->students()->attach($student->id, ['enrolled_at' => now()]);
    }

    private function concept(Course $course, string $name = 'Pecahan'): LearningConcept
    {
        return LearningConcept::create(['course_id' => $course->id, 'name' => $name]);
    }

    private function quiz(Course $course, array $extra = []): Quiz
    {
        return Quiz::create(array_merge([
            'course_id' => $course->id,
            'title' => 'Kuis 1',
            'kind' => 'assessment',
            'max_attempts' => 2,
            'status' => 'published',
            'published_at' => now(),
        ], $extra))->refresh();
    }

    /** Soal dengan dua opsi: "Benar" (kunci) dan "Salah". */
    private function question(Quiz $quiz, LearningConcept $concept, int $position, int $points = 1): Question
    {
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'concept_id' => $concept->id,
            'body' => "Soal {$position}",
            'points' => $points,
            'explanation' => "Penjelasan {$position}",
            'position' => $position,
        ]);

        QuestionOption::create(['question_id' => $question->id, 'body' => 'Benar', 'is_correct' => true, 'position' => 1]);
        QuestionOption::create(['question_id' => $question->id, 'body' => 'Salah', 'is_correct' => false, 'position' => 2]);

        return $question;
    }

    private function optionId(Question $question, bool $correct): int
    {
        return (int) QuestionOption::where('question_id', $question->id)
            ->where('is_correct', $correct)
            ->value('id');
    }

    /** Simpan jawaban lewat API. $correct null berarti menghapus jawaban. */
    private function answer(int $attemptId, Question $question, ?bool $correct): TestResponse
    {
        return $this->putJson("/api/attempts/{$attemptId}/answers", [
            'question_id' => $question->id,
            'option_id' => $correct === null ? null : $this->optionId($question, $correct),
        ]);
    }

    /**
     * Mengerjakan dan mengirim lewat service (setup yang lebih cepat dari HTTP).
     *
     * @param  array<int, array{0: Question, 1: ?bool}>  $plan
     */
    private function submitViaService(User $student, Quiz $quiz, array $plan): QuizAttempt
    {
        $service = app(AttemptService::class);

        [$attempt] = $service->start($student, $quiz->fresh());

        foreach ($plan as [$question, $correct]) {
            $service->saveAnswer(
                $attempt,
                $question->id,
                $correct === null ? null : $this->optionId($question, $correct)
            );
        }

        return $service->submit($attempt);
    }
}
