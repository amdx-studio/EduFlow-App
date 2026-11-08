<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RemediationAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'concept' => $this->concept->name,
            'lesson' => [
                'id' => $this->lesson->id,
                'title' => $this->lesson->title,
            ],
            'reason' => $this->reason,
            'source' => $this->source,
            'status' => $this->status,
            'note' => $this->note,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
