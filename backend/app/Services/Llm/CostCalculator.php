<?php

declare(strict_types=1);

namespace App\Services\Llm;

/**
 * Computes USD cost from token counts and per-model rates (config/llm.php).
 */
class CostCalculator
{
    public function cost(string $model, int $inputTokens, int $outputTokens): float
    {
        /** @var array<string, array{input: float, output: float}> $pricing */
        $pricing = config('llm.pricing', []);
        $rates = $pricing[$model] ?? ['input' => 0.0, 'output' => 0.0];

        return ($inputTokens / 1_000_000) * $rates['input']
            + ($outputTokens / 1_000_000) * $rates['output'];
    }
}
