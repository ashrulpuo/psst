<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportMessageRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:500', 'not_regex:/\x00/'],
        ];
    }
}
