<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConceptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $course = $this->route('course');

        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('learning_concepts', 'name')->where('course_id', $course->id),
            ],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Nama konsep sudah dipakai di kelas ini.',
        ];
    }
}
