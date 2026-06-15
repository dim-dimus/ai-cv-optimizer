<?php

declare(strict_types=1);

namespace App\Services\Llm;

/**
 * Provider-agnostic request for a single text completion.
 */
final readonly class LlmRequest
{
    public function __construct(
        public string $prompt,
        public string $model,
        public int $maxTokens = 1024,
        public float $temperature = 0.2,
        public ?string $system = null,
    ) {}
}
