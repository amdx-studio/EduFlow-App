<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class LessonUnpublishGuardTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    public function test_unpublishing_a_lesson_with_pending_assignment_returns_409(): void
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

        $this->signIn($teacher);

        $this->putJson("/api/lessons/{$lesson->id}", ['is_published' => false])
            ->assertStatus(409);

        $this->assertTrue($lesson->fresh()->is_published);

        $this->signIn($student);
        $this->postJson("/api/lessons/{$lesson->id}/complete")->assertOk();

        $this->signIn($teacher);
        $this->putJson("/api/lessons/{$lesson->id}", ['is_published' => false])->assertOk();

        $this->assertFalse($lesson->fresh()->is_published);
    }

    public function test_unpublishing_without_assignments_and_editing_other_fields_stay_allowed(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);
        $pending = $this->remedialLesson($course, $concept, 'Dipakai', 1);
        $free = $this->remedialLesson($course, $concept, 'Bebas', 3);

        $this->submitViaService($student, $quiz, [[$q1, false], [$q2, false]]);

        $this->signIn($teacher);

        $this->putJson("/api/lessons/{$free->id}", ['is_published' => false])->assertOk();
        $this->putJson("/api/lessons/{$pending->id}", ['title' => 'Judul baru'])->assertOk();
    }
}
