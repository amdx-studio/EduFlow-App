<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualRemediationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $attempt = $this->route('attempt');
        $attempt->loadMissing('quiz');

        return [
            'concept_id' => [
                'required', 'integer',
                Rule::exists('attempt_concept_results', 'concept_id')->where('attempt_id', $attempt->id),
            ],
            'lesson_id' => [
                'required', 'integer',
                Rule::exists('lessons', 'id')
                    ->where('course_id', $attempt->quiz->course_id)
                    ->where('kind', 'remedial')
                    ->where('is_published', true),
                Rule::unique('remediation_assignments', 'lesson_id')
                    ->where('attempt_id', $attempt->id)
                    ->where('concept_id', (int) $this->input('concept_id')),
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'concept_id.exists' => 'Konsep tidak ada pada hasil percobaan ini.',
            'lesson_id.exists' => 'Materi harus berjenis remedial, sudah terbit, dan berada di kelas yang sama.',
            'lesson_id.unique' => 'Materi ini sudah ditugaskan untuk konsep dan percobaan ini.',
        ];
    }
}
