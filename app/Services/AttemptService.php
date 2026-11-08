<?php

namespace App\Services;

use App\Exceptions\AttemptNotInProgressException;
use App\Exceptions\GateLockedException;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Siklus percobaan (§8.1): mulai, simpan jawaban, kirim.
 * Pemanggil menjamin siswa adalah anggota kelas dan kuis berstatus terbit (lewat route binding).
 */
class AttemptService
{
    public function __construct(
        private readonly GateService $gate,
        private readonly MasteryService $mastery,
        private readonly RemediationService $remediation,
    ) {}

    /**
     * @return array{0: QuizAttempt, 1: bool} percobaan dan penanda baru dibuat (true) atau dilanjutkan (false)
     *
     * @throws GateLockedException
     */
    public function start(User $student, Quiz $quiz): array
    {
        $gate = $this->gate->check($student, $quiz);

        if ($gate['status'] === 'locked') {
            throw new GateLockedException($gate);
        }

        $existing = $this->activeAttempt($student, $quiz);

        if ($existing) {
            return [$existing, false];
        }

        try {
            $attempt = DB::transaction(function () use ($student, $quiz) {
                $next = (int) QuizAttempt::where('quiz_id', $quiz->id)
                    ->where('student_id', $student->id)
                    ->max('attempt_no') + 1;

                return QuizAttempt::create([
                    'quiz_id' => $quiz->id,
                    'student_id' => $student->id,
                    'attempt_no' => $next,
                    'status' => 'in_progress',
                    'started_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            // Permintaan lain membuat percobaan aktif lebih dulu: lanjutkan yang itu.
            $existing = $this->activeAttempt($student, $quiz);

            if ($existing) {
                return [$existing, false];
            }

            throw $e;
        }

        return [$attempt->refresh(), true];
    }

    /**
     * Upsert satu jawaban. option_id null menghapus jawaban.
     * Validasi kepemilikan soal dan opsi dilakukan oleh FormRequest.
     *
     * @throws AttemptNotInProgressException
     */
    public function saveAnswer(QuizAttempt $attempt, int $questionId, ?int $optionId): QuizAnswer
    {
        return DB::transaction(function () use ($attempt, $questionId, $optionId) {
            $locked = QuizAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'in_progress') {
                throw new AttemptNotInProgressException;
            }

            return QuizAnswer::updateOrCreate(
                ['attempt_id' => $locked->id, 'question_id' => $questionId],
                ['option_id' => $optionId, 'is_correct' => false, 'points_awarded' => 0]
            );
        });
    }

    /**
     * Kirim dan nilai dalam satu transaksi (§8.1). Kegagalan apa pun membatalkan semuanya.
     *
     * @throws AttemptNotInProgressException
     */
    public function submit(QuizAttempt $attempt): QuizAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $locked = QuizAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'in_progress') {
                throw new AttemptNotInProgressException;
            }

            $questions = $locked->quiz->questions()->with('options')->get();
            $answers = $locked->answers()->get()->keyBy('question_id');

            $earned = 0;
            $possible = 0;

            foreach ($questions as $question) {
                $answer = $answers->get($question->id);
                $optionId = $answer?->option_id;

                $correct = $optionId !== null
                    && (bool) $question->options->firstWhere('id', $optionId)?->is_correct;

                $awarded = $correct ? (int) $question->points : 0;
                $earned += $awarded;
                $possible += (int) $question->points;

                QuizAnswer::updateOrCreate(
                    ['attempt_id' => $locked->id, 'question_id' => $question->id],
                    ['option_id' => $optionId, 'is_correct' => $correct, 'points_awarded' => $awarded]
                );
            }

            $locked->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'earned_points' => $earned,
                'possible_points' => $possible,
                'score_percent' => round(MasteryService::rawPercent($earned, $possible), 2),
            ]);

            $this->mastery->compute($locked->refresh());
            $this->remediation->generate($locked);

            return $locked->refresh();
        });
    }

    private function activeAttempt(User $student, Quiz $quiz): ?QuizAttempt
    {
        return QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->first();
    }
}
