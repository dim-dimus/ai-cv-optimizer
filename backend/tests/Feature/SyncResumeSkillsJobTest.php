<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncResumeSkillsJob;
use App\Models\Resume;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncResumeSkillsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PromptTemplateSeeder::class);
    }

    public function test_it_extracts_skills_and_stores_embeddings(): void
    {
        $resume = Resume::factory()->create([
            'parsed_text' => 'Backend engineer with Laravel, PostgreSQL and Redis.',
            'skills_synced_at' => null,
        ]);

        SyncResumeSkillsJob::dispatchSync($resume->id);

        $resume->refresh();
        $this->assertNotNull($resume->skills_synced_at);
        $this->assertGreaterThan(0, $resume->skills()->count());
    }

    public function test_it_does_not_recompute_when_already_fresh(): void
    {
        $resume = Resume::factory()->create([
            'parsed_text' => 'Data scientist skilled in Python, pandas and TensorFlow.',
            'skills_synced_at' => null,
        ]);

        SyncResumeSkillsJob::dispatchSync($resume->id);
        $resume->refresh();

        $firstSyncedAt = $resume->skills_synced_at;
        $firstSkillIds = $resume->skills()->orderBy('id')->pluck('id')->all();

        // Running again must be a no-op: the cache is fresh (skills_synced_at set).
        SyncResumeSkillsJob::dispatchSync($resume->id);
        $resume->refresh();

        $this->assertEquals($firstSyncedAt, $resume->skills_synced_at);
        $this->assertSame(
            $firstSkillIds,
            $resume->skills()->orderBy('id')->pluck('id')->all(),
            'Skills were recomputed despite a fresh cache.',
        );
    }

    public function test_changed_text_then_resync_replaces_skills(): void
    {
        $resume = Resume::factory()->create([
            'parsed_text' => 'Original about Java and Spring.',
            'skills_synced_at' => null,
        ]);

        SyncResumeSkillsJob::dispatchSync($resume->id);
        $originalIds = $resume->refresh()->skills()->pluck('id')->all();

        // Simulate an edit: new text invalidates the cache.
        $resume->update(['parsed_text' => 'Now about Elixir and Phoenix.', 'skills_synced_at' => null]);
        SyncResumeSkillsJob::dispatchSync($resume->id);

        $newIds = $resume->refresh()->skills()->pluck('id')->all();
        $this->assertNotEquals($originalIds, $newIds);
        $this->assertNotNull($resume->skills_synced_at);
    }
}
