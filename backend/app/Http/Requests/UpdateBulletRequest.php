<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBulletRequest extends FormRequest
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
            'status' => ['required', 'in:accepted,rejected,edited'],
            'edited_text' => ['nullable', 'required_if:status,edited', 'string', 'max:2000'],
        ];
    }
}
