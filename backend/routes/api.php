<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\LlmLogController;
use App\Http\Controllers\Admin\PromptTemplateController;
use App\Http\Controllers\Admin\UsageController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BulletController;
use App\Http\Controllers\CoverLetterController;
use App\Http\Controllers\ResumeController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::delete('account', [AccountController::class, 'destroy']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('resume', [ResumeController::class, 'show']);
    Route::post('resume', [ResumeController::class, 'store']);
    Route::patch('resume', [ResumeController::class, 'update']);
    Route::delete('resume', [ResumeController::class, 'destroy']);

    Route::get('analyses/latest', [AnalysisController::class, 'latest']);
    Route::get('analyses/{analysis}', [AnalysisController::class, 'show'])->whereNumber('analysis');
    Route::get('analyses/{analysis}/bullets', [BulletController::class, 'index'])->whereNumber('analysis');
    Route::patch('bullets/{bullet}', [BulletController::class, 'update'])->whereNumber('bullet');
    Route::get('analyses/{analysis}/cover-letter', [CoverLetterController::class, 'show'])->whereNumber('analysis');
    Route::patch('analyses/{analysis}/cover-letter', [CoverLetterController::class, 'update'])->whereNumber('analysis');
    Route::get('analyses/{analysis}/cover-letter/export', [CoverLetterController::class, 'export'])->whereNumber('analysis');

    // Endpoints that trigger paid LLM work are rate limited per user (NFR-S7).
    Route::middleware('throttle:llm')->group(function () {
        Route::post('analyses', [AnalysisController::class, 'store']);
        Route::post('analyses/{analysis}/bullets', [BulletController::class, 'store'])->whereNumber('analysis');
        Route::post('analyses/{analysis}/cover-letter', [CoverLetterController::class, 'store'])->whereNumber('analysis');
    });
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('prompt-templates', [PromptTemplateController::class, 'index']);
    Route::get('prompt-templates/{slug}', [PromptTemplateController::class, 'show']);
    Route::put('prompt-templates/{slug}', [PromptTemplateController::class, 'update']);
    Route::get('usage', [UsageController::class, 'index']);
    Route::get('llm-logs', [LlmLogController::class, 'index']);
    Route::get('users', [AdminUserController::class, 'index']);
});

Route::get('health', fn () => response()->json(['status' => 'ok']));
