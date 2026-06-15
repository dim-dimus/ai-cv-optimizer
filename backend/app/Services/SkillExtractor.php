<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\LlmClient;
use App\Models\PromptTemplate;
use App\Services\Llm\LlmRequest;
use RuntimeException;

/**
 * Extracts a flat list of skill / experience phrases from resume text by
 * running the `extract_skills` prompt template through the LLM client.
 *
 * Structured-output validation here is intentionally lightweight; the one
 * corrective-retry policy (NFR-R2) is added with the real provider in Phase 3.
 */
class SkillExtractor
{
    public function __construct(private readonly LlmClient $llm) {}

    /**
     * @return array<int, string>
     */
    public function extract(string $resumeText): array
    {
        $template = PromptTemplate::query()
            ->where('slug', 'extract_skills')
            ->where('is_active', true)
            ->firstOrFail();

        $prompt = str_replace('{{resume_text}}', $resumeText, $template->content);

        $response = $this->llm->complete(new LlmRequest(
            prompt: $prompt,
            model: $template->model,
            maxTokens: $template->max_tokens,
            temperature: $template->temperature,
        ));

        return $this->parseSkills($response->text);
    }

    /**
     * @return array<int, string>
     */
    private function parseSkills(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded) || ! isset($decoded['skills']) || ! is_array($decoded['skills'])) {
            throw new RuntimeException('Skill extraction returned malformed output.');
        }

        $skills = [];
        foreach ($decoded['skills'] as $skill) {
            if (! is_string($skill)) {
                continue;
            }
            $trimmed = trim($skill);
            if ($trimmed !== '') {
                $skills[] = $trimmed;
            }
        }

        // De-duplicate case-insensitively while preserving order.
        $unique = [];
        foreach ($skills as $skill) {
            $unique[mb_strtolower($skill)] ??= $skill;
        }

        return array_values($unique);
    }
}
