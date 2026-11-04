<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\LearningConcept;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\RemediationAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonTest extends TestCase
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
        ])->refresh();
    }

    private function concept(Course $course, string $name = 'Pecahan'): LearningConcept
    {
        return LearningConcept::create(['course_id' => $course->id, 'name' => $name]);
    }

    private function lesson(Course $course, array $extra = []): Lesson
    {
        return Lesson::create(array_merge([
            'course_id' => $course->id,
            'title' => 'Pengenalan Pecahan',
            'content' => "# Pecahan\n\nIsi materi.",
            'kind' => 'regular',
            'position' => 0,
            'is_published' => true,
        ], $extra));
    }

    private function enroll(Course $course, User $student): void
    {
        $course->students()->attach($student->id, ['enrolled_at' => now()]);
    }

    // TC-014
    public function test_tc014_lesson_concept_links_and_validation(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $other = $this->course($teacher);
        $own = $this->concept($course);
        $foreign = $this->concept($other, 'Asing');
        $this->signIn($teacher);

        $base = ['title' => 'Materi', 'content' => 'Isi', 'kind' => 'remedial'];

        // Konsep kelas lain ditolak (BR-004).
        $this->postJson("/api/courses/{$course->id}/lessons", $base + ['concept_ids' => [$foreign->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('concept_ids.0');

        // Remedial tanpa konsep ditolak.
        $this->postJson("/api/courses/{$course->id}/lessons", $base)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('concept_ids');

        $this->postJson("/api/courses/{$course->id}/lessons", $base + ['concept_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('concept_ids');

        // Valid.
        $this->postJson("/api/courses/{$course->id}/lessons", $base + ['concept_ids' => [$own->id], 'is_published' => true])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'remedial')
            ->assertJsonPath('data.concepts.0.id', $own->id)
            ->assertJsonPath('data.is_published', true);

        $this->assertDatabaseHas('concept_lesson', ['concept_id' => $own->id]);
    }

    public function test_regular_lesson_without_concepts_is_valid(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $this->signIn($teacher);

        $this->postJson("/api/courses/{$course->id}/lessons", ['title' => 'Intro', 'content' => 'Isi', 'kind' => 'regular'])
            ->assertCreated()
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.concepts', []);
    }

    public function test_update_syncs_concepts_and_keeps_remedial_rule(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $a = $this->concept($course, 'A');
        $b = $this->concept($course, 'B');
        $lesson = $this->lesson($course, ['kind' => 'remedial']);
        $lesson->concepts()->sync([$a->id]);
        $this->signIn($teacher);

        $this->putJson("/api/lessons/{$lesson->id}", ['concept_ids' => [$b->id]])
            ->assertOk()
            ->assertJsonPath('data.concepts.0.id', $b->id)
            ->assertJsonCount(1, 'data.concepts');

        $this->putJson("/api/lessons/{$lesson->id}", ['concept_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('concept_ids');

        // Mengubah jenis ke remedial tanpa konsep tersimpan ditolak.
        $regular = $this->lesson($course, ['title' => 'Reguler']);
        $this->putJson("/api/lessons/{$regular->id}", ['kind' => 'remedial'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('concept_ids');

        $this->putJson("/api/lessons/{$regular->id}", ['title' => 'Judul Baru'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Judul Baru');
    }

    public function test_teacher_lists_all_lessons_with_kind_filter_without_content(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $concept = $this->concept($course);
        $this->lesson($course, ['title' => 'Reguler', 'position' => 1]);
        $draft = $this->lesson($course, ['title' => 'Remedial Draf', 'kind' => 'remedial', 'is_published' => false, 'position' => 2]);
        $draft->concepts()->sync([$concept->id]);
        $this->signIn($teacher);

        $this->getJson("/api/courses/{$course->id}/lessons")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Reguler')
            ->assertJsonMissingPath('data.0.content');

        $this->getJson("/api/courses/{$course->id}/lessons?kind=remedial")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.concepts.0.name', 'Pecahan');

        $this->getJson("/api/lessons/{$draft->id}")
            ->assertOk()
            ->assertJsonPath('data.content', $draft->content);
    }

    public function test_student_sees_only_published_lessons(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $published = $this->lesson($course, ['title' => 'Terbit']);
        $draft = $this->lesson($course, ['title' => 'Draf', 'is_published' => false]);

        $this->signIn($student);

        $this->getJson("/api/courses/{$course->id}/lessons")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Terbit')
            ->assertJsonPath('data.0.completed', false);

        $this->getJson("/api/lessons/{$published->id}")
            ->assertOk()
            ->assertJsonPath('data.content', $published->content);

        $this->getJson("/api/lessons/{$draft->id}")->assertNotFound();
    }

    // TC-012 (versi materi, ditunda dari Phase 4)
    public function test_tc012_non_member_student_cannot_access_lessons(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $lesson = $this->lesson($course);

        $this->signIn($this->student());

        $this->getJson("/api/courses/{$course->id}/lessons")
            ->assertNotFound()
            ->assertJsonMissing(['title' => $lesson->title]);

        $this->getJson("/api/lessons/{$lesson->id}")
            ->assertNotFound()
            ->assertJsonMissing(['content' => $lesson->content]);

        $this->postJson("/api/lessons/{$lesson->id}/complete")->assertNotFound();
    }

    public function test_only_owner_can_write_lessons(): void
    {
        $owner = $this->teacher();
        $course = $this->course($owner);
        $lesson = $this->lesson($course);
        $member = $this->student();
        $this->enroll($course, $member);

        $this->signIn($this->teacher());
        $this->postJson("/api/courses/{$course->id}/lessons", ['title' => 'X', 'content' => 'Y', 'kind' => 'regular'])->assertNotFound();
        $this->putJson("/api/lessons/{$lesson->id}", ['title' => 'Ubah'])->assertNotFound();
        $this->deleteJson("/api/lessons/{$lesson->id}")->assertNotFound();

        $this->signIn($member);
        $this->postJson("/api/courses/{$course->id}/lessons", ['title' => 'X', 'content' => 'Y', 'kind' => 'regular'])->assertForbidden();
        $this->putJson("/api/lessons/{$lesson->id}", ['title' => 'Ubah'])->assertForbidden();
        $this->deleteJson("/api/lessons/{$lesson->id}")->assertForbidden();

        $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'title' => 'Pengenalan Pecahan']);
    }

    // TC-015
    public function test_tc015_complete_twice_keeps_single_progress_row(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $lesson = $this->lesson($course);

        $this->signIn($student);

        $first = $this->postJson("/api/lessons/{$lesson->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.assignments_completed', 0)
            ->json('data.completed_at');

        $second = $this->postJson("/api/lessons/{$lesson->id}/complete")
            ->assertOk()
            ->json('data.completed_at');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('lesson_progress', 1);

        $this->getJson("/api/courses/{$course->id}/lessons")
            ->assertJsonPath('data.0.completed', true);
    }

    public function test_complete_rejects_draft_and_teacher(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $draft = $this->lesson($course, ['is_published' => false]);
        $published = $this->lesson($course, ['title' => 'Terbit']);

        $this->signIn($student);
        $this->postJson("/api/lessons/{$draft->id}/complete")->assertNotFound();

        $this->signIn($teacher);
        $this->postJson("/api/lessons/{$published->id}/complete")->assertForbidden();

        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_delete_lesson_conflicts_when_used_by_assignment(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $used = $this->lesson($course, ['kind' => 'remedial']);
        $used->concepts()->sync([$concept->id]);
        $free = $this->lesson($course, ['title' => 'Bebas']);

        $quiz = Quiz::forceCreate(['course_id' => $course->id, 'title' => 'Kuis 1']);
        $attempt = QuizAttempt::forceCreate([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'attempt_no' => 1,
            'started_at' => now(),
        ]);
        RemediationAssignment::forceCreate([
            'attempt_id' => $attempt->id,
            'student_id' => $student->id,
            'concept_id' => $concept->id,
            'lesson_id' => $used->id,
            'source' => 'rule',
            'reason' => 'Uji',
            'assigned_at' => now(),
        ]);

        $this->signIn($teacher);

        $this->deleteJson("/api/lessons/{$used->id}")->assertStatus(409);
        $this->assertDatabaseHas('lessons', ['id' => $used->id]);

        $this->deleteJson("/api/lessons/{$free->id}")->assertNoContent();
        $this->assertDatabaseMissing('lessons', ['id' => $free->id]);
    }
}
