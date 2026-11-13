<?php

namespace Tests\Feature;

use App\Models\QuizAttempt;
use App\Services\RemediationService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Concerns\BuildsAttemptData;
use Tests\Concerns\TruncatesAfterTest;
use Tests\TestCase;

/**
 * UT-02 (OI-9): rollback submit dibuktikan TANPA pembungkus transaksi test.
 * DatabaseTruncation tidak membuka transaksi, jadi transaksi submit adalah yang terluar (commit/rollback nyata).
 */
class SubmitRollbackTest extends TestCase
{
    use BuildsAttemptData;
    use DatabaseTruncation;
    use TruncatesAfterTest;

    public function test_tc026_submit_rolls_back_for_real_without_test_transaction_wrapper(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = $this->course($teacher);
        $this->enroll($course, $student);
        $concept = $this->concept($course);
        $quiz = $this->quiz($course);
        $q1 = $this->question($quiz, $concept, 1);
        $q2 = $this->question($quiz, $concept, 2);

        $this->assertSame(0, DB::transactionLevel(), 'Test ini harus berjalan tanpa pembungkus transaksi.');

        $this->signIn($student);

        $id = $this->postJson("/api/quizzes/{$quiz->id}/attempts")->assertCreated()->json('data.id');
        $this->answer($id, $q1, true)->assertOk();
        $this->answer($id, $q2, true)->assertOk();

        $levelInsideSubmit = null;

        $this->mock(RemediationService::class, function (MockInterface $mock) use (&$levelInsideSubmit) {
            $mock->shouldReceive('generate')->once()->andReturnUsing(function () use (&$levelInsideSubmit) {
                $levelInsideSubmit = DB::transactionLevel();

                throw new RuntimeException('Kegagalan simulasi.');
            });
        });

        $this->postJson("/api/attempts/{$id}/submit")->assertStatus(500);

        // Transaksi submit adalah yang terluar: rollback-nya nyata, bukan savepoint.
        $this->assertSame(1, $levelInsideSubmit);
        $this->assertSame(0, DB::transactionLevel());

        $attempt = QuizAttempt::findOrFail($id);
        $this->assertSame('in_progress', $attempt->status);
        $this->assertNull($attempt->submitted_at);
        $this->assertNull($attempt->earned_points);
        $this->assertDatabaseCount('attempt_concept_results', 0);
        $this->assertDatabaseCount('remediation_assignments', 0);
        $this->assertSame(0, $attempt->answers()->where('is_correct', true)->count());
        $this->assertSame(0, (int) $attempt->answers()->sum('points_awarded'));

        // Percobaan tetap dapat dipakai setelah kegagalan.
        $this->answer($id, $q1, false)->assertOk();
    }
}
