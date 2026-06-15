<?php

declare(strict_types=1);

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ResumeController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('resume', [ResumeController::class, 'show']);
    Route::post('resume', [ResumeController::class, 'store']);
    Route::patch('resume', [ResumeController::class, 'update']);
    Route::delete('resume', [ResumeController::class, 'destroy']);

    Route::post('analyses', [AnalysisController::class, 'store']);
    Route::get('analyses/latest', [AnalysisController::class, 'latest']);
    Route::get('analyses/{analysis}', [AnalysisController::class, 'show'])->whereNumber('analysis');
});

Route::get('health', fn () => response()->json(['status' => 'ok']));
