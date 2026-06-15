<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Abstraction over the embedding provider (Voyage voyage-3-large in production,
 * 1024 dimensions). Vectors are returned as plain float arrays.
 */
interface EmbeddingClient
{
    /**
     * Vector dimension. Fixed per the pgvector column type — changing it is a migration.
     */
    public const int DIMENSIONS = 1024;

    /**
     * @return array<int, float> a single 1024-dimension vector
     */
    public function embed(string $text): array;

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>> one vector per input, in order
     */
    public function embedBatch(array $texts): array;
}
