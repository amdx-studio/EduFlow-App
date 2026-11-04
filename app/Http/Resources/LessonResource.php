<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();

        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'kind' => $this->kind,
            'position' => $this->position,
            'is_published' => $this->is_published,
            'content' => $this->when(array_key_exists('content', $attributes), fn () => $this->content),
            'concepts' => $this->whenLoaded('concepts', fn () => $this->concepts
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
                ->values()),
            'completed' => $this->when(array_key_exists('completed', $attributes), fn () => (bool) $this->completed),
        ];
    }
}
