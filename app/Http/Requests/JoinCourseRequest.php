<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('join_code'))) {
            $this->merge(['join_code' => strtoupper(trim($this->input('join_code')))]);
        }
    }

    public function rules(): array
    {
        return [
            'join_code' => ['required', 'string'],
        ];
    }
}
