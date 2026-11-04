<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();

        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'kind' => $this->kind,
            'base_quiz_id' => $this->base_quiz_id,
            'max_attempts' => $this->max_attempts,
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601ZuluString(),
            'questions_count' => $this->when(array_key_exists('questions_count', $attributes), fn () => (int) $this->questions_count),
            'attempts_count' => $this->when(array_key_exists('attempts_count', $attributes), fn () => (int) $this->attempts_count),
            // Hanya dimuat untuk guru (memuat is_correct).
            'questions' => $this->whenLoaded('questions', fn () => QuestionResource::collection($this->questions)),
        ];
    }
}
