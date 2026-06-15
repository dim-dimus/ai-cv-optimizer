<?php

declare(strict_types=1);

namespace App\Services\Llm;

/**
 * Per-run holder for the user / analysis a sequence of LLM calls belongs to.
 * Jobs set it once; the logging decorators read it when writing llm_logs rows.
 * Bound as a singleton (one queue job runs in its own process/container).
 */
final class LlmContext
{
    public ?int $userId = null;

    public ?int $analysisId = null;

    public function for(?int $userId, ?int $analysisId): void
    {
        $this->userId = $userId;
        $this->analysisId = $analysisId;
    }

    public function reset(): void
    {
        $this->userId = null;
        $this->analysisId = null;
    }
}
