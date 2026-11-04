<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $course = $this->route('course');

        return [
            'title' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string'],
            'kind' => ['required', Rule::in(['regular', 'remedial'])],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_published' => ['sometimes', 'boolean'],
            'concept_ids' => ['sometimes', 'array'],
            'concept_ids.*' => [
                'integer', 'distinct',
                Rule::exists('learning_concepts', 'id')->where('course_id', $course->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'concept_ids.*.exists' => 'Konsep harus milik kelas yang sama.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if ($this->input('kind') === 'remedial' && count($this->input('concept_ids', [])) < 1) {
                    $validator->errors()->add('concept_ids', 'Materi remedial wajib terhubung ke minimal 1 konsep.');
                }
            },
        ];
    }
}
