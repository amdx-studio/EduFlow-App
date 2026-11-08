<?php

namespace Tests\Feature;

use App\Models\RemediationAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class RemediationIndexTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    public function test_student_lists_only_own_remediation_for_that_course(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $other = $this->student();

        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $this->enroll($course, $other);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);
        $this->remedialLesson($course, $concept, 'Remedial A', 1);
        $this->remedialLesson($course, $concept, 'Remedial B', 2);

        $course2 = $this->course($teacher, ['title' => 'IPA']);
        $this->enroll($course2, $student);
        $concept2 = $this->concept($course2);
        $quiz2 = $this->quiz($course2);
        $r1 = $this->question($quiz2, $concept2, 1);
        $r2 = $this->question($quiz2, $concept2, 2);
        $this->remedialLesson($course2, $concept2, 'Remedial IPA', 1);

        $this->submitViaService($student, $quiz, [[$q1, false], [$q2, false]]);
        $this->submitViaService($other, $quiz, [[$q1, false], [$q2, false]]);
        $this->submitViaService($student, $quiz2, [[$r1, false], [$r2, false]]);

        $this->signIn($student);

        $response = $this->getJson("/api/courses/{$course->id}/remediation")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.concept', 'Pecahan')
            ->assertJsonPath('data.0.lesson.title', 'Remedial A')
            ->assertJsonPath('data.1.lesson.title', 'Remedial B')
            ->assertJsonPath('data.0.source', 'rule')
            ->assertJsonPath('data.0.status', 'assigned')
            ->assertJsonPath('data.0.note', null)
            ->assertJsonPath('data.0.completed_at', null);

        $this->assertStringContainsString('Pecahan', $response->json('data.0.reason'));
        $this->assertNotNull($response->json('data.0.assigned_at'));

        $this->getJson("/api/courses/{$course2->id}/remediation")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lesson.title', 'Remedial IPA');
    }

    public function test_list_reflects_completed_status_after_lesson_complete(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);
        $lesson = $this->remedialLesson($course, $concept, 'Remedial A', 1);

        $this->submitViaService($student, $quiz, [[$q1, false], [$q2, false]]);
        $this->assertSame(1, RemediationAssignment::where('status', 'assigned')->count());

        $this->signIn($student);
        $this->postJson("/api/lessons/{$lesson->id}/complete")->assertOk();

        $this->getJson("/api/courses/{$course->id}/remediation")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.completed_at', fn ($value) => $value !== null);
    }

    public function test_student_without_remediation_gets_empty_list(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);

        $this->signIn($student);

        $this->getJson("/api/courses/{$course->id}/remediation")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_access_rules(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $outsider = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);

        $this->getJson("/api/courses/{$course->id}/remediation")->assertUnauthorized();

        $this->signIn($teacher);
        $this->getJson("/api/courses/{$course->id}/remediation")->assertForbidden();

        $this->signIn($outsider);
        $this->getJson("/api/courses/{$course->id}/remediation")->assertNotFound();
    }
}
