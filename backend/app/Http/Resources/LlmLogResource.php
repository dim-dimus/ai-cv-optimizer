<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LlmLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-only — includes the minimal request/response metadata (NFR-O1).
 *
 * @mixin LlmLog
 */
class LlmLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'analysis_id' => $this->analysis_id,
            'provider' => $this->provider,
            'model' => $this->model,
            'operation' => $this->operation,
            'prompt_tokens' => $this->prompt_tokens,
            'completion_tokens' => $this->completion_tokens,
            'total_tokens' => $this->total_tokens,
            'cost_usd' => (float) $this->cost_usd,
            'latency_ms' => $this->latency_ms,
            'status' => $this->status,
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
