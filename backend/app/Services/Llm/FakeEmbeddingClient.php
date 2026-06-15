<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Contracts\EmbeddingClient;

/**
 * Deterministic, offline embedding stand-in for local/test environments.
 * The same text always maps to the same unit-length vector, so semantic-cache
 * behaviour can be exercised without calling Voyage. Replaced by the real
 * provider in Phase 3.
 */
final class FakeEmbeddingClient implements EmbeddingClient
{
    public function embed(string $text): array
    {
        // Seed a PRNG from the text so results are stable across runs.
        mt_srand(crc32($text));

        $vector = [];
        $sumSquares = 0.0;
        for ($i = 0; $i < self::DIMENSIONS; $i++) {
            $value = (mt_rand() / mt_getrandmax()) * 2 - 1; // [-1, 1]
            $vector[$i] = $value;
            $sumSquares += $value * $value;
        }
        mt_srand();

        // Normalise to unit length so cosine distance is meaningful.
        $norm = sqrt($sumSquares) ?: 1.0;

        return array_map(static fn (float $v): float => $v / $norm, $vector);
    }

    public function embedBatch(array $texts): array
    {
        return array_map(fn (string $text): array => $this->embed($text), $texts);
    }
}
