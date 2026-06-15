<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Contracts\EmbeddingClient;
use App\Models\LlmLog;
use Throwable;

/**
 * Decorates any EmbeddingClient to write an llm_logs row per call. Token counts
 * are estimated from text length (the interface returns vectors, not usage);
 * embeddings are near-free so this is sufficient for cost tracking.
 */
class LoggingEmbeddingClient implements EmbeddingClient
{
    public function __construct(
        private readonly EmbeddingClient $inner,
        private readonly CostCalculator $cost,
        private readonly LlmContext $context,
        private readonly string $model,
    ) {}

    public function embed(string $text): array
    {
        return $this->logged([$text], fn (): array => [$this->inner->embed($text)])[0];
    }

    public function embedBatch(array $texts): array
    {
        return $this->logged($texts, fn (): array => $this->inner->embedBatch($texts));
    }

    /**
     * @param  array<int, string>  $texts
     * @param  callable(): array<int, array<int, float>>  $call
     * @return array<int, array<int, float>>
     */
    private function logged(array $texts, callable $call): array
    {
        $startedAt = microtime(true);

        try {
            $vectors = $call();
            $this->record($texts, $startedAt, 'success', null);

            return $vectors;
        } catch (Throwable $e) {
            $this->record($texts, $startedAt, 'failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param  array<int, string>  $texts
     */
    private function record(array $texts, float $startedAt, string $status, ?string $error): void
    {
        $tokens = (int) ceil(array_sum(array_map(static fn (string $t): int => mb_strlen($t), $texts)) / 4);

        LlmLog::create([
            'user_id' => $this->context->userId,
            'analysis_id' => $this->context->analysisId,
            'provider' => 'voyage',
            'model' => $this->model,
            'operation' => 'embedding',
            'prompt_tokens' => $tokens,
            'completion_tokens' => 0,
            'total_tokens' => $tokens,
            'cost_usd' => $this->cost->cost($this->model, $tokens, 0),
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'status' => $status,
            'error' => $error,
            'request_meta' => ['inputs' => count($texts)],
            'response_meta' => null,
        ]);
    }
}
