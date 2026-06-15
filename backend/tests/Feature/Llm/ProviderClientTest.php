<?php

declare(strict_types=1);

namespace Tests\Feature\Llm;

use App\Exceptions\ProviderException;
use App\Services\Llm\AnthropicClient;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\VoyageClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderClientTest extends TestCase
{
    public function test_anthropic_client_parses_text_and_usage(): void
    {
        Http::fake([
            '*/v1/messages' => Http::response([
                'model' => 'claude-haiku-4-5',
                'content' => [['type' => 'text', 'text' => '{"skills":["Laravel"]}']],
                'usage' => ['input_tokens' => 120, 'output_tokens' => 8],
            ]),
        ]);

        $response = (new AnthropicClient)->complete(new LlmRequest(
            prompt: 'extract skills',
            model: 'claude-haiku-4-5',
            operation: 'extract_skills',
        ));

        $this->assertSame('{"skills":["Laravel"]}', $response->text);
        $this->assertSame(120, $response->inputTokens);
        $this->assertSame(8, $response->outputTokens);
    }

    public function test_anthropic_client_throws_provider_exception_on_server_error(): void
    {
        Http::fake(['*/v1/messages' => Http::response(['error' => 'boom'], 500)]);

        $this->expectException(ProviderException::class);

        (new AnthropicClient)->complete(new LlmRequest(
            prompt: 'x',
            model: 'claude-haiku-4-5',
            operation: 'extract_skills',
        ));
    }

    public function test_voyage_client_returns_vectors_in_input_order(): void
    {
        Http::fake([
            '*/v1/embeddings' => Http::response([
                'data' => [
                    ['index' => 1, 'embedding' => [0.1, 0.2]],
                    ['index' => 0, 'embedding' => [0.9, 0.8]],
                ],
                'usage' => ['total_tokens' => 4],
            ]),
        ]);

        $vectors = (new VoyageClient)->embedBatch(['first', 'second']);

        // Reordered by `index` so vectors line up with inputs.
        $this->assertSame([0.9, 0.8], $vectors[0]);
        $this->assertSame([0.1, 0.2], $vectors[1]);
    }
}
