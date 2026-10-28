<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReactionRequest extends FormRequest
{
    /** The whole vocabulary. Anything else is rejected so the column stays trustworthy. */
    public const EMOJI = ['🔥', '❤️', '😂', '👍', '😮', '😢'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', Rule::in(self::EMOJI)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'emoji.in' => 'That reaction is not available.',
        ];
    }
}
