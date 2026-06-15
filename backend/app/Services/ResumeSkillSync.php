<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EmbeddingClient;
use App\Models\Resume;
use App\Support\Vector;
use Illuminate\Support\Facades\DB;

/**
 * Cache-aware resume skill sync: extracts skills and stores their embeddings,
 * but only when the cache is stale (`skills_synced_at` null). Shared by
 * SyncResumeSkillsJob and ScoreMatchJob so an analysis never matches against
 * stale or missing skills.
 */
class ResumeSkillSync
{
    public function __construct(
        private readonly SkillExtractor $extractor,
        private readonly EmbeddingClient $embeddings,
    ) {}

    public function sync(Resume $resume): void
    {
        if ($resume->skills_synced_at !== null) {
            return; // cache fresh — never re-embed an unchanged resume (NFR-C3)
        }

        $text = trim((string) $resume->parsed_text);
        if ($text === '') {
            return;
        }

        $skills = $this->extractor->extract($text);
        $vectors = $this->embeddings->embedBatch($skills);

        DB::transaction(function () use ($resume, $skills, $vectors): void {
            $resume->skills()->delete();

            foreach ($skills as $i => $skill) {
                $resume->skills()->create([
                    'skill_text' => $skill,
                    'embedding' => Vector::toLiteral($vectors[$i] ?? []),
                ]);
            }

            $resume->forceFill(['skills_synced_at' => now()])->save();
        });
    }
}
