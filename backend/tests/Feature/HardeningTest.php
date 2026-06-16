<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Resume;
use App\Models\User;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_is_public(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJsonPath('status', 'ok');
    }

    public function test_llm_endpoints_are_rate_limited(): void
    {
        $this->seed(PromptTemplateSeeder::class);
        $user = User::factory()->create();
        Resume::factory()->for($user)->create([
            'parsed_text' => 'Engineer with Laravel.',
            'skills_synced_at' => now(),
        ]);

        $payload = ['job_description' => 'A sufficiently long job description to satisfy validation.'];

        // The limiter allows 15 per minute; the 16th is throttled.
        for ($i = 0; $i < 15; $i++) {
            $this->actingAs($user)->postJson('/api/analyses', $payload)->assertCreated();
        }

        $this->actingAs($user)->postJson('/api/analyses', $payload)->assertStatus(429);
    }
}
