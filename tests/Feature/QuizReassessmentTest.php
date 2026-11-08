<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class QuizReassessmentTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    // TC-031 (bagian pembuatan)
    public function test_tc031_teacher_creates_reassessment_only_with_published_base(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $base = $this->quiz($course);
        $draftBase = $this->quiz($course, ['title' => 'Draf', 'status' => 'draft', 'published_at' => null]);

        $this->signIn($teacher);

        $this->postJson("/api/courses/{$course->id}/quizzes", [
            'title' => 'Asesmen Ulang',
            'kind' => 'reassessment',
            'base_quiz_id' => $base->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'reassessment')
            ->assertJsonPath('data.status', 'draft');

        $this->postJson("/api/courses/{$course->id}/quizzes", [
            'title' => 'Asesmen Ulang',
            'kind' => 'reassessment',
            'base_quiz_id' => $draftBase->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['base_quiz_id']);
    }

    public function test_reassessment_requires_base_quiz(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);

        $this->signIn($teacher);

        $this->postJson("/api/courses/{$course->id}/quizzes", [
            'title' => 'Asesmen Ulang',
            'kind' => 'reassessment',
        ])->assertUnprocessable()->assertJsonValidationErrors(['base_quiz_id']);
    }

    public function test_base_quiz_from_another_course_is_rejected(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $other = $this->course($teacher, ['title' => 'IPA']);
        $foreignBase = $this->quiz($other);

        $this->signIn($teacher);

        $this->postJson("/api/courses/{$course->id}/quizzes", [
            'title' => 'Asesmen Ulang',
            'kind' => 'reassessment',
            'base_quiz_id' => $foreignBase->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['base_quiz_id']);
    }

    public function test_assessment_cannot_have_base_quiz(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $base = $this->quiz($course);

        $this->signIn($teacher);

        $this->postJson("/api/courses/{$course->id}/quizzes", [
            'title' => 'Kuis',
            'kind' => 'assessment',
            'base_quiz_id' => $base->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['base_quiz_id']);
    }

    public function test_publish_reassessment_sharing_a_concept_succeeds(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $concept = $this->concept($course);
        $base = $this->quiz($course);
        $this->question($base, $concept, 1);
        $this->question($base, $concept, 2);

        $reassessment = $this->quiz($course, [
            'title' => 'Asesmen Ulang',
            'kind' => 'reassessment',
            'base_quiz_id' => $base->id,
            'status' => 'draft',
            'published_at' => null,
        ]);
        $this->question($reassessment, $concept, 1);
        $this->question($reassessment, $concept, 2);

        $this->signIn($teacher);

        $this->postJson("/api/quizzes/{$reassessment->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    public function test_publish_reassessment_without_shared_concept_is_rejected(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $conceptA = $this->concept($course, 'Pecahan');
        $conceptB = $this->concept($course, 'Desimal');
        $base = $this->quiz($course);
        $this->question($base, $conceptA, 1);
        $this->question($base, $conceptA, 2);

        $reassessment = $this->quiz($course, [
            'title' => 'Asesmen Ulang',
            'kind' => 'reassessment',
            'base_quiz_id' => $base->id,
            'status' => 'draft',
            'published_at' => null,
        ]);
        $this->question($reassessment, $conceptB, 1);
        $this->question($reassessment, $conceptB, 2);

        $this->signIn($teacher);

        $this->postJson("/api/quizzes/{$reassessment->id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['base_quiz_id']);

        $this->assertSame('draft', $reassessment->fresh()->status);
    }

    public function test_publish_reassessment_is_rejected_when_base_returns_to_draft(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $concept = $this->concept($course);
        $base = $this->quiz($course);
        $this->question($base, $concept, 1);
        $this->question($base, $concept, 2);

        $reassessment = $this->quiz($course, [
            'title' => 'Asesmen Ulang',
            'kind' => 'reassessment',
            'base_quiz_id' => $base->id,
            'status' => 'draft',
            'published_at' => null,
        ]);
        $this->question($reassessment, $concept, 1);
        $this->question($reassessment, $concept, 2);

        $base->update(['status' => 'draft', 'published_at' => null]);

        $this->signIn($teacher);

        $this->postJson("/api/quizzes/{$reassessment->id}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['base_quiz_id']);
    }
}
