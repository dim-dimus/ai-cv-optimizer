<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\LlmClient;
use App\Exceptions\InvalidStructuredOutput;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\LlmResponse;
use App\Services\Llm\StructuredLlm;
use PHPUnit\Framework\TestCase;

class StructuredLlmTest extends TestCase
{
    private function llmReturning(string ...$texts): LlmClient
    {
        return new class($texts) implements LlmClient
        {
            /** @param array<int, string> $texts */
            public function __construct(private array $texts) {}

            public function complete(LlmRequest $request): LlmResponse
            {
                return new LlmResponse(array_shift($this->texts) ?? '', $request->model);
            }
        };
    }

    private function request(): LlmRequest
    {
        return new LlmRequest('prompt', 'claude-haiku-4-5', 'extract_skills');
    }

    /** @return callable(array<mixed>): array<mixed> */
    private function requireOk(): callable
    {
        return function (array $decoded): array {
            if (($decoded['ok'] ?? false) !== true) {
                throw new InvalidStructuredOutput('Missing ok flag.');
            }

            return $decoded;
        };
    }

    public function test_it_retries_once_on_invalid_output_then_succeeds(): void
    {
        $structured = new StructuredLlm($this->llmReturning('not json', '{"ok":true}'));

        $result = $structured->json($this->request(), $this->requireOk());

        $this->assertTrue($result['ok']);
    }

    public function test_it_strips_markdown_fences(): void
    {
        $structured = new StructuredLlm($this->llmReturning("```json\n{\"ok\":true}\n```"));

        $this->assertTrue($structured->json($this->request(), $this->requireOk())['ok']);
    }

    public function test_it_fails_after_two_invalid_responses(): void
    {
        $structured = new StructuredLlm($this->llmReturning('garbage', 'still garbage'));

        $this->expectException(InvalidStructuredOutput::class);

        $structured->json($this->request(), $this->requireOk());
    }
}
