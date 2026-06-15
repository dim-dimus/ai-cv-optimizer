<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Cap length for predictable downstream LLM cost (NFR-C2).
            'parsed_text' => ['required', 'string', 'min:1', 'max:50000'],
        ];
    }
}
