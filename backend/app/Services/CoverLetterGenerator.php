<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\LlmClient;
use App\Models\PromptTemplate;
use App\Services\Llm\LlmRequest;
use RuntimeException;

/**
 * Generates a tailored cover letter (free text) via the `cover_letter` template.
 * Output is prose, so there is no JSON schema to validate.
 */
class CoverLetterGenerator
{
    public function __construct(private readonly LlmClient $llm) {}

    /**
     * @param  array<int, string>  $matched  matched strength phrases
     */
    public function generate(
        string $resumeText,
        string $jobDescription,
        array $matched,
        string $tone,
        string $length,
        string $language,
    ): string {
        $template = PromptTemplate::query()
            ->where('slug', 'cover_letter')
            ->where('is_active', true)
            ->firstOrFail();

        $prompt = str_replace(
            ['{{tone}}', '{{length}}', '{{language}}', '{{matched}}', '{{resume_text}}', '{{job_description}}'],
            [$tone, $length, $language, $this->bullets($matched), $resumeText, $jobDescription],
            $template->content,
        );

        $content = trim($this->llm->complete(new LlmRequest(
            prompt: $prompt,
            model: $template->model,
            operation: 'cover_letter',
            maxTokens: $template->max_tokens,
            temperature: $template->temperature,
        ))->text);

        if ($content === '') {
            throw new RuntimeException('The cover letter came back empty. Please try again.');
        }

        return $content;
    }

    /**
     * @param  array<int, string>  $items
     */
    private function bullets(array $items): string
    {
        if ($items === []) {
            return '(none identified)';
        }

        return implode('', array_map(static fn (string $item): string => "\n- {$item}", $items));
    }
}
