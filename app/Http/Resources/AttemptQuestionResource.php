<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Soal untuk siswa SELAMA mengerjakan: tidak pernah memuat is_correct (TC-019, §10.5).
 */
class AttemptQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'points' => $this->points,
            'position' => $this->position,
            'options' => $this->options
                ->map(fn ($option) => ['id' => $option->id, 'body' => $option->body])
                ->values(),
        ];
    }
}
