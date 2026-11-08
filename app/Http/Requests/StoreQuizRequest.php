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
        $course = $this->route('course');

        // API-24: asesmen ulang wajib punya kuis dasar terbit (jenis asesmen) di kelas yang sama.
        $baseRules = $this->input('kind') === 'reassessment'
            ? ['required', 'integer', Rule::exists('quizzes', 'id')->where(
                fn ($q) => $q->where('course_id', $course->id)
                    ->where('kind', 'assessment')
                    ->where('status', 'published')
            )]
            : ['prohibited'];

        return [
            'title' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::in(['assessment', 'reassessment'])],
            'base_quiz_id' => $baseRules,
            'max_attempts' => ['sometimes', 'integer', 'min:1', 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'kind.in' => 'Jenis kuis harus assessment atau reassessment.',
            'base_quiz_id.prohibited' => 'Kuis dasar hanya untuk asesmen ulang.',
            'base_quiz_id.required' => 'Asesmen ulang wajib memilih kuis dasar.',
            'base_quiz_id.exists' => 'Kuis dasar harus berupa asesmen yang sudah terbit di kelas yang sama.',
        ];
    }
}
