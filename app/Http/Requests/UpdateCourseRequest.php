<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string'],
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
            $course = $this->route('course');
            $mastery = (int) $this->input('mastery_threshold', $course->mastery_threshold);
            $weak = (int) $this->input('weak_threshold', $course->weak_threshold);
            if ($weak >= $mastery) {
                $validator->errors()->add('weak_threshold', 'Ambang lemah harus lebih kecil dari ambang penguasaan.');
            }
        }];
    }
}
