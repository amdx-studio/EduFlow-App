<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * HANYA untuk guru: memuat is_correct (kunci jawaban). Jangan dipakai untuk respons siswa.
 */
class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'concept_id' => $this->concept_id,
            'concept' => $this->whenLoaded('concept', fn () => [
                'id' => $this->concept->id,
                'name' => $this->concept->name,
            ]),
            'body' => $this->body,
            'points' => $this->points,
            'explanation' => $this->explanation,
            'position' => $this->position,
            'options' => $this->whenLoaded('options', fn () => $this->options
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'body' => $o->body,
                    'is_correct' => $o->is_correct,
                    'position' => $o->position,
                ])
                ->values()),
        ];
    }
}
