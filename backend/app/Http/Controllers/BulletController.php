<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBulletRequest;
use App\Http\Resources\BulletSuggestionResource;
use App\Jobs\RewriteBulletsJob;
use App\Models\BulletSuggestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BulletController extends Controller
{
    public function store(Request $request, int $analysis): JsonResponse
    {
        $model = $request->user()->analyses()->findOrFail($analysis);

        RewriteBulletsJob::dispatch($model->id);

        return response()->json(['message' => 'Generating bullet suggestions.'], 202);
    }

    public function index(Request $request, int $analysis): AnonymousResourceCollection
    {
        $model = $request->user()->analyses()->findOrFail($analysis);

        return BulletSuggestionResource::collection(
            $model->bulletSuggestions()->orderBy('position')->get(),
        );
    }

    public function update(UpdateBulletRequest $request, int $bullet): BulletSuggestionResource
    {
        $suggestion = BulletSuggestion::findOrFail($bullet);
        abort_unless($suggestion->analysis->user_id === $request->user()->id, 404);

        $status = $request->validated('status');
        $suggestion->update([
            'status' => $status,
            'edited_text' => $status === 'edited' ? $request->validated('edited_text') : $suggestion->edited_text,
        ]);

        return new BulletSuggestionResource($suggestion);
    }
}
