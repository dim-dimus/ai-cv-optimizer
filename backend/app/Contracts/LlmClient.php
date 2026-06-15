<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Llm\LlmRequest;
use App\Services\Llm\LlmResponse;

/**
 * Abstraction over the chat/completion provider (Anthropic Claude in production).
 * Controllers and jobs depend on this, never on the provider SDK directly.
 */
interface LlmClient
{
    public function complete(LlmRequest $request): LlmResponse;
}
