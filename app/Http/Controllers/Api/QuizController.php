<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuizRequest;
use App\Http\Requests\UpdateQuizRequest;
use App\Http\Resources\QuizResource;
use App\Models\Course;
use App\Models\Quiz;
use App\Services\GateService;
use App\Services\QuizPublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class QuizController extends Controller
{
    public function index(Request $request, Course $course, GateService $gate)
    {
        $user = $request->user();

        $query = $course->quizzes()->orderBy('id');

        if ($user->role === 'teacher') {
            Gate::authorize('update', $course);

            return QuizResource::collection($query->withCount(['questions', 'attempts'])->get());
        }

        $quizzes = $query
            ->where('status', 'published')
            ->withCount(['attempts as attempts_used' => fn ($q) => $q->where('student_id', $user->id)])
            ->get()
            ->each(function (Quiz $quiz) use ($gate, $user, $course) {
                $quiz->setRelation('course', $course);
                $quiz->setAttribute('gate', $gate->check($user, $quiz));
            });

        return QuizResource::collection($quizzes);
    }

    public function store(StoreQuizRequest $request, Course $course): JsonResponse
    {
        Gate::authorize('update', $course);

        $quiz = $course->quizzes()->create($request->validated());
        $quiz->refresh()->loadCount(['questions', 'attempts']);

        return (new QuizResource($quiz))->response()->setStatusCode(201);
    }

    public function show(Request $request, Quiz $quiz, GateService $gate): QuizResource
    {
        $user = $request->user();

        if ($user->role === 'teacher') {
            $quiz->loadCount(['questions', 'attempts']);
            $quiz->load([
                'questions' => fn ($q) => $q->orderBy('position')->orderBy('id'),
                'questions.concept:id,name',
                'questions.options' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            ]);

            return new QuizResource($quiz);
        }

        // Siswa: metadata, gate, dan riwayat percobaan sendiri. Tanpa soal.
        $quiz->loadCount(['attempts as attempts_used' => fn ($q) => $q->where('student_id', $user->id)]);
        $quiz->load(['attempts' => fn ($q) => $q->where('student_id', $user->id)->orderBy('attempt_no')]);
        $quiz->setAttribute('gate', $gate->check($user, $quiz));

        return new QuizResource($quiz);
    }

    public function update(UpdateQuizRequest $request, Quiz $quiz)
    {
        Gate::authorize('update', $quiz);

        $data = $request->validated();

        if (($data['status'] ?? null) === 'draft' && $quiz->status === 'published') {
            if ($quiz->attempts()->exists()) {
                return response()->json([
                    'message' => 'Kuis tidak dapat dikembalikan ke draft karena sudah ada percobaan.',
                ], 409);
            }

            $data['published_at'] = null;
        }

        $quiz->update($data);
        $quiz->refresh()->loadCount(['questions', 'attempts']);

        return new QuizResource($quiz);
    }

    public function destroy(Quiz $quiz): JsonResponse
    {
        Gate::authorize('delete', $quiz);

        if ($quiz->status !== 'draft' || $quiz->attempts()->exists()) {
            return response()->json([
                'message' => 'Hanya kuis draft tanpa percobaan yang dapat dihapus.',
            ], 409);
        }

        if ($quiz->reassessments()->exists()) {
            return response()->json([
                'message' => 'Kuis tidak dapat dihapus karena dipakai sebagai kuis dasar asesmen ulang.',
            ], 409);
        }

        $quiz->delete();

        return response()->json(null, 204);
    }

    public function publish(Quiz $quiz, QuizPublishService $service)
    {
        Gate::authorize('update', $quiz);

        if ($quiz->status === 'published') {
            return response()->json(['message' => 'Kuis sudah terbit.'], 409);
        }

        $warnings = $service->publish($quiz);

        $quiz->refresh()->loadCount(['questions', 'attempts']);

        return (new QuizResource($quiz))->additional(['warnings' => $warnings]);
    }
}
