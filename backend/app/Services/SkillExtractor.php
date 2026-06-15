<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidStructuredOutput;
use App\Models\PromptTemplate;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\StructuredLlm;

/**
 * Extracts a flat list of skill / experience phrases from resume text via the
 * `extract_skills` prompt template, with schema validation + one corrective
 * retry (StructuredLlm).
 */
class SkillExtractor
{
    public function __construct(private readonly StructuredLlm $structured) {}

    /**
     * @return array<int, string>
     */
    public function extract(string $resumeText): array
    {
        $template = PromptTemplate::query()
            ->where('slug', 'extract_skills')
            ->where('is_active', true)
            ->firstOrFail();

        $request = new LlmRequest(
            prompt: str_replace('{{resume_text}}', $resumeText, $template->content),
            model: $template->model,
            operation: 'extract_skills',
            maxTokens: $template->max_tokens,
            temperature: $template->temperature,
        );

        return $this->structured->json($request, $this->validator());
    }

    /**
     * @return callable(array<mixed>): array<int, string>
     */
    private function validator(): callable
    {
        return function (array $decoded): array {
            if (! isset($decoded['skills']) || ! is_array($decoded['skills'])) {
                throw new InvalidStructuredOutput('Expected a "skills" array.');
            }

            $skills = [];
            foreach ($decoded['skills'] as $skill) {
                if (is_string($skill) && trim($skill) !== '') {
                    $skills[mb_strtolower(trim($skill))] ??= trim($skill);
                }
            }

            return array_values($skills);
        };
    }
}
