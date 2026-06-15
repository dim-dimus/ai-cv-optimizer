<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateCoverLetterRequest extends FormRequest
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
            'tone' => ['sometimes', 'string', 'in:professional,friendly,enthusiastic,formal'],
            'length' => ['sometimes', 'string', 'in:short,medium,long'],
            'language' => ['sometimes', 'string', 'max:20'],
        ];
    }
}
