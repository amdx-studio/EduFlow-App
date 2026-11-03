<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOwner = $user && $user->id === $this->teacher_id;
        $hasCount = array_key_exists('students_count', $this->resource->getAttributes());

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'mastery_threshold' => $this->mastery_threshold,
            'weak_threshold' => $this->weak_threshold,
            'min_questions_per_concept' => $this->min_questions_per_concept,
            'is_archived' => $this->is_archived,
            'created_at' => $this->created_at?->toIso8601String(),
            'join_code' => $this->when($isOwner, fn () => $this->join_code),
            'students_count' => $this->when($isOwner && $hasCount, fn () => (int) $this->students_count),
            'teacher_name' => $this->when(! $isOwner && $this->relationLoaded('teacher'), fn () => $this->teacher->name),
        ];
    }
}
