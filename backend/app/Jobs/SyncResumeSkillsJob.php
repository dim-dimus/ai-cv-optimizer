<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\EmbeddingClient;
use App\Models\Resume;
use App\Services\SkillExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Extracts skills from a resume and stores their embeddings. Idempotent and
 * cache-aware: it does nothing if the resume's skills are already in sync
 * (`skills_synced_at` set), so an unchanged resume is never re-embedded (NFR-C3).
 *
 * The trigger that requires a re-sync (new upload or edited text) resets
 * `skills_synced_at` to null before dispatching this job.
 */
class SyncResumeSkillsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $resumeId) {}

    public function handle(SkillExtractor $extractor, EmbeddingClient $embeddings): void
    {
        $resume = Resume::find($this->resumeId);

        if ($resume === null) {
            return;
        }

        // Already fresh — the cache is valid, do not recompute.
        if ($resume->skills_synced_at !== null) {
            return;
        }

        $text = trim((string) $resume->parsed_text);
        if ($text === '') {
            return;
        }

        $skills = $extractor->extract($text);
        $vectors = $embeddings->embedBatch($skills);

        DB::transaction(function () use ($resume, $skills, $vectors): void {
            $resume->skills()->delete();

            foreach ($skills as $i => $skill) {
                $resume->skills()->create([
                    'skill_text' => $skill,
                    'embedding' => $this->toVectorLiteral($vectors[$i] ?? []),
                ]);
            }

            $resume->forceFill(['skills_synced_at' => now()])->save();
        });
    }

    /**
     * Format a float array as a pgvector literal, e.g. "[0.1,0.2,...]".
     *
     * @param  array<int, float>  $vector
     */
    private function toVectorLiteral(array $vector): string
    {
        return '['.implode(',', array_map(static fn (float $v): string => (string) $v, $vector)).']';
    }
}
