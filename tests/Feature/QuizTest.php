<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\LearningConcept;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class QuizTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    private function quiz(Course $course, array $extra = []): Quiz
    {
        return Quiz::create(array_merge([
            'course_id' => $course->id,
            'title' => 'Kuis 1',
        ], $extra))->refresh();
    }

    private function publishedQuiz(Course $course, array $extra = []): Quiz
    {
        return $this->quiz($course, array_merge(['status' => 'published', 'published_at' => now()], $extra));
    }

    private function question(Quiz $quiz, LearningConcept $concept, int $position = 1): Question
    {
        $question = Question::create([
            'quiz_id' => $quiz->id,
            'concept_id' => $concept->id,
            'body' => "Soal {$position}",
            'points' => 1,
            'position' => $position,
        ]);

        QuestionOption::create(['question_id' => $question->id, 'body' => 'Benar', 'is_correct' => true, 'position' => 1]);
        QuestionOption::create(['question_id' => $question->id, 'body' => 'Salah', 'is_correct' => false, 'position' => 2]);

        return $question;
    }

    private function payload(LearningConcept $concept, array $extra = []): array
    {
        return array_merge([
            'body' => 'Berapa 1/2 + 1/2?',
            'concept_id' => $concept->id,
            'points' => 1,
            'options' => [
                ['body' => '1', 'is_correct' => true],
                ['body' => '2', 'is_correct' => false],
            ],
        ], $extra);
    }

    // TC-016
    public function test_tc016_teacher_creates_quiz_with_questions_linked_to_concepts(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $a = $this->concept($course, 'Pecahan');
        $b = $this->concept($course, 'Desimal');
        $this->signIn($teacher);

        $quizId = $this->postJson("/api/courses/{$course->id}/quizzes", [
            'title' => 'Kuis 1',
            'kind' => 'assessment',
            'max_attempts' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.max_attempts', 2)
            ->json('data.id');

        foreach ([$a, $a, $b, $b] as $i => $concept) {
            $this->postJson("/api/quizzes/{$quizId}/questions", $this->payload($concept, ['body' => 'Soal '.($i + 1)]))
                ->assertCreated()
                ->assertJsonPath('data.concept_id', $concept->id)
                ->assertJsonPath('data.position', $i + 1)
                ->assertJsonCount(2, 'data.options');
        }

        $this->assertDatabaseCount('questions', 4);
        $this->assertDatabaseCount('question_options', 8);

        $this->getJson("/api/quizzes/{$quizId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.questions_count', 4)
            ->assertJsonCount(4, 'data.questions')
            ->assertJsonPath('data.questions.0.options.0.is_correct', true)
            ->assertJsonPath('data.questions.2.concept.name', 'Desimal');

        $this->getJson("/api/courses/{$course->id}/quizzes")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.questions_count', 4)
            ->assertJsonPath('data.0.attempts_count', 0);
    }

    public function test_quiz_creation_validation_and_defaults(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $this->signIn($teacher);
        $url = "/api/courses/{$course->id}/quizzes";

        $this->postJson($url, ['kind' => 'assessment'])
            ->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->postJson($url, ['title' => 'X', 'kind' => 'reassessment'])
            ->assertUnprocessable()->assertJsonValidationErrors('base_quiz_id');
        $this->postJson($url, ['title' => 'X', 'kind' => 'bukan-jenis'])
            ->assertUnprocessable()->assertJsonValidationErrors('kind');
        $this->postJson($url, ['title' => 'X', 'kind' => 'assessment', 'base_quiz_id' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('base_quiz_id');
        $this->postJson($url, ['title' => 'X', 'kind' => 'assessment', 'max_attempts' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('max_attempts');

        $this->assertDatabaseCount('quizzes', 0);

        $this->postJson($url, ['title' => 'X', 'kind' => 'assessment'])
            ->assertCreated()
            ->assertJsonPath('data.max_attempts', 1);
    }

    public function test_question_validation_rejects_invalid_payloads(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $concept = $this->concept($course);
        $foreign = $this->concept($this->course($teacher), 'Asing');
        $quiz = $this->quiz($course);
        $this->signIn($teacher);

        $sevenOptions = array_map(fn ($i) => ['body' => "Opsi {$i}", 'is_correct' => $i === 0], range(0, 6));

        $cases = [
            'satu opsi' => ['options', ['options' => [['body' => 'A', 'is_correct' => true]]]],
            'tujuh opsi' => ['options', ['options' => $sevenOptions]],
            'tanpa opsi benar' => ['options', ['options' => [['body' => 'A', 'is_correct' => false], ['body' => 'B', 'is_correct' => false]]]],
            'dua opsi benar' => ['options', ['options' => [['body' => 'A', 'is_correct' => true], ['body' => 'B', 'is_correct' => true]]]],
            'konsep kelas lain' => ['concept_id', ['concept_id' => $foreign->id]],
            'poin nol' => ['points', ['points' => 0]],
            'isi soal kosong' => ['body', ['body' => '']],
        ];

        foreach ($cases as $label => [$key, $override]) {
            $this->postJson("/api/quizzes/{$quiz->id}/questions", $this->payload($concept, $override))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($key);
        }

        $this->assertDatabaseCount('questions', 0);
        $this->assertDatabaseCount('question_options', 0);
    }

    public function test_question_update_replaces_options_wholly_and_keeps_position(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $question = $this->question($quiz, $concept, 1);
        $this->signIn($teacher);

        // Opsi benar berpindah dari "Benar" ke "Opsi D" dalam satu permintaan.
        $this->putJson("/api/questions/{$question->id}", $this->payload($concept, [
            'body' => 'Soal diubah',
            'options' => [
                ['body' => 'Opsi C', 'is_correct' => false],
                ['body' => 'Opsi D', 'is_correct' => true],
                ['body' => 'Opsi E', 'is_correct' => false],
            ],
        ]))
            ->assertOk()
            ->assertJsonPath('data.body', 'Soal diubah')
            ->assertJsonPath('data.position', 1)
            ->assertJsonCount(3, 'data.options')
            ->assertJsonPath('data.options.1.is_correct', true);

        $this->assertDatabaseMissing('question_options', ['question_id' => $question->id, 'body' => 'Benar']);
        $this->assertSame(3, QuestionOption::where('question_id', $question->id)->count());
        $this->assertSame(1, QuestionOption::where('question_id', $question->id)->where('is_correct', true)->count());

        $this->putJson("/api/questions/{$question->id}", $this->payload($concept, ['position' => 9]))
            ->assertOk()
            ->assertJsonPath('data.position', 9);
    }

    public function test_structure_cannot_change_when_quiz_is_published(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $concept = $this->concept($course);
        $quiz = $this->publishedQuiz($course);
        $question = $this->question($quiz, $concept);
        $this->signIn($teacher);

        $this->postJson("/api/quizzes/{$quiz->id}/questions", $this->payload($concept))->assertStatus(409);
        $this->putJson("/api/questions/{$question->id}", $this->payload($concept))->assertStatus(409);
        $this->deleteJson("/api/questions/{$question->id}")->assertStatus(409);

        $this->assertDatabaseCount('questions', 1);
    }

    public function test_teacher_deletes_question_in_draft_with_its_options(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $quiz = $this->quiz($course);
        $question = $this->question($quiz, $this->concept($course));
        $this->signIn($teacher);

        $this->deleteJson("/api/questions/{$question->id}")->assertNoContent();

        $this->assertDatabaseCount('questions', 0);
        $this->assertDatabaseCount('question_options', 0);
    }

    public function test_quiz_update_allows_title_and_attempts_but_not_published_status(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $quiz = $this->quiz($course);
        $this->signIn($teacher);

        $this->putJson("/api/quizzes/{$quiz->id}", ['title' => 'Judul Baru', 'max_attempts' => 3])
            ->assertOk()
            ->assertJsonPath('data.title', 'Judul Baru')
            ->assertJsonPath('data.max_attempts', 3);

        $this->putJson("/api/quizzes/{$quiz->id}", ['status' => 'published'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id, 'status' => 'draft']);
    }

    public function test_revert_to_draft_only_when_no_attempts(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $quiz = $this->publishedQuiz($course);
        $this->question($quiz, $this->concept($course));
        $this->signIn($teacher);

        $this->putJson("/api/quizzes/{$quiz->id}", ['status' => 'draft'])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null);

        $quiz->refresh()->update(['status' => 'published', 'published_at' => now()]);
        QuizAttempt::forceCreate([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'attempt_no' => 1,
            'started_at' => now(),
        ]);

        $this->putJson("/api/quizzes/{$quiz->id}", ['status' => 'draft'])->assertStatus(409);
        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id, 'status' => 'published']);

        // Judul tetap boleh diubah walau sudah ada percobaan.
        $this->putJson("/api/quizzes/{$quiz->id}", ['title' => 'Judul Baru'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Judul Baru');
    }

    public function test_delete_quiz_only_when_draft_without_attempts(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $published = $this->publishedQuiz($course, ['title' => 'Terbit']);
        $withAttempt = $this->quiz($course, ['title' => 'Draf dengan percobaan']);
        $free = $this->quiz($course, ['title' => 'Draf bebas']);
        QuizAttempt::forceCreate([
            'quiz_id' => $withAttempt->id,
            'student_id' => $student->id,
            'attempt_no' => 1,
            'started_at' => now(),
        ]);
        $this->signIn($teacher);

        $this->deleteJson("/api/quizzes/{$published->id}")->assertStatus(409);
        $this->deleteJson("/api/quizzes/{$withAttempt->id}")->assertStatus(409);
        $this->deleteJson("/api/quizzes/{$free->id}")->assertNoContent();

        $this->assertDatabaseHas('quizzes', ['id' => $published->id]);
        $this->assertDatabaseHas('quizzes', ['id' => $withAttempt->id]);
        $this->assertDatabaseMissing('quizzes', ['id' => $free->id]);
    }

    public function test_student_sees_only_published_quizzes_without_answer_key(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $published = $this->publishedQuiz($course, ['title' => 'Terbit']);
        $this->question($published, $concept);
        $draft = $this->quiz($course, ['title' => 'Draf']);

        $this->signIn($student);

        $this->getJson("/api/courses/{$course->id}/quizzes")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Terbit')
            ->assertJsonMissingPath('data.0.questions_count');

        $this->getJson("/api/quizzes/{$published->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Terbit')
            ->assertJsonMissingPath('data.questions')
            ->assertDontSee('is_correct');

        $this->getJson("/api/quizzes/{$draft->id}")->assertNotFound();
    }

    public function test_member_student_gets_403_on_write_endpoints_and_404_on_draft_questions(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $published = $this->publishedQuiz($course);
        $question = $this->question($published, $concept);
        $draft = $this->quiz($course, ['title' => 'Draf']);
        $draftQuestion = $this->question($draft, $concept);

        $this->signIn($student);

        $this->postJson("/api/courses/{$course->id}/quizzes", ['title' => 'X', 'kind' => 'assessment'])->assertForbidden();
        $this->putJson("/api/quizzes/{$published->id}", ['title' => 'X'])->assertForbidden();
        $this->deleteJson("/api/quizzes/{$published->id}")->assertForbidden();
        $this->postJson("/api/quizzes/{$published->id}/questions", $this->payload($concept))->assertForbidden();
        $this->postJson("/api/quizzes/{$published->id}/publish")->assertForbidden();
        $this->putJson("/api/questions/{$question->id}", $this->payload($concept))->assertForbidden();
        $this->deleteJson("/api/questions/{$question->id}")->assertForbidden();

        // Soal di kuis draf tidak dibedakan dari soal yang tidak ada.
        $this->putJson("/api/questions/{$draftQuestion->id}", $this->payload($concept))->assertNotFound();
        $this->deleteJson("/api/questions/{$draftQuestion->id}")->assertNotFound();
    }

    public function test_non_member_student_and_other_teacher_get_404(): void
    {
        $owner = $this->teacher();
        $course = $this->course($owner);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $question = $this->question($quiz, $concept);

        $this->signIn($this->student());
        $this->getJson("/api/courses/{$course->id}/quizzes")->assertNotFound();
        $this->getJson("/api/quizzes/{$quiz->id}")->assertNotFound();

        $this->signIn($this->teacher());
        $this->getJson("/api/courses/{$course->id}/quizzes")->assertNotFound();
        $this->postJson("/api/courses/{$course->id}/quizzes", ['title' => 'X', 'kind' => 'assessment'])->assertNotFound();
        $this->getJson("/api/quizzes/{$quiz->id}")->assertNotFound();
        $this->putJson("/api/quizzes/{$quiz->id}", ['title' => 'X'])->assertNotFound();
        $this->deleteJson("/api/quizzes/{$quiz->id}")->assertNotFound();
        $this->postJson("/api/quizzes/{$quiz->id}/questions", $this->payload($concept))->assertNotFound();
        $this->postJson("/api/quizzes/{$quiz->id}/publish")->assertNotFound();
        $this->putJson("/api/questions/{$question->id}", $this->payload($concept))->assertNotFound();
        $this->deleteJson("/api/questions/{$question->id}")->assertNotFound();

        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id, 'title' => 'Kuis 1', 'status' => 'draft']);
        $this->assertDatabaseCount('questions', 1);
    }

    public function test_guest_gets_401(): void
    {
        $course = $this->course($this->teacher());

        $this->getJson("/api/courses/{$course->id}/quizzes")->assertUnauthorized();
    }

    public function test_question_order_follows_position_for_teacher_and_student(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $first = $this->question($quiz, $concept, 1);
        $second = $this->question($quiz, $concept, 2);
        $third = $this->question($quiz, $concept, 3);

        // Geser soal ketiga ke depan lewat API (nilai urutan 0).
        $this->signIn($teacher);
        $this->putJson("/api/questions/{$third->id}", $this->payload($concept, ['position' => 0]))
            ->assertOk()
            ->assertJsonPath('data.position', 0);

        $this->getJson("/api/quizzes/{$quiz->id}")
            ->assertOk()
            ->assertJsonPath('data.questions.0.id', $third->id)
            ->assertJsonPath('data.questions.1.id', $first->id)
            ->assertJsonPath('data.questions.2.id', $second->id);

        // Nilai urutan sama diurutkan menurut waktu dibuat (id).
        $this->putJson("/api/questions/{$second->id}", $this->payload($concept, ['position' => 1]))->assertOk();

        $this->getJson("/api/quizzes/{$quiz->id}")
            ->assertJsonPath('data.questions.0.id', $third->id)
            ->assertJsonPath('data.questions.1.id', $first->id)
            ->assertJsonPath('data.questions.2.id', $second->id);

        // Siswa menerima soal dengan urutan yang sama.
        $quiz->refresh()->update(['status' => 'published', 'published_at' => now()]);

        $this->signIn($student);
        $attemptId = $this->postJson("/api/quizzes/{$quiz->id}/attempts")->assertCreated()->json('data.id');

        $this->getJson("/api/attempts/{$attemptId}")
            ->assertOk()
            ->assertJsonPath('data.questions.0.id', $third->id)
            ->assertJsonPath('data.questions.1.id', $first->id)
            ->assertJsonPath('data.questions.2.id', $second->id);
    }
}
