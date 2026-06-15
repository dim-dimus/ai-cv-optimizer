<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Contracts\LlmClient;
use App\Exceptions\ProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Real Anthropic Claude client (POST /v1/messages). Per-call timeout and capped
 * backoff retries on 429/5xx (NFR-R1). Used in production when an API key is set;
 * the FakeLlmClient stands in otherwise.
 */
class AnthropicClient implements LlmClient
{
    private const RETRYABLE = [429, 500, 502, 503, 529];

    public function complete(LlmRequest $request): LlmResponse
    {
        $config = config('services.anthropic');

        $payload = array_filter([
            'model' => $request->model,
            'max_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
            'system' => $request->system,
            'messages' => [['role' => 'user', 'content' => $request->prompt]],
        ], static fn ($value) => $value !== null);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $config['key'],
                'anthropic-version' => $config['version'],
                'content-type' => 'application/json',
            ])
                ->timeout($config['timeout'])
                ->retry(3, 300, fn (Throwable $e): bool => $this->shouldRetry($e), throw: false)
                ->throw()
                ->post($config['base_url'].'/v1/messages', $payload);
        } catch (RequestException|ConnectionException $e) {
            throw new ProviderException('Anthropic request failed: '.$e->getMessage(), previous: $e);
        }

        $data = $response->json();
        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'];
            }
        }

        return new LlmResponse(
            text: $text,
            model: $data['model'] ?? $request->model,
            inputTokens: (int) ($data['usage']['input_tokens'] ?? 0),
            outputTokens: (int) ($data['usage']['output_tokens'] ?? 0),
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
