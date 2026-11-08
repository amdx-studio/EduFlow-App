<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class GateReassessmentTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    // TC-030
    public function test_tc030_reassessment_is_locked_until_remedial_is_completed(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $base = $this->quiz($course);
        $q1 = $this->question($base, $concept, 1);
        $q2 = $this->question($base, $concept, 2);
        $lesson = $this->remedialLesson($course, $concept, 'Remedial A', 1);
        $reassessment = $this->quiz($course, [
            'title' => 'Asesmen Ulang',
            'kind' => 'reassessment',
            'base_quiz_id' => $base->id,
        ]);

        $this->submitViaService($student, $base, [[$q1, false], [$q2, false]]);

        $this->signIn($student);

        $this->postJson("/api/quizzes/{$reassessment->id}/attempts")
            ->assertStatus(409)
            ->assertJsonPath('reason', 'Selesaikan remedial.')
            ->assertJsonCount(1, 'pending');

        $this->postJson("/api/lessons/{$lesson->id}/complete")->assertOk();

        $this->postJson("/api/quizzes/{$reassessment->id}/attempts")->assertCreated();
    }

    public function test_reassessment_is_locked_without_a_base_attempt(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $base = $this->quiz($course);
        $reassessment = $this->quiz($course, [
            'title' => 'Asesmen Ulang',
            'kind' => 'reassessment',
            'base_quiz_id' => $base->id,
        ]);

        $this->signIn($student);

        $this->postJson("/api/quizzes/{$reassessment->id}/attempts")
            ->assertStatus(409)
            ->assertJsonPath('reason', 'Kerjakan kuis dasar terlebih dahulu.');
    }

    public function test_reassessment_is_available_when_base_attempt_has_no_remedial(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $base = $this->quiz($course);
        $q1 = $this->question($base, $concept, 1);
        $q2 = $this->question($base, $concept, 2);
        $reassessment = $this->quiz($course, [
            'title' => 'Asesmen Ulang',
            'kind' => 'reassessment',
            'base_quiz_id' => $base->id,
        ]);

        $this->submitViaService($student, $base, [[$q1, true], [$q2, true]]);

        $this->signIn($student);

        $this->postJson("/api/quizzes/{$reassessment->id}/attempts")->assertCreated();
    }

    public function test_student_quiz_list_shows_locked_gate_with_pending_remedial(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $base = $this->quiz($course);
        $q1 = $this->question($base, $concept, 1);
        $q2 = $this->question($base, $concept, 2);
        $this->remedialLesson($course, $concept, 'Remedial A', 1);
        $this->quiz($course, [
            'title' => 'Asesmen Ulang',
            'kind' => 'reassessment',
            'base_quiz_id' => $base->id,
        ]);

        $this->submitViaService($student, $base, [[$q1, false], [$q2, false]]);

        $this->signIn($student);

        $this->getJson("/api/courses/{$course->id}/quizzes")
            ->assertOk()
            ->assertJsonPath('data.1.gate.status', 'locked')
            ->assertJsonPath('data.1.gate.reason', 'Selesaikan remedial.')
            ->assertJsonCount(1, 'data.1.gate.pending')
            ->assertJsonPath('data.1.gate.pending.0.concept', 'Pecahan')
            ->assertJsonPath('data.1.gate.pending.0.lesson.title', 'Remedial A');
    }
}
