<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            // Asesmen ulang (reassessment + base_quiz_id) ditambahkan di Phase 8.
            'kind' => ['required', Rule::in(['assessment'])],
            'base_quiz_id' => ['prohibited'],
            'max_attempts' => ['sometimes', 'integer', 'min:1', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'kind.in' => 'Jenis kuis yang tersedia saat ini hanya asesmen.',
            'base_quiz_id.prohibited' => 'Kuis dasar hanya untuk asesmen ulang.',
        ];
    }
}
