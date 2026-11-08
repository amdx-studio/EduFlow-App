<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class ComparisonTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    private function near(float $expected): \Closure
    {
        return fn ($value) => abs((float) $value - $expected) < 0.01;
    }

    /** @return array{0: User, 1: User, 2: Course, 3: Quiz, 4: Quiz} */
    private function scenario(bool $submitReassessment = true): array
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);

        $a = $this->concept($course, 'Pecahan');
        $b = $this->concept($course, 'Desimal');
        $c = $this->concept($course, 'Bilangan');

        $base = $this->quiz($course);
        $baseA = [];
        for ($i = 1; $i <= 5; $i++) {
            $baseA[] = $this->question($base, $a, $i);
        }
        $baseB1 = $this->question($base, $b, 6, 11);
        $baseB2 = $this->question($base, $b, 7, 9);
        $baseC = $this->question($base, $c, 8);

        $re = $this->quiz($course, ['title' => 'Asesmen Ulang', 'kind' => 'reassessment', 'base_quiz_id' => $base->id]);
        $reA = [];
        for ($i = 1; $i <= 5; $i++) {
            $reA[] = $this->question($re, $a, $i);
        }
        $reB1 = $this->question($re, $b, 6, 12);
        $reB2 = $this->question($re, $b, 7, 8);
        $reC = $this->question($re, $c, 8);

        // Sebelum: Pecahan 2/5 = 40, Desimal 11/20 = 55, Bilangan 1 soal (insufficient).
        $this->submitViaService($student, $base, [
            [$baseA[0], true], [$baseA[1], true], [$baseA[2], false], [$baseA[3], false], [$baseA[4], false],
            [$baseB1, true], [$baseB2, false], [$baseC, true],
        ]);

        if ($submitReassessment) {
            // Sesudah: Pecahan 4/5 = 80, Desimal 12/20 = 60, Bilangan 1 soal (insufficient).
            $this->submitViaService($student, $re, [
                [$reA[0], true], [$reA[1], true], [$reA[2], true], [$reA[3], true], [$reA[4], false],
                [$reB1, true], [$reB2, false], [$reC, true],
            ]);
        }

        return [$teacher, $student, $course, $base, $re];
    }

    // TC-032
    public function test_tc032_student_sees_before_after_comparison(): void
    {
        [, $student, , , $re] = $this->scenario();

        $this->signIn($student);

        $this->getJson("/api/quizzes/{$re->id}/comparison")
            ->assertOk()
            ->assertJsonPath('data.status', 'complete')
            ->assertJsonPath('data.note', 'Perubahan skor, bukan bukti sebab-akibat.')
            ->assertJsonCount(2, 'data.concepts')
            ->assertJsonPath('data.concepts.0.concept', 'Pecahan')
            ->assertJsonPath('data.concepts.0.before', $this->near(40))
            ->assertJsonPath('data.concepts.0.after', $this->near(80))
            ->assertJsonPath('data.concepts.0.delta', $this->near(40))
            ->assertJsonPath('data.concepts.0.trend', 'improved')
            ->assertJsonPath('data.concepts.0.reached_mastery', true)
            ->assertJsonPath('data.concepts.1.concept', 'Desimal')
            ->assertJsonPath('data.concepts.1.delta', $this->near(5))
            ->assertJsonPath('data.concepts.1.trend', 'unchanged')
            ->assertJsonPath('data.concepts.1.reached_mastery', false);
    }

    public function test_status_is_incomplete_without_reassessment_attempt(): void
    {
        [, $student, , , $re] = $this->scenario(false);

        $this->signIn($student);

        $this->getJson("/api/quizzes/{$re->id}/comparison")
            ->assertOk()
            ->assertJsonPath('data.status', 'incomplete')
            ->assertJsonPath('data.note', 'Perubahan skor, bukan bukti sebab-akibat.')
            ->assertJsonCount(0, 'data.concepts');
    }

    public function test_non_reassessment_quiz_returns_404(): void
    {
        [, $student, , $base] = $this->scenario(false);

        $this->signIn($student);

        $this->getJson("/api/quizzes/{$base->id}/comparison")->assertNotFound();
    }

    public function test_teacher_needs_student_id_and_sees_that_students_comparison(): void
    {
        [$teacher, $student, , , $re] = $this->scenario();

        $this->signIn($teacher);

        $this->getJson("/api/quizzes/{$re->id}/comparison")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['student_id']);

        $this->getJson("/api/quizzes/{$re->id}/comparison?student_id={$student->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'complete')
            ->assertJsonCount(2, 'data.concepts');

        $outsider = $this->student();
        $this->getJson("/api/quizzes/{$re->id}/comparison?student_id={$outsider->id}")->assertNotFound();
    }

    public function test_access_rules(): void
    {
        [, $student, , , $re] = $this->scenario();

        $this->getJson("/api/quizzes/{$re->id}/comparison")->assertUnauthorized();

        $this->signIn($this->teacher());
        $this->getJson("/api/quizzes/{$re->id}/comparison?student_id={$student->id}")->assertNotFound();

        $this->signIn($this->student());
        $this->getJson("/api/quizzes/{$re->id}/comparison")->assertNotFound();
    }
}
