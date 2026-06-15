<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Contracts\EmbeddingClient;
use App\Exceptions\ProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Real Voyage AI embedding client (POST /v1/embeddings, voyage-3-large → 1024 dims).
 * Per-call timeout and capped backoff retries on 429/5xx (NFR-R1).
 */
class VoyageClient implements EmbeddingClient
{
    private const RETRYABLE = [429, 500, 502, 503, 529];

    public function embed(string $text): array
    {
        return $this->embedBatch([$text])[0];
    }

    public function embedBatch(array $texts): array
    {
        $config = config('services.voyage');

        try {
            $response = Http::withToken($config['key'])
                ->timeout($config['timeout'])
                ->retry(3, 300, fn (Throwable $e): bool => $this->shouldRetry($e), throw: false)
                ->throw()
                ->post($config['base_url'].'/v1/embeddings', [
                    'model' => $config['model'],
                    'input' => array_values($texts),
                    'input_type' => 'document',
                ]);
        } catch (RequestException|ConnectionException $e) {
            throw new ProviderException('Voyage request failed: '.$e->getMessage(), previous: $e);
        }

        $rows = $response->json('data') ?? [];

        // Preserve input order via the per-row `index`.
        usort($rows, static fn (array $a, array $b): int => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        return array_map(
            static fn (array $row): array => array_map('floatval', $row['embedding'] ?? []),
            $rows,
        );
    }

    private function shouldRetry(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        return $e instanceof RequestException
            && $e->response !== null
            && in_array($e->response->status(), self::RETRYABLE, true);
    }
}
