<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    // TC-009
    public function test_tc009_teacher_creates_course_with_defaults_and_join_code(): void
    {
        $this->actingAs($this->teacher(), 'web');

        $res = $this->postJson('/api/courses', ['title' => 'Matematika'])
            ->assertCreated()
            ->assertJsonPath('data.mastery_threshold', 70)
            ->assertJsonPath('data.weak_threshold', 50)
            ->assertJsonPath('data.min_questions_per_concept', 2);

        $this->assertMatchesRegularExpression(
            '/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{8}$/',
            $res->json('data.join_code')
        );
    }

    public function test_weak_threshold_must_be_below_mastery_threshold(): void
    {
        $this->actingAs($this->teacher(), 'web');

        $this->postJson('/api/courses', ['title' => 'X', 'mastery_threshold' => 60, 'weak_threshold' => 60])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('weak_threshold');

        $this->postJson('/api/courses', ['title' => 'X', 'weak_threshold' => 80])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('weak_threshold');
    }

    // TC-010
    public function test_tc010_student_joins_and_rejoin_does_not_duplicate(): void
    {
        $course = $this->course($this->teacher());
        $this->actingAs($this->student(), 'web');

        $this->postJson('/api/courses/join', ['join_code' => $course->join_code])->assertOk();
        $this->postJson('/api/courses/join', ['join_code' => strtolower($course->join_code)])->assertOk();

        $this->assertDatabaseCount('course_enrollments', 1);
    }

    // TC-011
    public function test_tc011_invalid_or_archived_join_code_returns_422(): void
    {
        $archived = $this->course($this->teacher(), ['is_archived' => true]);
        $this->actingAs($this->student(), 'web');

        $this->postJson('/api/courses/join', ['join_code' => 'ZZZZZZZZ'])
            ->assertUnprocessable()->assertJsonValidationErrors('join_code');

        $this->postJson('/api/courses/join', ['join_code' => $archived->join_code])
            ->assertUnprocessable()->assertJsonValidationErrors('join_code');
    }

    // TC-012 (endpoint materi baru ada di Phase 5; di sini diuji lewat detail kelas)
    public function test_tc012_non_member_student_cannot_access_course(): void
    {
        $course = $this->course($this->teacher());
        $this->actingAs($this->student(), 'web');

        $this->getJson("/api/courses/{$course->id}")->assertNotFound();

        $status = $this->getJson("/api/courses/{$course->id}/students")->getStatusCode();
        $this->assertContains($status, [403, 404]);
    }

    public function test_student_does_not_see_join_code_but_sees_teacher_name(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $this->actingAs($this->student(), 'web');
        $this->postJson('/api/courses/join', ['join_code' => $course->join_code])->assertOk();

        $this->getJson("/api/courses/{$course->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.join_code')
            ->assertJsonPath('data.teacher_name', $teacher->name);
    }

    public function test_only_owner_can_update_and_archive(): void
    {
        $course = $this->course($this->teacher());
        $this->actingAs($this->teacher(), 'web');

        $this->putJson("/api/courses/{$course->id}", ['title' => 'Hack'])->assertNotFound();
        $this->postJson("/api/courses/{$course->id}/archive")->assertNotFound();
    }

    public function test_owner_updates_and_archives_course(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $this->actingAs($teacher, 'web');

        $this->putJson("/api/courses/{$course->id}", ['title' => 'Baru'])
            ->assertOk()->assertJsonPath('data.title', 'Baru');

        $this->postJson("/api/courses/{$course->id}/archive")
            ->assertOk()->assertJsonPath('data.is_archived', true);
    }

    public function test_teacher_lists_own_courses_and_students(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $this->course($this->teacher());
        $student = $this->student();
        $course->students()->attach($student->id, ['enrolled_at' => now()]);
        $this->actingAs($teacher, 'web');

        $this->getJson('/api/courses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.students_count', 1)
            ->assertJsonPath('data.0.join_code', $course->join_code);

        $this->getJson("/api/courses/{$course->id}/students")
            ->assertOk()
            ->assertJsonPath('data.0.email', $student->email);
    }
}
