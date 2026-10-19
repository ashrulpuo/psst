<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetUsernameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => mb_strtolower(trim($this->input('username')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'regex:/^[a-z0-9_.]{3,24}$/',
                Rule::notIn(config('psst.reserved_usernames')),
                Rule::unique('users', 'username'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Use 3–24 lowercase letters, numbers, dots or underscores.',
            'username.unique' => 'That username is taken. Try another.',
            'username.not_in' => 'That username is reserved. Try another.',
        ];
    }
}
