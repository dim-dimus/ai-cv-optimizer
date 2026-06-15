<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnalysisRequest extends FormRequest
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
            // Non-empty, reasonable minimum, capped for predictable LLM cost (NFR-C2).
            'job_description' => ['required', 'string', 'min:30', 'max:20000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'job_description.min' => 'Please paste the full job description (at least 30 characters).',
        ];
    }
}
