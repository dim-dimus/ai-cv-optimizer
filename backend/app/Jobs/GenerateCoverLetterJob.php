<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\ProviderException;
use App\Models\CoverLetter;
use App\Models\Resume;
use App\Services\CoverLetterGenerator;
use App\Services\Llm\LlmContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Generates (or regenerates) a cover letter for an analysis. Overwrites previous
 * content (no history). Own status: queued → processing → completed|failed.
 */
class GenerateCoverLetterJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $coverLetterId) {}

    public function handle(CoverLetterGenerator $generator, LlmContext $context): void
    {
        $cover = CoverLetter::with('analysis')->find($this->coverLetterId);
        if ($cover === null) {
            return;
        }

        $analysis = $cover->analysis;
        $context->for($analysis->user_id, $analysis->id);

        try {
            $cover->update(['status' => 'processing']);

            $resume = Resume::firstWhere('user_id', $analysis->user_id);
            if ($resume === null || trim((string) $resume->parsed_text) === '') {
                throw new RuntimeException('Upload a resume before generating a cover letter.');
            }

            $matched = $analysis->requirements()
                ->where('is_matched', true)
                ->pluck('requirement_text')
                ->all();

            $content = $generator->generate(
                $resume->parsed_text,
                $analysis->job_description,
                $matched,
                $cover->tone ?? 'professional',
                $cover->length ?? 'medium',
                $cover->language ?? 'en',
            );

            $cover->update(['status' => 'completed', 'content' => $content, 'error_message' => null]);
        } catch (Throwable $e) {
            $cover->update(['status' => 'failed', 'error_message' => $this->friendlyMessage($e)]);
            report($e);
        } finally {
            $context->reset();
        }
    }

    private function friendlyMessage(Throwable $e): string
    {
        return match (true) {
            $e instanceof ProviderException => 'The generator is temporarily unavailable. Please try again shortly.',
            $e instanceof RuntimeException => $e->getMessage(),
            default => 'Cover letter generation failed. Please try again.',
        };
    }
}
