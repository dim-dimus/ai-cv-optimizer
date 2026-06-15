<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BulletSuggestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BulletSuggestion
 */
class BulletSuggestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_text' => $this->original_text,
            'suggested_text' => $this->suggested_text,
            'rationale' => $this->rationale,
            'status' => $this->status,
            'edited_text' => $this->edited_text,
            'position' => $this->position,
        ];
    }
}
