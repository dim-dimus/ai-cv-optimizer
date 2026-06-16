<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LlmLog;
use App\Models\User;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PromptTemplateSeeder::class);
    }

    public function test_non_admins_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/admin/prompt-templates')->assertForbidden();
        $this->actingAs($user)->getJson('/api/admin/usage')->assertForbidden();
        $this->actingAs($user)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_admin_can_list_prompt_templates(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/admin/prompt-templates')
            ->assertOk()
            ->assertJsonStructure(['data' => [['slug', 'model', 'max_tokens', 'version']]]);
    }

    public function test_admin_can_edit_a_prompt_template_without_a_deploy(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->putJson('/api/admin/prompt-templates/scoring', [
            'content' => 'Updated scoring prompt {{resume_text}} {{job_description}}',
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 1500,
            'temperature' => 0.3,
            'is_active' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.max_tokens', 1500)
            ->assertJsonPath('data.version', 2); // bumped from 1

        $this->assertDatabaseHas('prompt_templates', [
            'slug' => 'scoring',
            'max_tokens' => 1500,
            'updated_by' => $admin->id,
        ]);
    }

    public function test_prompt_template_update_is_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->putJson('/api/admin/prompt-templates/scoring', [
            'content' => '',
            'model' => '',
            'max_tokens' => 0,
            'temperature' => 5,
            'is_active' => 'maybe',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['content', 'model', 'max_tokens', 'temperature', 'is_active']);
    }

    public function test_admin_usage_aggregates_tokens_and_cost(): void
    {
        $admin = User::factory()->admin()->create();
        LlmLog::query()->insert([
            ['provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'operation' => 'extract_skills', 'prompt_tokens' => 100, 'completion_tokens' => 20, 'total_tokens' => 120, 'cost_usd' => 0.001, 'status' => 'success', 'created_at' => now(), 'updated_at' => now()],
            ['provider' => 'anthropic', 'model' => 'claude-sonnet-4-6', 'operation' => 'scoring', 'prompt_tokens' => 200, 'completion_tokens' => 50, 'total_tokens' => 250, 'cost_usd' => 0.01, 'status' => 'failed', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($admin)->getJson('/api/admin/usage')
            ->assertOk()
            ->assertJsonPath('data.totals.calls', 2)
            ->assertJsonPath('data.totals.tokens', 370)
            ->assertJsonPath('data.totals.failures', 1);
    }

    public function test_admin_can_view_logs_and_users(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create();

        $this->actingAs($admin)->getJson('/api/admin/llm-logs')
            ->assertOk()->assertJsonStructure(['data', 'links', 'meta']);

        $this->actingAs($admin)->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'email', 'role', 'analyses_count', 'has_resume']]]);
    }
}
