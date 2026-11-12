<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateThreadRequest extends FormRequest
{
    /**
     * Dicek sebelum validasi, supaya yang tidak berhak mendapat 403 dan bukan 422.
     * Judul dan isi: penulis. Sematan dan kunci: guru pemilik (BR-020).
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $thread = $this->route('thread');

        if ($this->hasAny(['title', 'body']) && ! $user->can('update', $thread)) {
            return false;
        }

        if ($this->hasAny(['is_pinned', 'is_locked']) && ! $user->can('moderate', $thread)) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'body' => ['sometimes', 'required', 'string', 'max:10000'],
            'is_pinned' => ['sometimes', 'boolean'],
            'is_locked' => ['sometimes', 'boolean'],
        ];
    }
}
