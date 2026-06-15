<?php

declare(strict_types=1);

namespace App\Services\Llm;

/**
 * Provider-agnostic completion result, including token usage for cost/logging.
 */
final readonly class LlmResponse
{
    public function __construct(
        public string $text,
        public string $model,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {}
}
