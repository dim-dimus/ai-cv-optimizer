<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Contracts\LlmClient;
use App\Exceptions\InvalidStructuredOutput;

/**
 * Runs an LLM request that must return JSON, validates it, and performs exactly
 * one corrective retry on invalid output before giving up (NFR-R2).
 */
class StructuredLlm
{
    public function __construct(private readonly LlmClient $llm) {}

    /**
     * @template T
     *
     * @param  callable(array<mixed>): T  $validate  returns the validated value or throws InvalidStructuredOutput
     * @return T
     *
     * @throws InvalidStructuredOutput when output is invalid after one corrective retry
     */
    public function json(LlmRequest $request, callable $validate): mixed
    {
        $response = $this->llm->complete($request);

        try {
            return $validate($this->decode($response->text));
        } catch (InvalidStructuredOutput $first) {
            $retry = $this->llm->complete($this->corrective($request, $first->getMessage()));

            // A second failure propagates — the caller fails the job.
            return $validate($this->decode($retry->text));
        }
    }

    /**
     * @return array<mixed>
     */
    private function decode(string $text): array
    {
        $decoded = json_decode($this->extractJson($text), true);

        if (! is_array($decoded)) {
            throw new InvalidStructuredOutput('Response was not valid JSON.');
        }

        return $decoded;
    }

    /**
     * Pull the JSON object out of the response, tolerating ```json fences and any
     * prose the model wraps around it.
     */
    private function extractJson(string $text): string
    {
        $trimmed = trim($text);

        // Remove a leading ```json / ``` fence and trailing ``` if present.
        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```[a-zA-Z]*\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }

        // Otherwise narrow to the outermost { ... } so surrounding prose is ignored.
        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            return substr($trimmed, $start, $end - $start + 1);
        }

        return $trimmed;
    }

    private function corrective(LlmRequest $request, string $error): LlmRequest
    {
        return new LlmRequest(
            prompt: $request->prompt
                ."\n\nYour previous response was invalid: {$error}\n"
                .'Return ONLY valid JSON matching the required schema, with no prose or markdown.',
            model: $request->model,
            operation: $request->operation,
            maxTokens: $request->maxTokens,
            temperature: $request->temperature,
            system: $request->system,
        );
    }
}
