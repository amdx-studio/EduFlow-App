<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RemediationAssignmentResource;
use App\Models\AttemptConceptResult;
use App\Models\Course;
use App\Models\RemediationAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
}
