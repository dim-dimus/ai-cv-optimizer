<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\EmbeddingClient;
use App\Contracts\LlmClient;
use App\Services\Llm\FakeEmbeddingClient;
use App\Services\Llm\FakeLlmClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Phase 2 uses deterministic, offline stand-ins so the resume → skills →
        // embeddings pipeline runs without provider keys. Phase 3 swaps these for
        // the real Anthropic / Voyage clients (with llm_logs) based on config.
        $this->app->bind(LlmClient::class, FakeLlmClient::class);
        $this->app->bind(EmbeddingClient::class, FakeEmbeddingClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
