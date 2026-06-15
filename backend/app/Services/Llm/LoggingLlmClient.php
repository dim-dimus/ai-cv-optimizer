<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Contracts\LlmClient;
use App\Models\LlmLog;
use Throwable;

/**
 * Decorates any LlmClient to write an llm_logs row for every call — success or
 * failure — with provider, model, operation, tokens, cost, latency, and status
 * (CLAUDE.md observability rule). Stored payloads are minimal: they would
 * otherwise contain personal data (CV text).
 */
class LoggingLlmClient implements LlmClient
{
    public function __construct(
        private readonly LlmClient $inner,
        private readonly CostCalculator $cost,
        private readonly LlmContext $context,
    ) {}

    public function complete(LlmRequest $request): LlmResponse
    {
        $startedAt = microtime(true);

        try {
            $response = $this->inner->complete($request);
            $this->record($request, $response, $startedAt, 'success', null);

            return $response;
        } catch (Throwable $e) {
            $this->record($request, null, $startedAt, 'failed', $e->getMessage());
            throw $e;
        }
    }

    private function record(LlmRequest $request, ?LlmResponse $response, float $startedAt, string $status, ?string $error): void
    {
        $model = $response->model ?? $request->model;
        $input = $response->inputTokens ?? 0;
        $output = $response->outputTokens ?? 0;

        LlmLog::create([
            'user_id' => $this->context->userId,
            'analysis_id' => $this->context->analysisId,
            'provider' => 'anthropic',
            'model' => $model,
            'operation' => $request->operation,
            'prompt_tokens' => $input,
            'completion_tokens' => $output,
            'total_tokens' => $input + $output,
            'cost_usd' => $this->cost->cost($model, $input, $output),
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'status' => $status,
            'error' => $error,
            'request_meta' => ['operation' => $request->operation, 'prompt_chars' => mb_strlen($request->prompt)],
            'response_meta' => null,
        ]);
    }
}
