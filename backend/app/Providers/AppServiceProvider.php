<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\EmbeddingClient;
use App\Contracts\LlmClient;
use App\Services\Llm\AnthropicClient;
use App\Services\Llm\CostCalculator;
use App\Services\Llm\FakeEmbeddingClient;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\LlmContext;
use App\Services\Llm\LoggingEmbeddingClient;
use App\Services\Llm\LoggingLlmClient;
use App\Services\Llm\VoyageClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Per-run context (user/analysis) for llm_logs; one queue job per process.
        $this->app->singleton(LlmContext::class);

        // Use the real providers when an API key is configured; otherwise fall back
        // to the deterministic offline stand-ins so dev/test run without keys. Both
        // are wrapped so every call is logged to llm_logs (CLAUDE.md).
        $this->app->bind(LlmClient::class, function ($app): LlmClient {
            $base = config('services.anthropic.key')
                ? $app->make(AnthropicClient::class)
                : $app->make(FakeLlmClient::class);

            return new LoggingLlmClient($base, $app->make(CostCalculator::class), $app->make(LlmContext::class));
        });

        $this->app->bind(EmbeddingClient::class, function ($app): EmbeddingClient {
            $base = config('services.voyage.key')
                ? $app->make(VoyageClient::class)
                : $app->make(FakeEmbeddingClient::class);

            return new LoggingEmbeddingClient(
                $base,
                $app->make(CostCalculator::class),
                $app->make(LlmContext::class),
                (string) config('services.voyage.model'),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
