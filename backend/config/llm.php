<?php

declare(strict_types=1);

return [

    /*
    | Cosine-similarity threshold for treating a job requirement as matched by a
    | resume skill (LLM-INTEGRATION.md). similarity = 1 - distance.
    */
    'match_threshold' => (float) env('MATCH_THRESHOLD', 0.75),

    /*
    | Per-model prices in USD per million tokens (MTok). Used to compute the
    | cost_usd written to llm_logs. Verify against current provider pricing.
    */
    'pricing' => [
        'claude-sonnet-4-6' => ['input' => 3.00, 'output' => 15.00],
        'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00],
        'voyage-3-large' => ['input' => 0.18, 'output' => 0.0],
    ],

];
