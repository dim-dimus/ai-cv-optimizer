<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\BulletSuggestion;
use App\Models\Resume;
use App\Models\User;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PromptTemplateSeeder::class);
    }

    /** @return array{0: User, 1: Analysis} */
    private function userWithAnalysis(): array
    {
        $user = User::factory()->create();
        Resume::factory()->for($user)->create([
            'parsed_text' => 'Worked on backend. Responsible for testing. Built Laravel APIs.',
            'skills_synced_at' => now(),
        ]);
        $analysis = Analysis::factory()->for($user)->completed()->create([
            'job_description' => 'Looking for a backend engineer with Laravel and testing experience.',
        ]);

        return [$user, $analysis];
    }

    public function test_it_generates_bullet_suggestions(): void
    {
        [$user, $analysis] = $this->userWithAnalysis();

        $this->actingAs($user)->postJson("/api/analyses/{$analysis->id}/bullets")->assertStatus(202);

        $list = $this->actingAs($user)->getJson("/api/analyses/{$analysis->id}/bullets")->assertOk();
        $this->assertGreaterThan(0, count($list->json('data')));
        $list->assertJsonStructure(['data' => [['id', 'original_text', 'suggested_text', 'status', 'position']]]);
    }

    public function test_a_user_can_accept_reject_and_edit_bullets(): void
    {
        [$user, $analysis] = $this->userWithAnalysis();
        $this->actingAs($user)->postJson("/api/analyses/{$analysis->id}/bullets")->assertStatus(202);
        $ids = collect($this->actingAs($user)->getJson("/api/analyses/{$analysis->id}/bullets")->json('data'))
            ->pluck('id');

        $this->actingAs($user)->patchJson("/api/bullets/{$ids[0]}", ['status' => 'accepted'])
            ->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->actingAs($user)->patchJson("/api/bullets/{$ids[1]}", [
            'status' => 'edited',
            'edited_text' => 'My own improved bullet.',
        ])->assertOk()->assertJsonPath('data.edited_text', 'My own improved bullet.');
    }

    public function test_editing_requires_edited_text(): void
    {
        [$user, $analysis] = $this->userWithAnalysis();
        $this->actingAs($user)->postJson("/api/analyses/{$analysis->id}/bullets")->assertStatus(202);
        $id = $this->actingAs($user)->getJson("/api/analyses/{$analysis->id}/bullets")->json('data.0.id');

        $this->actingAs($user)->patchJson("/api/bullets/{$id}", ['status' => 'edited'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('edited_text');
    }

    public function test_a_user_cannot_edit_another_users_bullet(): void
    {
        [$owner, $analysis] = $this->userWithAnalysis();
        $this->actingAs($owner)->postJson("/api/analyses/{$analysis->id}/bullets")->assertStatus(202);
        $bulletId = BulletSuggestion::where('analysis_id', $analysis->id)->first()->id;

        $other = User::factory()->create();
        $this->actingAs($other)->patchJson("/api/bullets/{$bulletId}", ['status' => 'accepted'])
            ->assertNotFound();
    }
}
