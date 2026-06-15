<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResumeRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'mimes:pdf,docx',
                'mimetypes:application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'max:5120', // 5 MB, in kilobytes
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'The resume must be a PDF or DOCX file.',
            'file.mimetypes' => 'The resume must be a PDF or DOCX file.',
            'file.max' => 'The resume may not be larger than 5 MB.',
            'file.uploaded' => 'The resume could not be uploaded. It may exceed the server upload limit — try a file under 5 MB.',
        ];
    }
}
