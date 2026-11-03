<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'mastery_threshold' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'weak_threshold' => ['sometimes', 'integer', 'min:1'],
            'min_questions_per_concept' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $mastery = (int) $this->input('mastery_threshold', 70);
            $weak = (int) $this->input('weak_threshold', 50);
            if ($weak >= $mastery) {
                $validator->errors()->add('weak_threshold', 'Ambang lemah harus lebih kecil dari ambang penguasaan.');
            }
        }];
    }
}
