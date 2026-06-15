<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidStructuredOutput;
use App\Models\PromptTemplate;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\StructuredLlm;

/**
 * Extracts categorized requirements from a job description via the
 * `extract_requirements` prompt template (schema-validated, one corrective retry).
 */
class JdRequirementExtractor
{
    private const CATEGORIES = ['hard_skill', 'soft_skill', 'experience', 'education', 'keyword'];

    public function __construct(private readonly StructuredLlm $structured) {}

    /**
     * @return array<int, array{text: string, category: ?string}>
     */
    public function extract(string $jobDescription): array
    {
        $template = PromptTemplate::query()
            ->where('slug', 'extract_requirements')
            ->where('is_active', true)
            ->firstOrFail();

        $request = new LlmRequest(
            prompt: str_replace('{{job_description}}', $jobDescription, $template->content),
            model: $template->model,
            operation: 'extract_requirements',
            maxTokens: $template->max_tokens,
            temperature: $template->temperature,
        );

        return $this->structured->json($request, $this->validator());
    }

    /**
     * @return callable(array<mixed>): array<int, array{text: string, category: ?string}>
     */
    private function validator(): callable
    {
        return function (array $decoded): array {
            if (! isset($decoded['requirements']) || ! is_array($decoded['requirements'])) {
                throw new InvalidStructuredOutput('Expected a "requirements" array.');
            }

            $requirements = [];
            $seen = [];
            foreach ($decoded['requirements'] as $item) {
                if (! is_array($item) || ! isset($item['text']) || ! is_string($item['text'])) {
                    continue;
                }
                $text = trim($item['text']);
                $key = mb_strtolower($text);
                if ($text === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $category = is_string($item['category'] ?? null) && in_array($item['category'], self::CATEGORIES, true)
                    ? $item['category']
                    : null;

                $requirements[] = ['text' => $text, 'category' => $category];
            }

            if ($requirements === []) {
                throw new InvalidStructuredOutput('No valid requirements found.');
            }

            return $requirements;
        };
    }
}
