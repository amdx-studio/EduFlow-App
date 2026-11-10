<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $lesson = $this->route('lesson');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'content' => ['sometimes', 'required', 'string'],
            'kind' => ['sometimes', 'required', Rule::in(['regular', 'remedial'])],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_published' => ['sometimes', 'boolean'],
            'concept_ids' => ['sometimes', 'array'],
            'concept_ids.*' => [
                'integer', 'distinct',
                Rule::exists('learning_concepts', 'id')->where('course_id', $lesson->course_id),
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
                $lesson = $this->route('lesson');
                $kind = $this->input('kind', $lesson->kind);

                if ($kind !== 'remedial') {
                    return;
                }

                $count = $this->has('concept_ids')
                    ? count((array) $this->input('concept_ids', []))
                    : $lesson->concepts()->count();

                if ($count < 1) {
                    $validator->errors()->add('concept_ids', 'Materi remedial wajib terhubung ke minimal 1 konsep.');
                }
            },
        ];
    }
}
