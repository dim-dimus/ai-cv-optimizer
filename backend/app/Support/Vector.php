<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Helpers for the pgvector text representation.
 */
final class Vector
{
    /**
     * Format a float array as a pgvector literal, e.g. "[0.1,0.2,...]".
     *
     * @param  array<int, float>  $vector
     */
    public static function toLiteral(array $vector): string
    {
        return '['.implode(',', array_map(static fn (float $v): string => (string) $v, $vector)).']';
    }
}
