<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\LearningConcept;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizPublishTest extends TestCase
{
    use RefreshDatabase;

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

    private function course(User $teacher): Course
    {
        return Course::create([
            'teacher_id' => $teacher->id,
            'title' => 'Matematika',
            'join_code' => Course::generateJoinCode(),
            'min_questions_per_concept' => 2,
        ])->refresh();
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
        ], $extra))->refresh();
    }

    /**
     * Dibuat langsung lewat model agar cacat yang ditolak API (opsi salah, konsep asing) bisa diuji.
     *
     * @param  array<int, array{0: string, 1: bool}>|null  $options
     */
    private function question(Quiz $quiz, LearningConcept $concept, int $position, ?array $options = null): Question
    {
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'concept_id' => $concept->id,
            'body' => "Soal {$position}",
            'points' => 1,
            'position' => $position,
        ]);

        $options ??= [['Benar', true], ['Salah', false]];

        foreach ($options as $i => [$body, $correct]) {
            QuestionOption::create([
                'question_id' => $question->id,
                'body' => $body,
                'is_correct' => $correct,
                'position' => $i + 1,
            ]);
        }

        return $question;
    }

    // TC-017
    public function test_tc017_publish_rejected_with_error_list_and_status_stays_draft(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $own = $this->concept($course);
        $foreign = $this->concept($this->course($teacher), 'Asing');
        $quiz = $this->quiz($course);

        $good = $this->question($quiz, $own, 1);
        $noCorrect = $this->question($quiz, $own, 2, [['A', false], ['B', false]]);
        $foreignConcept = $this->question($quiz, $foreign, 3);
        $oneOption = $this->question($quiz, $own, 4, [['A', true]]);
        $tooMany = $this->question($quiz, $own, 5, array_map(fn ($i) => ["Opsi {$i}", $i === 0], range(0, 6)));

        $this->signIn($teacher);

        $this->postJson("/api/quizzes/{$quiz->id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                "questions.{$noCorrect->id}",
                "questions.{$foreignConcept->id}",
                "questions.{$oneOption->id}",
                "questions.{$tooMany->id}",
            ])
            ->assertJsonMissingValidationErrors("questions.{$good->id}");

        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id, 'status' => 'draft', 'published_at' => null]);
    }

    public function test_publish_rejects_quiz_without_questions(): void
    {
        $teacher = $this->teacher();
        $quiz = $this->quiz($this->course($teacher));
        $this->signIn($teacher);

        $this->postJson("/api/quizzes/{$quiz->id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quiz');

        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id, 'status' => 'draft']);
    }

    // TC-018
    public function test_tc018_publish_succeeds_with_warning_for_concept_below_minimum(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $a = $this->concept($course, 'Pecahan');
        $b = $this->concept($course, 'Desimal');
        $quiz = $this->quiz($course);
        $this->question($quiz, $a, 1);
        $this->question($quiz, $a, 2);
        $this->question($quiz, $b, 3);

        $this->signIn($teacher);

        $this->postJson("/api/quizzes/{$quiz->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonCount(1, 'warnings')
            ->assertJsonPath('warnings.0.concept_id', $b->id)
            ->assertJsonPath('warnings.0.questions_count', 1)
            ->assertJsonPath('warnings.0.minimum', 2);

        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id, 'status' => 'published']);
        $this->assertNotNull($quiz->fresh()->published_at);
    }

    public function test_publish_without_warnings_when_every_concept_meets_minimum(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $a = $this->concept($course, 'Pecahan');
        $b = $this->concept($course, 'Desimal');
        $quiz = $this->quiz($course);
        $this->question($quiz, $a, 1);
        $this->question($quiz, $a, 2);
        $this->question($quiz, $b, 3);
        $this->question($quiz, $b, 4);

        $this->signIn($teacher);

        $this->postJson("/api/quizzes/{$quiz->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('warnings', []);
    }

    public function test_publishing_twice_returns_409(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $this->question($quiz, $concept, 1);
        $this->question($quiz, $concept, 2);
        $this->signIn($teacher);

        $this->postJson("/api/quizzes/{$quiz->id}/publish")->assertOk();
        $this->postJson("/api/quizzes/{$quiz->id}/publish")->assertStatus(409);

        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id, 'status' => 'published']);
    }

    public function test_only_owner_can_publish(): void
    {
        $owner = $this->teacher();
        $student = $this->student();
        $course = $this->course($owner);
        $course->students()->attach($student->id, ['enrolled_at' => now()]);
        $concept = $this->concept($course);
        $draft = $this->quiz($course, ['title' => 'Draf']);
        $this->question($draft, $concept, 1);
        $published = $this->quiz($course, ['title' => 'Terbit', 'status' => 'published', 'published_at' => now()]);

        $this->signIn($student);
        $this->postJson("/api/quizzes/{$published->id}/publish")->assertForbidden();
        $this->postJson("/api/quizzes/{$draft->id}/publish")->assertNotFound();

        $this->signIn($this->teacher());
        $this->postJson("/api/quizzes/{$draft->id}/publish")->assertNotFound();

        $this->assertDatabaseHas('quizzes', ['id' => $draft->id, 'status' => 'draft']);
    }
}
