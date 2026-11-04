<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'max_attempts' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'status' => ['sometimes', Rule::in(['draft'])],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status hanya dapat diubah ke draft. Gunakan publikasi untuk menerbitkan kuis.',
        ];
    }
}
