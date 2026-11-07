<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLessonRequest;
use App\Http\Requests\UpdateLessonRequest;
use App\Http\Resources\LessonResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\RemediationAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LessonController extends Controller
{
    public function index(Request $request, Course $course)
    {
        $request->validate(['kind' => ['nullable', 'in:regular,remedial']]);

        $user = $request->user();

        $query = $course->lessons()
            ->select(['id', 'course_id', 'title', 'kind', 'position', 'is_published'])
            ->with('concepts:id,name')
            ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->input('kind')))
            ->orderBy('position')
            ->orderBy('id');

        if ($user->role === 'teacher') {
            Gate::authorize('update', $course);
        } else {
            $query->where('is_published', true)
                ->withExists(['progress as completed' => fn ($q) => $q->where('student_id', $user->id)]);
        }

        return LessonResource::collection($query->get());
    }

    public function store(StoreLessonRequest $request, Course $course): JsonResponse
    {
        Gate::authorize('update', $course);

        $data = $request->validated();

        $lesson = DB::transaction(function () use ($course, $data) {
            $lesson = $course->lessons()->create(collect($data)->except('concept_ids')->all());
            $lesson->concepts()->sync($data['concept_ids'] ?? []);

            return $lesson;
        });

        $lesson->refresh()->load('concepts:id,name');

        return (new LessonResource($lesson))->response()->setStatusCode(201);
    }

    public function show(Request $request, Lesson $lesson): LessonResource
    {
        $user = $request->user();

        $lesson->load('concepts:id,name');

        if ($user->role !== 'teacher') {
            $lesson->loadExists(['progress as completed' => fn ($q) => $q->where('student_id', $user->id)]);
        }

        return new LessonResource($lesson);
    }

    public function update(UpdateLessonRequest $request, Lesson $lesson): LessonResource
    {
        Gate::authorize('update', $lesson);

        $data = $request->validated();

        DB::transaction(function () use ($lesson, $data) {
            $lesson->update(collect($data)->except('concept_ids')->all());

            if (array_key_exists('concept_ids', $data)) {
                $lesson->concepts()->sync($data['concept_ids']);
            }
        });

        $lesson->refresh()->load('concepts:id,name');

        return new LessonResource($lesson);
    }

    public function destroy(Lesson $lesson): JsonResponse
    {
        Gate::authorize('delete', $lesson);

        if (RemediationAssignment::where('lesson_id', $lesson->id)->exists()) {
            return response()->json([
                'message' => 'Materi tidak dapat dihapus karena sudah dipakai sebagai remedial.',
            ], 409);
        }

        $lesson->delete();

        return response()->json(null, 204);
    }

    public function complete(Request $request, Lesson $lesson): JsonResponse
    {
        $student = $request->user();

        [$progress, $completed] = DB::transaction(function () use ($lesson, $student) {
            $progress = LessonProgress::firstOrCreate(
                ['lesson_id' => $lesson->id, 'student_id' => $student->id],
                ['completed_at' => now()]
            );

            // KT-11: assignment 'assigned' milik siswa ini untuk materi ini menjadi 'completed'.
            // Idempoten: panggilan berikutnya tidak menemukan baris 'assigned' (hasil 0).
            $completed = RemediationAssignment::where('student_id', $student->id)
                ->where('lesson_id', $lesson->id)
                ->where('status', 'assigned')
                ->update(['status' => 'completed', 'completed_at' => now()]);

            return [$progress, $completed];
        });

        return response()->json([
            'data' => [
                'completed_at' => $progress->completed_at->toIso8601ZuluString(),
                'assignments_completed' => $completed,
            ],
        ]);
    }
}
