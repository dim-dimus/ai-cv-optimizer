<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromptTemplateRequest extends FormRequest
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
            'content' => ['required', 'string'],
            'model' => ['required', 'string', 'max:100'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:8192'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
