<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccountController extends Controller
{
    /**
     * Full account + data deletion (GDPR, NFR-S5): removes the resume file, the
     * user's LLM logs (which hold personal data), tokens, and the user — whose
     * deletion cascades to resume, analyses, requirements, bullets, cover letters.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $resume = $user->resume()->first();
            if ($resume !== null) {
                Storage::disk(config('filesystems.default'))->delete($resume->file_path);
            }

            $user->llmLogs()->delete();
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json(['message' => 'Account deleted.']);
    }
}
