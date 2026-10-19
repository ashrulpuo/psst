<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SignInRequest extends FormRequest
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
        return [
            'id_token' => ['required', 'string', 'max:4096'],
            // Client-generated per attempt and embedded in the provider request, binding the token to this sign-in.
            'nonce' => ['required', 'string', 'min:16', 'max:128'],
            // Apple only reveals the name on the very first authorization, so the client may pass it along.
            'name' => ['nullable', 'string', 'max:80'],
            'device_name' => ['sometimes', 'string', 'max:60'],
        ];
    }
}
