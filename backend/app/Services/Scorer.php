<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidStructuredOutput;
use App\Models\PromptTemplate;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\StructuredLlm;

/**
 * Produces the overall score, per-category breakdown, and explanation via the
 * `scoring` prompt template (schema-validated, one corrective retry).
 */
class Scorer
{
    private const CATEGORIES = ['hard_skills', 'soft_skills', 'experience', 'education', 'keywords'];

    public function __construct(private readonly StructuredLlm $structured) {}

    /**
     * @param  array<int, string>  $matched  matched requirement phrases
     * @param  array<int, string>  $gaps  unmatched requirement phrases
     * @return array{overall_score: int, breakdown: array<string, int>, explanation: string}
     */
    public function score(string $resumeText, string $jobDescription, array $matched, array $gaps): array
    {
        $template = PromptTemplate::query()
            ->where('slug', 'scoring')
            ->where('is_active', true)
            ->firstOrFail();

        $prompt = str_replace(
            ['{{matched}}', '{{gaps}}', '{{resume_text}}', '{{job_description}}'],
            [$this->bullets($matched), $this->bullets($gaps), $resumeText, $jobDescription],
            $template->content,
        );

        $request = new LlmRequest(
            prompt: $prompt,
            model: $template->model,
            operation: 'scoring',
            maxTokens: $template->max_tokens,
            temperature: $template->temperature,
        );

        return $this->structured->json($request, $this->validator());
    }

    /**
     * @param  array<int, string>  $items
     */
    private function bullets(array $items): string
    {
        if ($items === []) {
            return '(none)';
        }

        return implode('', array_map(static fn (string $item): string => "\n- {$item}", $items));
    }

    /**
     * @return callable(array<mixed>): array{overall_score: int, breakdown: array<string, int>, explanation: string}
     */
    private function validator(): callable
    {
        return function (array $decoded): array {
            $score = $decoded['overall_score'] ?? null;
            if (! is_int($score) && ! (is_numeric($score))) {
                throw new InvalidStructuredOutput('Expected a numeric "overall_score".');
            }
            $score = (int) $score;
            if ($score < 0 || $score > 100) {
                throw new InvalidStructuredOutput('"overall_score" must be between 0 and 100.');
            }

            $rawBreakdown = $decoded['breakdown'] ?? null;
            if (! is_array($rawBreakdown)) {
                throw new InvalidStructuredOutput('Expected a "breakdown" object.');
            }

            $breakdown = [];
            foreach (self::CATEGORIES as $category) {
                $value = $rawBreakdown[$category] ?? null;
                if (! is_numeric($value)) {
                    throw new InvalidStructuredOutput("Missing breakdown category: {$category}.");
                }
                $breakdown[$category] = max(0, min(100, (int) $value));
            }

            $explanation = $decoded['explanation'] ?? null;
            if (! is_string($explanation) || trim($explanation) === '') {
                throw new InvalidStructuredOutput('Expected a non-empty "explanation".');
            }

            return ['overall_score' => $score, 'breakdown' => $breakdown, 'explanation' => trim($explanation)];
        };
    }
}
