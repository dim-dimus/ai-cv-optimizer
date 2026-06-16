<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PromptTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PromptTemplate
 */
class PromptTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'content' => $this->content,
            'model' => $this->model,
            'max_tokens' => $this->max_tokens,
            'temperature' => $this->temperature,
            'is_active' => $this->is_active,
            'version' => $this->version,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
