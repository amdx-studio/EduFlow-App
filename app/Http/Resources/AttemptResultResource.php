<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Hasil percobaan TERKIRIM (API-33 submitted, API-35, API-36). Aman memuat kunci jawaban.
 * Mensyaratkan relasi: quiz.questions.concept, quiz.questions.options, answers, conceptResults.concept.
 */
class AttemptResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attempt = $this->resource;
        $questions = $attempt->quiz->questions->sortBy([['position', 'asc'], ['id', 'asc']])->values();
        $answers = $attempt->answers->keyBy('question_id');

        return [
            'attempt' => [
                'id' => $attempt->id,
                'quiz_id' => $attempt->quiz_id,
                'attempt_no' => $attempt->attempt_no,
                'score_percent' => (float) $attempt->score_percent,
                'earned_points' => $attempt->earned_points,
                'possible_points' => $attempt->possible_points,
                'submitted_at' => $attempt->submitted_at?->toIso8601ZuluString(),
            ],
            'concepts' => $attempt->conceptResults
                ->sortBy(fn ($r) => mb_strtolower($r->concept->name))
                ->values()
                ->map(fn ($r) => [
                    'concept_id' => $r->concept_id,
                    'concept' => $r->concept->name,
                    'correct' => $r->correct_count,
                    'total' => $r->total_count,
                    'percent' => (float) $r->mastery_percent,
                    'level' => $r->mastery_level,
                    'mastery_threshold_used' => $r->mastery_threshold_used,
                    'weak_threshold_used' => $r->weak_threshold_used,
                ]),
            'answers' => $questions->map(function ($question) use ($answers) {
                $answer = $answers->get($question->id);
                $correct = $question->options->firstWhere('is_correct', true);

                return [
                    'question_id' => $question->id,
                    'body' => $question->body,
                    'concept' => $question->concept->name,
                    'selected_option_id' => $answer?->option_id,
                    'correct_option_id' => $correct?->id,
                    'is_correct' => (bool) ($answer?->is_correct ?? false),
                    'explanation' => $question->explanation,
                    // Tambahan agar AnswerReview dapat menampilkan teks opsi.
                    'options' => $question->options
                        ->sortBy([['position', 'asc'], ['id', 'asc']])
                        ->values()
                        ->map(fn ($o) => ['id' => $o->id, 'body' => $o->body]),
                ];
            }),
            // Diisi RemediationService di Phase 8.
            'remediation' => [],
            'remediation_gaps' => [],
        ];
    }
}
