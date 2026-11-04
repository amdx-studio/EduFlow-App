<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConceptRequest;
use App\Http\Requests\UpdateConceptRequest;
use App\Http\Resources\ConceptResource;
use App\Models\AttemptConceptResult;
use App\Models\Course;
use App\Models\LearningConcept;
use App\Models\RemediationAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ConceptController extends Controller
{
    public function index(Course $course)
    {
        return ConceptResource::collection(
            $course->concepts()->orderBy('name')->get()
        );
    }

    public function store(StoreConceptRequest $request, Course $course): JsonResponse
    {
        Gate::authorize('update', $course);

        $concept = $course->concepts()->create($request->validated());

        return (new ConceptResource($concept))->response()->setStatusCode(201);
    }

    public function update(UpdateConceptRequest $request, LearningConcept $concept): ConceptResource
    {
        Gate::authorize('update', $concept);

        $concept->update($request->validated());

        return new ConceptResource($concept);
    }

    public function destroy(LearningConcept $concept): JsonResponse
    {
        Gate::authorize('delete', $concept);

        $inUse = $concept->questions()->exists()
            || AttemptConceptResult::where('concept_id', $concept->id)->exists()
            || RemediationAssignment::where('concept_id', $concept->id)->exists();

        if ($inUse) {
            return response()->json([
                'message' => 'Konsep tidak dapat dihapus karena sudah dipakai soal, hasil, atau remedial.',
            ], 409);
        }

        $concept->delete();

        return response()->json(null, 204);
    }
}
