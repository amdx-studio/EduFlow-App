<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConceptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $concept = $this->route('concept');

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:120',
                Rule::unique('learning_concepts', 'name')
                    ->where('course_id', $concept->course_id)
                    ->ignore($concept->id),
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
