<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dipakai untuk API-28 (buat) dan API-29 (ubah; opsi diganti utuh).
 */
class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private function courseId(): int
    {
        $quiz = $this->route('quiz');

        return $quiz ? $quiz->course_id : $this->route('question')->quiz->course_id;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'concept_id' => [
                'required', 'integer',
                Rule::exists('learning_concepts', 'id')->where('course_id', $this->courseId()),
            ],
            'points' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'explanation' => ['nullable', 'string'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*.body' => ['required', 'string'],
            'options.*.is_correct' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'concept_id.exists' => 'Konsep harus milik kelas yang sama.',
            'options.min' => 'Soal harus memiliki minimal 2 opsi.',
            'options.max' => 'Soal dapat memiliki maksimal 6 opsi.',
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if ($validator->errors()->has('options')) {
                    return;
                }

                $options = $this->input('options');
                if (! is_array($options)) {
                    return;
                }

                $correct = collect($options)
                    ->filter(fn ($o) => is_array($o) && filter_var($o['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))
                    ->count();

                if ($correct !== 1) {
                    $validator->errors()->add('options', 'Soal harus memiliki tepat satu opsi benar.');
                }
            },
        ];
    }
}
