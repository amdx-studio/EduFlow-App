<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $inviteCode = config('eduflow.teacher_invite_code');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(['teacher', 'student'])],
            'teacher_code' => [
                Rule::requiredIf(fn () => $this->input('role') === 'teacher' && filled($inviteCode)),
                'nullable',
                'string',
            ],
        ];
    }

    /**
     * Validasi kode undangan guru (KT-10).
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $inviteCode = config('eduflow.teacher_invite_code');

                if ($this->input('role') !== 'teacher' || blank($inviteCode)) {
                    return;
                }

                if ($validator->errors()->has('teacher_code')) {
                    return;
                }

                if (! hash_equals((string) $inviteCode, (string) $this->input('teacher_code'))) {
                    $validator->errors()->add('teacher_code', 'Kode guru tidak valid.');
                }
            },
        ];
    }
}
