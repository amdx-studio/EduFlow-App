<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class ConceptTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    // TC-013
    public function test_tc013_teacher_crud_concepts_with_duplicate_and_in_use_rules(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $this->signIn($teacher);

        $id = $this->postJson("/api/courses/{$course->id}/concepts", ['name' => 'Pecahan', 'description' => 'Dasar'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Pecahan')
            ->json('data.id');

        $this->postJson("/api/courses/{$course->id}/concepts", ['name' => 'Pecahan'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->putJson("/api/concepts/{$id}", ['name' => 'Pecahan Biasa'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Pecahan Biasa');

        $this->getJson("/api/courses/{$course->id}/concepts")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Konsep terpakai soal tidak dapat dihapus (BR-003).
        $quiz = Quiz::forceCreate(['course_id' => $course->id, 'title' => 'Kuis 1']);
        Question::forceCreate([
            'quiz_id' => $quiz->id,
            'concept_id' => $id,
            'body' => 'Berapa 1/2 + 1/2?',
            'points' => 1,
            'position' => 1,
        ]);

        $this->deleteJson("/api/concepts/{$id}")->assertStatus(409);
        $this->assertDatabaseHas('learning_concepts', ['id' => $id]);

        // Konsep tanpa pemakaian dapat dihapus.
        $free = $this->concept($course, 'Desimal');
        $this->deleteJson("/api/concepts/{$free->id}")->assertNoContent();
        $this->assertDatabaseMissing('learning_concepts', ['id' => $free->id]);
    }

    public function test_same_concept_name_is_allowed_in_different_courses(): void
    {
        $teacher = $this->teacher();
        $a = $this->course($teacher);
        $b = $this->course($teacher);
        $this->concept($a, 'Pecahan');
        $this->signIn($teacher);

        $this->postJson("/api/courses/{$b->id}/concepts", ['name' => 'Pecahan'])->assertCreated();
    }

    public function test_update_keeping_own_name_is_valid(): void
    {
        $teacher = $this->teacher();
        $concept = $this->concept($this->course($teacher));
        $this->signIn($teacher);

        $this->putJson("/api/concepts/{$concept->id}", ['name' => 'Pecahan', 'description' => 'Baru'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Baru');
    }

    public function test_member_student_can_list_but_not_write(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $concept = $this->concept($course);
        $course->students()->attach($student->id, ['enrolled_at' => now()]);

        $this->signIn($student);

        $this->getJson("/api/courses/{$course->id}/concepts")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson("/api/courses/{$course->id}/concepts", ['name' => 'Baru'])->assertForbidden();
        $this->putJson("/api/concepts/{$concept->id}", ['name' => 'Ubah'])->assertForbidden();
        $this->deleteJson("/api/concepts/{$concept->id}")->assertForbidden();
    }

    public function test_non_member_and_other_teacher_get_404(): void
    {
        $owner = $this->teacher();
        $course = $this->course($owner);
        $concept = $this->concept($course);

        $this->signIn($this->student());
        $this->getJson("/api/courses/{$course->id}/concepts")->assertNotFound();

        $this->signIn($this->teacher());
        $this->getJson("/api/courses/{$course->id}/concepts")->assertNotFound();
        $this->postJson("/api/courses/{$course->id}/concepts", ['name' => 'X'])->assertNotFound();
        $this->putJson("/api/concepts/{$concept->id}", ['name' => 'X'])->assertNotFound();
        $this->deleteJson("/api/concepts/{$concept->id}")->assertNotFound();
        $this->assertDatabaseHas('learning_concepts', ['id' => $concept->id, 'name' => 'Pecahan']);
    }

    public function test_guest_gets_401(): void
    {
        $course = $this->course($this->teacher());

        $this->getJson("/api/courses/{$course->id}/concepts")->assertUnauthorized();
    }
}
