<?php

declare(strict_types=1);

namespace App\Services\Llm;

/**
 * Provider-agnostic request for a single text completion.
 *
 * `operation` identifies the logical task (extract_skills | extract_requirements |
 * scoring | bullet_rewrite | cover_letter) and is recorded in llm_logs.
 */
final readonly class LlmRequest
{
    public function __construct(
        public string $prompt,
        public string $model,
        public string $operation,
        public int $maxTokens = 1024,
        public float $temperature = 0.2,
        public ?string $system = null,
    ) {}
}
