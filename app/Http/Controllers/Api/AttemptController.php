<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveAnswerRequest;
use App\Http\Resources\AttemptQuestionResource;
use App\Http\Resources\AttemptResource;
use App\Http\Resources\AttemptResultResource;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\AttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AttemptController extends Controller
{
    /** API-32: mulai atau lanjutkan. 409 bila gate terkunci (GateLockedException). */
    public function store(Request $request, Quiz $quiz, AttemptService $service): JsonResponse
    {
        [$attempt, $created] = $service->start($request->user(), $quiz);

        return (new AttemptResource($attempt))->response()->setStatusCode($created ? 201 : 200);
    }

    /** API-33: in_progress -> soal tanpa kunci + jawaban tersimpan; submitted -> payload hasil. */
    public function show(Request $request, QuizAttempt $attempt)
    {
        if ($attempt->status === 'submitted') {
            return $this->resultFor($attempt);
        }

        $attempt->load([
            'quiz.questions' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'quiz.questions.options' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'answers',
        ]);

        return response()->json(['data' => [
            'attempt' => (new AttemptResource($attempt))->resolve($request),
            'questions' => AttemptQuestionResource::collection($attempt->quiz->questions)->resolve($request),
            'answers' => $attempt->answers
                ->map(fn ($a) => ['question_id' => $a->question_id, 'option_id' => $a->option_id])
                ->values(),
        ]]);
    }

    /** API-34: simpan satu jawaban (option_id null menghapus). */
    public function answer(SaveAnswerRequest $request, QuizAttempt $attempt, AttemptService $service): JsonResponse
    {
        Gate::authorize('answer', $attempt);

        $optionId = $request->validated('option_id');

        $answer = $service->saveAnswer(
            $attempt,
            (int) $request->validated('question_id'),
            $optionId !== null ? (int) $optionId : null
        );

        return response()->json(['data' => [
            'question_id' => $answer->question_id,
            'option_id' => $answer->option_id,
        ]]);
    }

    /** API-35: kirim dan nilai dalam satu transaksi. */
    public function submit(QuizAttempt $attempt, AttemptService $service)
    {
        Gate::authorize('answer', $attempt);

        return $this->resultFor($service->submit($attempt));
    }

    /** API-36: hasil. 409 bila belum terkirim. */
    public function result(QuizAttempt $attempt)
    {
        if ($attempt->status !== 'submitted') {
            return response()->json(['message' => 'Percobaan belum dikirim.'], 409);
        }

        return $this->resultFor($attempt);
    }

    private function resultFor(QuizAttempt $attempt): AttemptResultResource
    {
        $attempt->load([
            'quiz.questions.concept',
            'quiz.questions.options',
            'answers',
            'conceptResults.concept',
        ]);

        return new AttemptResultResource($attempt);
    }
}
