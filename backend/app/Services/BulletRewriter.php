<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidStructuredOutput;
use App\Models\PromptTemplate;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\StructuredLlm;

/**
 * Finds weak resume bullets and rewrites them for the job via the `bullet_rewrite`
 * prompt template (schema-validated, one corrective retry).
 */
class BulletRewriter
{
    public function __construct(private readonly StructuredLlm $structured) {}

    /**
     * @return array<int, array{original: string, suggested: string, rationale: ?string}>
     */
    public function rewrite(string $resumeText, string $jobDescription): array
    {
        $template = PromptTemplate::query()
            ->where('slug', 'bullet_rewrite')
            ->where('is_active', true)
            ->firstOrFail();

        $request = new LlmRequest(
            prompt: str_replace(
                ['{{resume_text}}', '{{job_description}}'],
                [$resumeText, $jobDescription],
                $template->content,
            ),
            model: $template->model,
            operation: 'bullet_rewrite',
            maxTokens: $template->max_tokens,
            temperature: $template->temperature,
        );

        return $this->structured->json($request, $this->validator());
    }

    /**
     * @return callable(array<mixed>): array<int, array{original: string, suggested: string, rationale: ?string}>
     */
    private function validator(): callable
    {
        return function (array $decoded): array {
            if (! isset($decoded['bullets']) || ! is_array($decoded['bullets'])) {
                throw new InvalidStructuredOutput('Expected a "bullets" array.');
            }

            $bullets = [];
            foreach ($decoded['bullets'] as $bullet) {
                if (! is_array($bullet)) {
                    continue;
                }
                $original = is_string($bullet['original'] ?? null) ? trim($bullet['original']) : '';
                $suggested = is_string($bullet['suggested'] ?? null) ? trim($bullet['suggested']) : '';
                if ($original === '' || $suggested === '') {
                    continue;
                }

                $bullets[] = [
                    'original' => $original,
                    'suggested' => $suggested,
                    'rationale' => is_string($bullet['rationale'] ?? null) ? trim($bullet['rationale']) : null,
                ];
            }

            return $bullets;
        };
    }
}
