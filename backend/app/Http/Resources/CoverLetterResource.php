<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CoverLetter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CoverLetter
 */
class CoverLetterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'tone' => $this->tone,
            'length' => $this->length,
            'language' => $this->language,
            'content' => $this->content,
            'error_message' => $this->error_message,
        ];
    }
}
