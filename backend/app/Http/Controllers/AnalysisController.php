<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnalysisRequest;
use App\Http\Resources\AnalysisResource;
use App\Jobs\ScoreMatchJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalysisController extends Controller
{
    public function store(StoreAnalysisRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->resume()->whereNotNull('parsed_text')->doesntExist()) {
            return response()->json(['message' => 'Upload a resume before running an analysis.'], 422);
        }

        $analysis = $user->analyses()->create([
            'status' => 'queued',
            'job_description' => $request->validated('job_description'),
        ]);

        ScoreMatchJob::dispatch($analysis->id);

        return (new AnalysisResource($analysis))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $analysis): AnalysisResource
    {
        $model = $request->user()->analyses()
            ->with('requirements.matchedResumeSkill')
            ->findOrFail($analysis);

        return new AnalysisResource($model);
    }

    public function latest(Request $request): AnalysisResource
    {
        $model = $request->user()->analyses()
            ->with('requirements.matchedResumeSkill')
            ->latest()
            ->firstOrFail();

        return new AnalysisResource($model);
    }
}
