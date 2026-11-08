<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAttemptData;
use Tests\TestCase;

class QuizBaseDeleteTest extends TestCase
{
    use BuildsAttemptData;
    use RefreshDatabase;

    public function test_deleting_a_base_quiz_used_by_a_reassessment_returns_409_and_keeps_it(): void
    {
        $teacher = $this->teacher();
        $course = $this->course($teacher);
        $base = $this->quiz($course);
        $reassessment = $this->quiz($course, [
            'kind' => 'reassessment',
            'base_quiz_id' => $base->id,
            'status' => 'draft',
            'published_at' => null,
        ]);

        // Kuis dasar kembali ke draf (diizinkan karena belum ada percobaan), lalu dicoba dihapus.
        $base->update(['status' => 'draft', 'published_at' => null]);

        $this->signIn($teacher);

        $this->deleteJson("/api/quizzes/{$base->id}")->assertStatus(409);
        $this->assertDatabaseHas('quizzes', ['id' => $base->id]);

        $this->deleteJson("/api/quizzes/{$reassessment->id}")->assertNoContent();
        $this->deleteJson("/api/quizzes/{$base->id}")->assertNoContent();
        $this->assertDatabaseMissing('quizzes', ['id' => $base->id]);
    }
}
