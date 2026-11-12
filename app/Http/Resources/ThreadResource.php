<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThreadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();

        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            // Daftar (API-43) tidak memuat isi; detail dan respons tulis memuatnya.
            'body' => $this->when(array_key_exists('body', $attributes), fn () => $this->body),
            'is_pinned' => $this->is_pinned,
            'is_locked' => $this->is_locked,
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'name' => $this->author->name,
                'role' => $this->author->role,
            ]),
            'posts_count' => $this->when(array_key_exists('posts_count', $attributes), fn () => (int) $this->posts_count),
            'posts' => $this->whenLoaded('posts', fn () => PostResource::collection($this->posts)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
