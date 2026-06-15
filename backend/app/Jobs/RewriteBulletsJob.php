<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Analysis;
use App\Models\Resume;
use App\Services\BulletRewriter;
use App\Services\Llm\LlmContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Rewrites weak resume bullets for an analysis and stores them as pending
 * suggestions. Replaces any previous suggestions for the analysis.
 */
class RewriteBulletsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $analysisId) {}

    public function handle(BulletRewriter $rewriter, LlmContext $context): void
    {
        $analysis = Analysis::find($this->analysisId);
        if ($analysis === null) {
            return;
        }

        $resume = Resume::firstWhere('user_id', $analysis->user_id);
        if ($resume === null || trim((string) $resume->parsed_text) === '') {
            return;
        }

        $context->for($analysis->user_id, $analysis->id);

        try {
            $bullets = $rewriter->rewrite($resume->parsed_text, $analysis->job_description);

            DB::transaction(function () use ($analysis, $bullets): void {
                $analysis->bulletSuggestions()->delete();

                foreach ($bullets as $i => $bullet) {
                    $analysis->bulletSuggestions()->create([
                        'original_text' => $bullet['original'],
                        'suggested_text' => $bullet['suggested'],
                        'rationale' => $bullet['rationale'],
                        'status' => 'pending',
                        'position' => $i,
                    ]);
                }
            });
        } finally {
            $context->reset();
        }
    }
}
