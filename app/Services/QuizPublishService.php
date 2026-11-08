<?php

namespace App\Services;

use App\Models\Quiz;
use Illuminate\Validation\ValidationException;

/**
 * Validasi dan publikasi kuis (§8.8, FR-010).
 */
class QuizPublishService
{
    public const MIN_OPTIONS = 2;

    public const MAX_OPTIONS = 6;

    /**
     * @return array<int, array<string, mixed>> peringatan (tidak menghalangi publikasi)
     *
     * @throws ValidationException bila ada kesalahan; status kuis tidak berubah
     */
    public function publish(Quiz $quiz): array
    {
        $quiz->load(['course', 'baseQuiz.questions', 'questions.concept', 'questions.options']);

        $errors = $this->errors($quiz);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $warnings = $this->warnings($quiz);

        $quiz->update(['status' => 'published', 'published_at' => now()]);

        return $warnings;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function errors(Quiz $quiz): array
    {
        $errors = $this->baseQuizErrors($quiz);

        if ($quiz->questions->isEmpty()) {
            $errors['quiz'][] = 'Kuis harus memiliki minimal 1 soal.';
        }

        foreach ($quiz->questions->sortBy('position') as $question) {
            $key = "questions.{$question->id}";
            $label = "Soal urutan {$question->position}";

            if ($question->concept === null) {
                $errors[$key][] = "{$label} tidak memiliki konsep.";
            } elseif ((int) $question->concept->course_id !== (int) $quiz->course_id) {
                $errors[$key][] = "{$label} memakai konsep dari kelas lain.";
            }

            $options = $question->options->count();
            if ($options < self::MIN_OPTIONS || $options > self::MAX_OPTIONS) {
                $errors[$key][] = sprintf(
                    '%s harus memiliki %d sampai %d opsi (saat ini %d).',
                    $label, self::MIN_OPTIONS, self::MAX_OPTIONS, $options
                );
            }

            $correct = $question->options->where('is_correct', true)->count();
            if ($correct !== 1) {
                $errors[$key][] = "{$label} harus memiliki tepat satu opsi benar (saat ini {$correct}).";
            }

            if ($question->points < 1) {
                $errors[$key][] = "{$label} harus bernilai minimal 1 poin.";
            }
        }

        return $errors;
    }

    /**
     * Aturan asesmen ulang (§8.8): kuis dasar terbit, jenis asesmen, kelas sama, berbagi konsep.
     *
     * @return array<string, array<int, string>>
     */
    private function baseQuizErrors(Quiz $quiz): array
    {
        if ($quiz->kind !== 'reassessment') {
            return [];
        }

        $base = $quiz->baseQuiz;

        if ($base === null) {
            return ['base_quiz_id' => ['Asesmen ulang harus tertaut ke kuis dasar.']];
        }

        $errors = [];

        if ($base->status !== 'published') {
            $errors['base_quiz_id'][] = 'Kuis dasar belum terbit.';
        }

        if ($base->kind !== 'assessment') {
            $errors['base_quiz_id'][] = 'Kuis dasar harus berjenis asesmen.';
        }

        if ((int) $base->course_id !== (int) $quiz->course_id) {
            $errors['base_quiz_id'][] = 'Kuis dasar berada di kelas lain.';
        } elseif ($quiz->questions->isNotEmpty()) {
            $shared = $quiz->questions->pluck('concept_id')
                ->intersect($base->questions->pluck('concept_id'));

            if ($shared->isEmpty()) {
                $errors['base_quiz_id'][] = 'Asesmen ulang harus memuat minimal 1 konsep dari kuis dasar.';
            }
        }

        return $errors;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function warnings(Quiz $quiz): array
    {
        $minimum = (int) $quiz->course->min_questions_per_concept;

        return $quiz->questions
            ->groupBy('concept_id')
            ->filter(fn ($questions) => $questions->count() < $minimum)
            ->map(function ($questions, $conceptId) use ($minimum) {
                $name = $questions->first()->concept->name;
                $count = $questions->count();

                return [
                    'concept_id' => (int) $conceptId,
                    'concept' => $name,
                    'questions_count' => $count,
                    'minimum' => $minimum,
                    'message' => sprintf('Konsep "%s" hanya memiliki %d soal (minimum %d).', $name, $count, $minimum),
                ];
            })
            ->values()
            ->all();
    }
}
