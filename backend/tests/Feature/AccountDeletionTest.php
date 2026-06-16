<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Analysis;
use App\Models\LlmLog;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_delete_their_account_and_all_data(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $resume = Resume::factory()->for($user)->create(['file_path' => 'resumes/cv.pdf']);
        Storage::disk('local')->put('resumes/cv.pdf', 'x');
        $analysis = Analysis::factory()->for($user)->create();
        LlmLog::create(['user_id' => $user->id, 'analysis_id' => $analysis->id, 'provider' => 'anthropic', 'model' => 'm', 'operation' => 'scoring', 'total_tokens' => 1, 'cost_usd' => 0, 'status' => 'success']);

        $this->actingAs($user)->deleteJson('/api/auth/account')->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('resumes', ['id' => $resume->id]); // cascade
        $this->assertDatabaseMissing('analyses', ['id' => $analysis->id]); // cascade
        $this->assertSame(0, LlmLog::where('user_id', $user->id)->count());
        Storage::disk('local')->assertMissing('resumes/cv.pdf');
    }

    public function test_account_deletion_revokes_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('spa');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->actingAs($user)->deleteJson('/api/auth/account')->assertOk();

        // Tokens are revoked — in production this makes the next request unauthorized.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
