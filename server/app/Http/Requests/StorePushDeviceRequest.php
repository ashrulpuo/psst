<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePushDeviceRequest extends FormRequest
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
            // FCM registration tokens are opaque URL-safe strings (letters, digits, ':', '-', '_', '.').
            'token' => ['required', 'string', 'max:4096', 'regex:/^[A-Za-z0-9:_.\-]+$/'],
            'platform' => ['required', 'in:ios,android'],
        ];
    }
}
