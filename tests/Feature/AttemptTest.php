<?php

namespace Tests\Feature;

use App\Models\AttemptConceptResult;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class AttemptTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    // TC-019
    public function test_tc019_answer_key_is_never_exposed_while_in_progress(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $this->question($quiz, $concept, 2);

        $this->signIn($student);

        $id = $this->postJson("/api/quizzes/{$quiz->id}/attempts")
            ->assertCreated()
            ->assertDontSee('is_correct')
            ->json('data.id');

        $show = $this->getJson("/api/attempts/{$id}")
            ->assertOk()
            ->assertDontSee('is_correct')
            ->assertJsonPath('data.attempt.status', 'in_progress')
            ->assertJsonCount(2, 'data.questions')
            ->assertJsonCount(2, 'data.questions.0.options');

        $this->assertEqualsCanonicalizing(['id', 'body'], array_keys($show->json('data.questions.0.options.0')));

        $this->answer($id, $q1, true)->assertOk()->assertDontSee('is_correct');
        $this->getJson("/api/attempts/{$id}")->assertOk()->assertDontSee('is_correct');
        $this->getJson("/api/quizzes/{$quiz->id}")->assertOk()->assertDontSee('is_correct');
    }

    public function test_start_creates_then_resumes_the_same_attempt(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $quiz = $this->quiz($course);
        $this->question($quiz, $this->concept($course), 1);

        $this->signIn($student);

        $first = $this->postJson("/api/quizzes/{$quiz->id}/attempts")
            ->assertCreated()
            ->assertJsonPath('data.attempt_no', 1)
            ->assertJsonPath('data.status', 'in_progress')
            ->json('data.id');

        $second = $this->postJson("/api/quizzes/{$quiz->id}/attempts")
            ->assertOk()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('quiz_attempts', 1);
    }

    public function test_start_is_rejected_for_teacher_non_member_and_draft_quiz(): void
    {
        $teacher = $this->teacher();
        $member = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $member);
        $quiz = $this->quiz($course);
        $draft = $this->quiz($course, ['title' => 'Draf', 'status' => 'draft', 'published_at' => null]);

        $this->signIn($teacher);
        $this->postJson("/api/quizzes/{$quiz->id}/attempts")->assertForbidden();

        $this->signIn($this->student());
        $this->postJson("/api/quizzes/{$quiz->id}/attempts")->assertNotFound();

        $this->signIn($member);
        $this->postJson("/api/quizzes/{$draft->id}/attempts")->assertNotFound();

        $this->assertDatabaseCount('quiz_attempts', 0);
    }

    // TC-020
    public function test_tc020_submit_scores_answers_and_marks_correctness(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q = [];
        foreach ([1, 2, 3, 4] as $position) {
            $q[$position] = $this->question($quiz, $concept, $position);
        }

        $this->signIn($student);
        $id = $this->postJson("/api/quizzes/{$quiz->id}/attempts")->assertCreated()->json('data.id');

        $this->answer($id, $q[1], true)->assertOk();
        $this->answer($id, $q[2], true)->assertOk();
        $this->answer($id, $q[3], true)->assertOk();
        $this->answer($id, $q[4], false)->assertOk();

        $res = $this->postJson("/api/attempts/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.attempt.earned_points', 3)
            ->assertJsonPath('data.attempt.possible_points', 4)
            ->assertJsonPath('data.answers.0.is_correct', true)
            ->assertJsonPath('data.answers.1.is_correct', true)
            ->assertJsonPath('data.answers.2.is_correct', true)
            ->assertJsonPath('data.answers.3.is_correct', false)
            ->assertJsonPath('data.answers.3.selected_option_id', $this->optionId($q[4], false))
            ->assertJsonPath('data.answers.3.correct_option_id', $this->optionId($q[4], true));

        $this->assertEquals(75.0, $res->json('data.attempt.score_percent'));

        $attempt = QuizAttempt::findOrFail($id);
        $this->assertSame('submitted', $attempt->status);
        $this->assertNotNull($attempt->submitted_at);
        $this->assertEquals(75.0, (float) $attempt->score_percent);
        $this->assertSame(4, QuizAnswer::where('attempt_id', $id)->count());
        $this->assertSame(3, QuizAnswer::where('attempt_id', $id)->where('is_correct', true)->count());
        $this->assertSame(3, (int) QuizAnswer::where('attempt_id', $id)->sum('points_awarded'));
    }

    public function test_unanswered_questions_score_zero_and_still_get_answer_rows(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $this->question($quiz, $concept, 2);
        $this->question($quiz, $concept, 3);

        $this->signIn($student);
        $id = $this->postJson("/api/quizzes/{$quiz->id}/attempts")->assertCreated()->json('data.id');

        $this->answer($id, $q1, null)->assertOk()->assertJsonPath('data.option_id', null);

        $res = $this->postJson("/api/attempts/{$id}/submit")
            ->assertOk()
            ->assertJsonPath('data.attempt.earned_points', 0)
            ->assertJsonPath('data.attempt.possible_points', 3)
            ->assertJsonPath('data.concepts.0.level', 'not_mastered')
            ->assertJsonPath('data.answers.1.selected_option_id', null)
            ->assertJsonPath('data.answers.1.is_correct', false);

        $this->assertEquals(0.0, $res->json('data.attempt.score_percent'));
        $this->assertSame(3, QuizAnswer::where('attempt_id', $id)->count());
        $this->assertSame(0, QuizAnswer::where('attempt_id', $id)->where('is_correct', true)->count());
    }

    // TC-021
    public function test_tc021_attempt_limit_and_double_submit_return_409_without_duplicate_data(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course, ['max_attempts' => 1]);
        $q1 = $this->question($quiz, $concept, 1);
        $this->question($quiz, $concept, 2);

        $this->signIn($student);
        $id = $this->postJson("/api/quizzes/{$quiz->id}/attempts")->assertCreated()->json('data.id');
        $this->answer($id, $q1, true)->assertOk();
        $this->postJson("/api/attempts/{$id}/submit")->assertOk();

        // Percobaan ke-2 melebihi max_attempts.
        $this->postJson("/api/quizzes/{$quiz->id}/attempts")
            ->assertStatus(409)
            ->assertJsonPath('reason', 'Batas percobaan tercapai.')
            ->assertJsonPath('pending', []);

        // Kirim ulang percobaan yang sudah terkirim.
        $this->postJson("/api/attempts/{$id}/submit")->assertStatus(409);

        $this->assertDatabaseCount('quiz_attempts', 1);
        $this->assertSame(2, QuizAnswer::where('attempt_id', $id)->count());
        $this->assertSame(1, AttemptConceptResult::where('attempt_id', $id)->count());
    }

    public function test_save_answer_validation_and_clearing_an_answer(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);
        $foreign = $this->question($this->quiz($course, ['title' => 'Lain']), $concept, 1);

        $this->signIn($student);
        $id = $this->postJson("/api/quizzes/{$quiz->id}/attempts")->assertCreated()->json('data.id');

        // Soal milik kuis lain.
        $this->putJson("/api/attempts/{$id}/answers", [
            'question_id' => $foreign->id,
            'option_id' => $this->optionId($foreign, true),
        ])->assertUnprocessable()->assertJsonValidationErrors('question_id');

        // Opsi milik soal lain.
        $this->putJson("/api/attempts/{$id}/answers", [
            'question_id' => $q1->id,
            'option_id' => $this->optionId($q2, true),
        ])->assertUnprocessable()->assertJsonValidationErrors('option_id');

        // option_id wajib ada (null boleh, hilang tidak).
        $this->putJson("/api/attempts/{$id}/answers", ['question_id' => $q1->id])
            ->assertUnprocessable()->assertJsonValidationErrors('option_id');

        $this->assertDatabaseCount('quiz_answers', 0);

        // Simpan lalu hapus jawaban.
        $this->answer($id, $q1, true)->assertOk()->assertJsonPath('data.question_id', $q1->id);
        $this->answer($id, $q1, null)->assertOk()->assertJsonPath('data.option_id', null);

        $this->assertSame(1, QuizAnswer::where('attempt_id', $id)->count());
        $this->assertNull(QuizAnswer::where('attempt_id', $id)->where('question_id', $q1->id)->value('option_id'));
    }

    public function test_saving_to_a_submitted_attempt_returns_409_and_keeps_the_answer(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $this->question($quiz, $concept, 2);

        $this->signIn($student);
        $id = $this->postJson("/api/quizzes/{$quiz->id}/attempts")->assertCreated()->json('data.id');
        $this->answer($id, $q1, true)->assertOk();
        $this->postJson("/api/attempts/{$id}/submit")->assertOk();

        $before = QuizAnswer::where('attempt_id', $id)->where('question_id', $q1->id)->value('option_id');

        $this->answer($id, $q1, false)->assertStatus(409);

        $after = QuizAnswer::where('attempt_id', $id)->where('question_id', $q1->id)->value('option_id');
        $this->assertSame($before, $after);
    }

    public function test_resuming_returns_saved_answers(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);

        $this->signIn($student);
        $id = $this->postJson("/api/quizzes/{$quiz->id}/attempts")->assertCreated()->json('data.id');
        $this->answer($id, $q1, true)->assertOk();
        $this->answer($id, $q2, false)->assertOk();

        $this->getJson("/api/attempts/{$id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.answers')
            ->assertJsonFragment(['question_id' => $q1->id, 'option_id' => $this->optionId($q1, true)])
            ->assertJsonFragment(['question_id' => $q2->id, 'option_id' => $this->optionId($q2, false)]);
    }

    public function test_other_users_cannot_touch_an_attempt(): void
    {
        $teacher = $this->teacher();
        $owner = $this->student();
        $other = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $owner);
        $this->enroll($course, $other);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $this->question($quiz, $concept, 2);

        $this->signIn($owner);
        $id = $this->postJson("/api/quizzes/{$quiz->id}/attempts")->assertCreated()->json('data.id');
        $this->answer($id, $q1, true)->assertOk();

        // Siswa lain di kelas yang sama.
        $this->signIn($other);
        $this->getJson("/api/attempts/{$id}")->assertNotFound();
        $this->answer($id, $q1, false)->assertNotFound();
        $this->postJson("/api/attempts/{$id}/submit")->assertNotFound();
        $this->getJson("/api/attempts/{$id}/result")->assertNotFound();

        // Guru pemilik: percobaan berjalan tidak terlihat.
        $this->signIn($teacher);
        $this->getJson("/api/attempts/{$id}")->assertNotFound();
        $this->getJson("/api/attempts/{$id}/result")->assertNotFound();

        // Belum ada yang berubah.
        $this->assertSame('in_progress', QuizAttempt::findOrFail($id)->status);

        // Setelah terkirim: guru pemilik boleh melihat, guru lain dan siswa lain tidak.
        $this->signIn($owner);
        $this->postJson("/api/attempts/{$id}/submit")->assertOk();

        $this->signIn($teacher);
        $this->getJson("/api/attempts/{$id}/result")
            ->assertOk()
            ->assertJsonStructure(['data' => ['attempt', 'concepts', 'answers', 'remediation', 'remediation_gaps']]);
        $this->getJson("/api/attempts/{$id}")->assertOk()->assertJsonStructure(['data' => ['attempt', 'concepts', 'answers']]);

        $this->signIn($this->teacher());
        $this->getJson("/api/attempts/{$id}/result")->assertNotFound();

        $this->signIn($other);
        $this->getJson("/api/attempts/{$id}/result")->assertNotFound();
    }

    public function test_archived_course_blocks_new_attempts_but_not_finishing_one_in_progress(): void
    {
        $teacher = $this->teacher();
        $first = $this->student();
        $second = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $first);
        $this->enroll($course, $second);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);

        $this->signIn($first);
        $id = $this->postJson("/api/quizzes/{$quiz->id}/attempts")->assertCreated()->json('data.id');
        $this->answer($id, $q1, true)->assertOk();

        $course->update(['is_archived' => true]);

        $this->postJson("/api/quizzes/{$quiz->id}/attempts")
            ->assertStatus(409)
            ->assertJsonPath('reason', 'Kelas diarsipkan.');

        $this->signIn($second);
        $this->postJson("/api/quizzes/{$quiz->id}/attempts")
            ->assertStatus(409)
            ->assertJsonPath('reason', 'Kelas diarsipkan.');
        $this->assertSame(0, QuizAttempt::where('student_id', $second->id)->count());

        // Percobaan yang sudah berjalan tetap bisa diselesaikan.
        $this->signIn($first);
        $this->answer($id, $q2, true)->assertOk();
        $this->postJson("/api/attempts/{$id}/submit")->assertOk();
    }

    public function test_student_quiz_list_and_detail_show_gate_attempts_used_and_history(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $other = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $this->enroll($course, $other);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course, ['max_attempts' => 1]);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);
        $this->quiz($course, ['title' => 'Draf', 'status' => 'draft', 'published_at' => null]);

        $this->signIn($student);

        $this->getJson("/api/courses/{$course->id}/quizzes")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attempts_used', 0)
            ->assertJsonPath('data.0.max_attempts', 1)
            ->assertJsonPath('data.0.gate.status', 'available')
            ->assertJsonPath('data.0.gate.reason', null)
            ->assertJsonPath('data.0.gate.pending', [])
            ->assertJsonMissingPath('data.0.questions_count');

        $id = $this->postJson("/api/quizzes/{$quiz->id}/attempts")->assertCreated()->json('data.id');

        // Sedang mengerjakan: gate tetap tersedia agar bisa melanjutkan.
        $this->getJson("/api/courses/{$course->id}/quizzes")
            ->assertJsonPath('data.0.attempts_used', 1)
            ->assertJsonPath('data.0.gate.status', 'available');

        $this->answer($id, $q1, true)->assertOk();
        $this->answer($id, $q2, true)->assertOk();
        $this->postJson("/api/attempts/{$id}/submit")->assertOk();

        $this->getJson("/api/courses/{$course->id}/quizzes")
            ->assertJsonPath('data.0.attempts_used', 1)
            ->assertJsonPath('data.0.gate.status', 'locked')
            ->assertJsonPath('data.0.gate.reason', 'Batas percobaan tercapai.');

        $detail = $this->getJson("/api/quizzes/{$quiz->id}")
            ->assertOk()
            ->assertJsonPath('data.attempts_used', 1)
            ->assertJsonPath('data.gate.status', 'locked')
            ->assertJsonCount(1, 'data.attempts')
            ->assertJsonPath('data.attempts.0.id', $id)
            ->assertJsonPath('data.attempts.0.status', 'submitted')
            ->assertJsonMissingPath('data.questions');

        $this->assertEquals(100.0, $detail->json('data.attempts.0.score_percent'));

        // Riwayat hanya milik siswa itu sendiri.
        $this->signIn($other);
        $this->getJson("/api/quizzes/{$quiz->id}")
            ->assertOk()
            ->assertJsonPath('data.attempts_used', 0)
            ->assertJsonPath('data.gate.status', 'available')
            ->assertJsonCount(0, 'data.attempts');
    }

    public function test_guest_gets_401_on_attempt_endpoints(): void
    {
        $this->getJson('/api/attempts/1')->assertUnauthorized();
        $this->postJson('/api/quizzes/1/attempts')->assertUnauthorized();
    }
}
