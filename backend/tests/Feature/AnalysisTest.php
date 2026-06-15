<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\Resume;
use App\Models\User;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PromptTemplateSeeder::class);
    }

    private function userWithResume(string $resumeText): User
    {
        $user = User::factory()->create();
        Resume::factory()->for($user)->create([
            'parsed_text' => $resumeText,
            'skills_synced_at' => null,
        ]);

        return $user;
    }

    public function test_running_an_analysis_returns_score_matched_and_gaps(): void
    {
        // Shared words (Laravel, PostgreSQL) match exactly; Kubernetes/Terraform are gaps.
        $user = $this->userWithResume('Backend engineer skilled in Laravel PostgreSQL Redis.');

        $create = $this->actingAs($user)->postJson('/api/analyses', [
            'job_description' => 'We need Laravel and PostgreSQL plus Kubernetes and Terraform on the team.',
        ]);
        $create->assertCreated()->assertJsonPath('data.status', 'queued');

        $id = $create->json('data.id');
        $result = $this->actingAs($user)->getJson("/api/analyses/{$id}")->assertOk();

        $result->assertJsonPath('data.status', 'completed');
        $score = $result->json('data.overall_score');
        $this->assertIsInt($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
        $result->assertJsonStructure([
            'data' => [
                'score_breakdown' => ['hard_skills', 'soft_skills', 'experience', 'education', 'keywords'],
                'explanation',
                'matched',
                'gaps',
            ],
        ]);

        $matched = collect($result->json('data.matched'))->pluck('requirement');
        $gaps = collect($result->json('data.gaps'))->pluck('requirement');

        // A differently-located but identically-worded skill is matched semantically.
        $this->assertTrue($matched->contains('Laravel') || $matched->contains('PostgreSQL'));
        $this->assertTrue($gaps->contains('Kubernetes') || $gaps->contains('Terraform'));
    }

    public function test_analysis_writes_llm_logs(): void
    {
        $user = $this->userWithResume('Engineer with Laravel and Docker experience.');

        $this->actingAs($user)->postJson('/api/analyses', [
            'job_description' => 'Looking for Laravel and Docker and Kubernetes skills here.',
        ])->assertCreated();

        // extract_skills + embedding + extract_requirements + embedding + scoring → several rows.
        $this->assertGreaterThan(0, $user->llmLogs()->count());
        $this->assertDatabaseHas('llm_logs', ['operation' => 'scoring', 'status' => 'success']);
        $this->assertDatabaseHas('llm_logs', ['operation' => 'embedding', 'provider' => 'voyage']);
    }

    public function test_it_requires_a_resume(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/analyses', [
            'job_description' => 'A valid length job description for testing the resume guard.',
        ])->assertStatus(422);
    }

    public function test_it_validates_job_description_length(): void
    {
        $user = $this->userWithResume('Some resume text with Laravel.');

        $this->actingAs($user)->postJson('/api/analyses', ['job_description' => 'too short'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('job_description');
    }

    public function test_latest_returns_the_most_recent_analysis(): void
    {
        $user = $this->userWithResume('Resume with Laravel and Vue.');

        $this->actingAs($user)->postJson('/api/analyses', [
            'job_description' => 'First job description that is long enough to pass validation.',
        ])->assertCreated();
        $this->actingAs($user)->postJson('/api/analyses', [
            'job_description' => 'Second more recent job description, also long enough to pass.',
        ])->assertCreated();

        $latestId = Analysis::where('user_id', $user->id)->latest()->first()->id;

        $this->actingAs($user)->getJson('/api/analyses/latest')
            ->assertOk()
            ->assertJsonPath('data.id', $latestId);
    }

    public function test_a_user_cannot_read_another_users_analysis(): void
    {
        $owner = $this->userWithResume('Owner resume with Laravel.');
        $create = $this->actingAs($owner)->postJson('/api/analyses', [
            'job_description' => 'Owner job description long enough to pass the validator rules.',
        ])->assertCreated();
        $id = $create->json('data.id');

        $other = User::factory()->create();
        $this->actingAs($other)->getJson("/api/analyses/{$id}")->assertNotFound();
    }
}
