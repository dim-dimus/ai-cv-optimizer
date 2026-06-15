<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\Resume;
use App\Models\User;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoverLetterTest extends TestCase
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
            'parsed_text' => 'Senior backend engineer with Laravel and PostgreSQL.',
            'skills_synced_at' => now(),
        ]);
        $analysis = Analysis::factory()->for($user)->completed()->create([
            'job_description' => 'Backend role needing Laravel and PostgreSQL expertise on the team.',
        ]);

        return [$user, $analysis];
    }

    public function test_it_generates_a_cover_letter(): void
    {
        [$user, $analysis] = $this->userWithAnalysis();

        $this->actingAs($user)->postJson("/api/analyses/{$analysis->id}/cover-letter", [
            'tone' => 'professional', 'length' => 'medium', 'language' => 'en',
        ])->assertStatus(202);

        $this->actingAs($user)->getJson("/api/analyses/{$analysis->id}/cover-letter")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.tone', 'professional');

        $content = $this->actingAs($user)->getJson("/api/analyses/{$analysis->id}/cover-letter")->json('data.content');
        $this->assertNotEmpty($content);
    }

    public function test_regenerate_overwrites_and_manual_edit_persists(): void
    {
        [$user, $analysis] = $this->userWithAnalysis();
        $this->actingAs($user)->postJson("/api/analyses/{$analysis->id}/cover-letter")->assertStatus(202);

        // Manual edit.
        $this->actingAs($user)->patchJson("/api/analyses/{$analysis->id}/cover-letter", [
            'content' => 'My hand-edited cover letter.',
        ])->assertOk()->assertJsonPath('data.content', 'My hand-edited cover letter.');

        // Regenerate replaces the content (still one row — analysis_id is unique).
        $this->actingAs($user)->postJson("/api/analyses/{$analysis->id}/cover-letter", ['tone' => 'friendly'])
            ->assertStatus(202);
        $this->assertSame(1, $analysis->coverLetter()->count());
        $this->actingAs($user)->getJson("/api/analyses/{$analysis->id}/cover-letter")
            ->assertJsonPath('data.tone', 'friendly');
    }

    public function test_it_exports_pdf_and_docx(): void
    {
        [$user, $analysis] = $this->userWithAnalysis();
        $this->actingAs($user)->postJson("/api/analyses/{$analysis->id}/cover-letter")->assertStatus(202);

        $pdf = $this->actingAs($user)->get("/api/analyses/{$analysis->id}/cover-letter/export?format=pdf");
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $docx = $this->actingAs($user)->get("/api/analyses/{$analysis->id}/cover-letter/export?format=docx");
        $docx->assertOk();
        // DOCX is a ZIP archive — starts with the PK signature.
        $this->assertStringStartsWith('PK', $docx->getContent());
    }

    public function test_it_validates_tone(): void
    {
        [$user, $analysis] = $this->userWithAnalysis();

        $this->actingAs($user)->postJson("/api/analyses/{$analysis->id}/cover-letter", ['tone' => 'sarcastic'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tone');
    }

    public function test_a_user_cannot_read_another_users_cover_letter(): void
    {
        [$owner, $analysis] = $this->userWithAnalysis();
        $this->actingAs($owner)->postJson("/api/analyses/{$analysis->id}/cover-letter")->assertStatus(202);

        $other = User::factory()->create();
        $this->actingAs($other)->getJson("/api/analyses/{$analysis->id}/cover-letter")->assertNotFound();
    }
}
