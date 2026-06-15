<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Resume;
use App\Services\Llm\LlmContext;
use App\Services\ResumeSkillSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Extracts skills from a resume and stores their embeddings. Cache-aware via
 * ResumeSkillSync: an unchanged resume (skills_synced_at set) is a no-op, so it
 * is never re-embedded (NFR-C3). The trigger that requires a re-sync (new upload
 * or edited text) resets skills_synced_at to null before dispatching this job.
 */
class SyncResumeSkillsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $resumeId) {}

    public function handle(ResumeSkillSync $sync, LlmContext $context): void
    {
        $resume = Resume::find($this->resumeId);

        if ($resume === null) {
            return;
        }

        $context->for($resume->user_id, null);

        try {
            $sync->sync($resume);
        } finally {
            $context->reset();
        }
    }
}
