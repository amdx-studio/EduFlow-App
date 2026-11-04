<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $attempt = $this->route('attempt');

        return [
            'question_id' => [
                'required', 'integer',
                Rule::exists('questions', 'id')->where('quiz_id', $attempt->quiz_id),
            ],
            // Wajib ada; null berarti menghapus jawaban (KT-13).
            'option_id' => [
                'present', 'nullable', 'integer',
                Rule::exists('question_options', 'id')->where('question_id', $this->input('question_id')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'question_id.exists' => 'Soal bukan bagian dari kuis ini.',
            'option_id.exists' => 'Opsi bukan milik soal ini.',
        ];
    }
}
