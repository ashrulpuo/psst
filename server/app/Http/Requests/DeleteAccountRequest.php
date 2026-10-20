<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['confirmation' => ['required', 'string']];
    }

    /** The user must type their username (or email before they have one): a deliberate, named confirmation. */
    public function after(): array
    {
        return [function (Validator $validator) {
            $user = $this->user();
            $expected = $user->username ?? $user->email;

            if (! hash_equals(mb_strtolower((string) $expected), mb_strtolower(trim((string) $this->input('confirmation'))))) {
                $validator->errors()->add('confirmation', 'That does not match. Type your username exactly to confirm.');
            }
        }];
    }
}
