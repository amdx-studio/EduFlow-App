<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class QuestionController extends Controller
{
    public function store(QuestionRequest $request, Quiz $quiz): JsonResponse
    {
        Gate::authorize('update', $quiz);

        if ($quiz->status !== 'draft') {
            return $this->notDraft();
        }

        $data = $request->validated();

        $question = DB::transaction(function () use ($quiz, $data) {
            $question = $quiz->questions()->create([
                'concept_id' => $data['concept_id'],
                'body' => $data['body'],
                'points' => $data['points'] ?? 1,
                'explanation' => $data['explanation'] ?? null,
                'position' => $data['position'] ?? ((int) $quiz->questions()->max('position') + 1),
            ]);

            $this->replaceOptions($question, $data['options']);

            return $question;
        });

        return (new QuestionResource($this->loaded($question)))->response()->setStatusCode(201);
    }

    public function update(QuestionRequest $request, Question $question)
    {
        Gate::authorize('update', $question->quiz);

        if ($question->quiz->status !== 'draft') {
            return $this->notDraft();
        }

        $data = $request->validated();

        DB::transaction(function () use ($question, $data) {
            $question->update([
                'concept_id' => $data['concept_id'],
                'body' => $data['body'],
                'points' => $data['points'] ?? 1,
                'explanation' => $data['explanation'] ?? null,
                'position' => $data['position'] ?? $question->position,
            ]);

            // Hapus dulu, baru buat: partial unique index membatasi satu opsi benar per soal.
            $question->options()->delete();
            $this->replaceOptions($question, $data['options']);
        });

        return new QuestionResource($this->loaded($question));
    }

    public function destroy(Question $question): JsonResponse
    {
        Gate::authorize('update', $question->quiz);

        if ($question->quiz->status !== 'draft') {
            return $this->notDraft();
        }

        $question->delete();

        return response()->json(null, 204);
    }

    private function replaceOptions(Question $question, array $options): void
    {
        foreach (array_values($options) as $i => $option) {
            $question->options()->create([
                'body' => $option['body'],
                'is_correct' => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'position' => $i + 1,
            ]);
        }
    }

    private function loaded(Question $question): Question
    {
        return $question->refresh()->load([
            'concept:id,name',
            'options' => fn ($q) => $q->orderBy('position')->orderBy('id'),
        ]);
    }

    private function notDraft(): JsonResponse
    {
        return response()->json([
            'message' => 'Struktur kuis hanya dapat diubah saat berstatus draft.',
        ], 409);
    }
}
