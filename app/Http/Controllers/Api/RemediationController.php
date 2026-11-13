<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManualRemediationRequest;
use App\Http\Resources\RemediationAssignmentResource;
use App\Models\AttemptConceptResult;
use App\Models\Course;
use App\Models\QuizAttempt;
use App\Models\RemediationAssignment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemediationController extends Controller
{
    // API-37: remedial milik siswa yang login pada satu kelas.
    public function index(Request $request, Course $course): AnonymousResourceCollection
    {
        $items = RemediationAssignment::query()
            ->where('student_id', $request->user()->id)
            ->whereHas('lesson', fn ($q) => $q->where('course_id', $course->id))
            ->with(['concept', 'lesson'])
            ->orderBy('concept_id')
            ->orderBy('id')
            ->get();

        $percents = AttemptConceptResult::whereIn('attempt_id', $items->pluck('attempt_id')->unique())
            ->get()
            ->mapWithKeys(fn ($r) => ["{$r->attempt_id}:{$r->concept_id}" => (float) $r->mastery_percent]);

        $items->each(fn ($item) => $item->setAttribute(
            'mastery_percent',
            $percents->get("{$item->attempt_id}:{$item->concept_id}")
        ));

        return RemediationAssignmentResource::collection($items);
    }

    // API-38: guru menambah remedial manual pada percobaan tersubmit di kelasnya (§8.9, BR-018).
    public function store(StoreManualRemediationRequest $request, QuizAttempt $attempt)
    {
        $data = $request->validated();

        try {
            $assignment = DB::transaction(fn () => RemediationAssignment::create([
                'attempt_id' => $attempt->id,
                'student_id' => $attempt->student_id,
                'concept_id' => $data['concept_id'],
                'lesson_id' => $data['lesson_id'],
                'source' => 'teacher',
                'reason' => 'Ditambahkan oleh guru: '.$request->user()->name,
                'status' => 'assigned',
                'assigned_by' => $request->user()->id,
                'note' => $data['note'] ?? null,
                'assigned_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            // Balapan: request lain menyisipkan baris yang sama setelah validasi lolos (UT-07).
            throw ValidationException::withMessages(['lesson_id' => ['Materi ini sudah ditugaskan untuk konsep dan percobaan ini.']]);
        }

        $percent = AttemptConceptResult::where('attempt_id', $attempt->id)
            ->where('concept_id', $assignment->concept_id)
            ->value('mastery_percent');

        $assignment->load(['concept', 'lesson']);
        $assignment->setAttribute('mastery_percent', $percent === null ? null : (float) $percent);

        return (new RemediationAssignmentResource($assignment))->response()->setStatusCode(201);
    }
}
