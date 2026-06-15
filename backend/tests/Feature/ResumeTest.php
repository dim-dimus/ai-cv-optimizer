<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Resume;
use App\Models\User;
use App\Services\ResumeParser;
use Database\Seeders\PromptTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ResumeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(PromptTemplateSeeder::class);
    }

    private function fakeParserReturning(string $text): void
    {
        $parser = Mockery::mock(ResumeParser::class);
        $parser->shouldReceive('parse')->andReturn($text);
        $this->app->instance(ResumeParser::class, $parser);
    }

    public function test_a_user_can_upload_a_pdf_resume_and_it_is_parsed_and_synced(): void
    {
        $this->fakeParserReturning('Senior PHP engineer with Laravel and PostgreSQL experience.');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/resume', [
            'file' => UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.original_filename', 'cv.pdf')
            ->assertJsonPath('data.language', 'en');

        $resume = $user->refresh()->resume;
        $this->assertNotNull($resume);
        $this->assertStringContainsString('Laravel', (string) $resume->parsed_text);
        Storage::disk('local')->assertExists($resume->file_path);

        // Sync job ran inline (sync queue) → skills + embeddings stored, cache marked.
        $this->assertNotNull($resume->skills_synced_at);
        $this->assertGreaterThan(0, $resume->skills()->count());
    }

    public function test_a_user_can_upload_a_docx_resume(): void
    {
        $this->fakeParserReturning('Product designer skilled in Figma and user research.');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/resume', [
            'file' => UploadedFile::fake()->create(
                'cv.docx',
                200,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ),
        ]);

        $response->assertCreated()->assertJsonPath('data.original_filename', 'cv.docx');
    }

    public function test_upload_rejects_a_non_pdf_docx_file(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/resume', [
            'file' => UploadedFile::fake()->create('cv.txt', 100, 'text/plain'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_upload_rejects_a_file_over_5mb(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/resume', [
            'file' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('file');
    }

    public function test_reupload_replaces_the_existing_resume_and_deletes_the_old_file(): void
    {
        $this->fakeParserReturning('First version text.');
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/resume', [
            'file' => UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $first = $user->refresh()->resume;
        $oldPath = $first->file_path;

        $this->fakeParserReturning('Second version text.');
        $this->actingAs($user)->postJson('/api/resume', [
            'file' => UploadedFile::fake()->create('second.pdf', 100, 'application/pdf'),
        ])->assertOk();

        // Still exactly one resume for the user.
        $this->assertSame(1, Resume::where('user_id', $user->id)->count());
        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_editing_parsed_text_resets_the_skill_cache_and_resyncs(): void
    {
        $this->fakeParserReturning('Original parsed text about Go and Kubernetes.');
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/resume', [
            'file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $response = $this->actingAs($user)->patchJson('/api/resume', [
            'parsed_text' => 'Updated text mentioning Rust and WebAssembly skills.',
        ]);

        $response->assertOk()->assertJsonPath('data.parsed_text', 'Updated text mentioning Rust and WebAssembly skills.');

        $resume = $user->refresh()->resume;
        $this->assertNotNull($resume->skills_synced_at); // re-synced inline
    }

    public function test_show_returns_404_when_no_resume(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/resume')->assertNotFound();
    }

    public function test_a_user_cannot_see_another_users_resume(): void
    {
        $owner = User::factory()->create();
        Resume::factory()->for($owner)->create();

        $other = User::factory()->create();

        // The other user has no resume of their own → 404, never the owner's data.
        $this->actingAs($other)->getJson('/api/resume')->assertNotFound();
    }

    public function test_a_user_can_delete_their_resume(): void
    {
        $this->fakeParserReturning('Some resume text.');
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/resume', [
            'file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $path = $user->refresh()->resume->file_path;

        $this->actingAs($user)->deleteJson('/api/resume')->assertOk();

        $this->assertNull($user->refresh()->resume);
        Storage::disk('local')->assertMissing($path);
    }
}
