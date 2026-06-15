<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\EmbeddingClient;
use App\Exceptions\InvalidStructuredOutput;
use App\Exceptions\ProviderException;
use App\Models\Analysis;
use App\Models\Resume;
use App\Services\JdRequirementExtractor;
use App\Services\Llm\LlmContext;
use App\Services\ResumeSkillSync;
use App\Services\Scorer;
use App\Services\SkillMatcher;
use App\Support\Vector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * The analysis pipeline (ARCHITECTURE.md / LLM-INTEGRATION.md):
 * ensure resume skills fresh → extract JD requirements → embed → nearest resume
 * skill by cosine distance → matched/gap by threshold → score → persist.
 * Status transitions queued → processing → completed|failed; writes are atomic.
 */
class ScoreMatchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $analysisId) {}

    public function handle(
        ResumeSkillSync $sync,
        JdRequirementExtractor $requirementExtractor,
        EmbeddingClient $embeddings,
        SkillMatcher $matcher,
        Scorer $scorer,
        LlmContext $context,
    ): void {
        $analysis = Analysis::find($this->analysisId);
        if ($analysis === null) {
            return;
        }

        $context->for($analysis->user_id, $analysis->id);

        try {
            $analysis->update(['status' => 'processing']);

            $resume = Resume::firstWhere('user_id', $analysis->user_id);
            if ($resume === null || trim((string) $resume->parsed_text) === '') {
                throw new RuntimeException('Upload a resume before running an analysis.');
            }

            // Skills + embeddings must be current before matching.
            $sync->sync($resume);

            $requirements = $requirementExtractor->extract($analysis->job_description);
            $vectors = $embeddings->embedBatch(array_map(static fn (array $r): string => $r['text'], $requirements));
            $threshold = (float) config('llm.match_threshold');

            $matched = [];
            $gaps = [];

            DB::transaction(function () use ($analysis, $resume, $requirements, $vectors, $matcher, $threshold, &$matched, &$gaps): void {
                $analysis->requirements()->delete();

                foreach ($requirements as $i => $requirement) {
                    $vector = $vectors[$i] ?? [];
                    $nearest = $vector === [] ? null : $matcher->nearest($resume->id, $vector);
                    $isMatched = $nearest !== null && $nearest['similarity'] >= $threshold;

                    $analysis->requirements()->create([
                        'requirement_text' => $requirement['text'],
                        'category' => $requirement['category'],
                        'embedding' => Vector::toLiteral($vector),
                        'is_matched' => $isMatched,
                        'matched_resume_skill_id' => $isMatched ? $nearest['id'] : null,
                        'similarity' => $nearest['similarity'] ?? null,
                    ]);

                    if ($isMatched) {
                        $matched[] = $requirement['text'];
                    } else {
                        $gaps[] = $requirement['text'];
                    }
                }
            });

            $result = $scorer->score($resume->parsed_text, $analysis->job_description, $matched, $gaps);

            $analysis->update([
                'status' => 'completed',
                'overall_score' => $result['overall_score'],
                'score_breakdown' => $result['breakdown'],
                'explanation' => $result['explanation'],
                'error_message' => null,
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $analysis->update(['status' => 'failed', 'error_message' => $this->friendlyMessage($e)]);
            report($e);
        } finally {
            $context->reset();
        }
    }

    private function friendlyMessage(Throwable $e): string
    {
        return match (true) {
            $e instanceof InvalidStructuredOutput => 'The analysis produced an unexpected result. Please try again.',
            $e instanceof ProviderException => 'The analysis service is temporarily unavailable. Please try again shortly.',
            $e instanceof RuntimeException => $e->getMessage(),
            default => 'Analysis failed. Please try again.',
        };
    }
}
