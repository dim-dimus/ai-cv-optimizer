<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePromptTemplateRequest;
use App\Http\Resources\PromptTemplateResource;
use App\Models\PromptTemplate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PromptTemplateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PromptTemplateResource::collection(PromptTemplate::orderBy('slug')->get());
    }

    public function show(string $slug): PromptTemplateResource
    {
        return new PromptTemplateResource(PromptTemplate::where('slug', $slug)->firstOrFail());
    }

    public function update(UpdatePromptTemplateRequest $request, string $slug): PromptTemplateResource
    {
        $template = PromptTemplate::where('slug', $slug)->firstOrFail();

        // Edits take effect immediately — templates are loaded by slug at runtime,
        // no deploy required (NFR-M1). Bump version and record the editor (NFR-S6).
        $template->update([
            ...$request->validated(),
            'version' => $template->version + 1,
            'updated_by' => $request->user()->id,
        ]);

        return new PromptTemplateResource($template);
    }
}
