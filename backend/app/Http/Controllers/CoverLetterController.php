<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GenerateCoverLetterRequest;
use App\Http\Requests\UpdateCoverLetterRequest;
use App\Http\Resources\CoverLetterResource;
use App\Jobs\GenerateCoverLetterJob;
use App\Models\CoverLetter;
use App\Services\CoverLetterExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CoverLetterController extends Controller
{
    public function store(GenerateCoverLetterRequest $request, int $analysis): JsonResponse
    {
        $model = $request->user()->analyses()->findOrFail($analysis);

        $cover = CoverLetter::updateOrCreate(
            ['analysis_id' => $model->id],
            [
                'status' => 'queued',
                'tone' => $request->validated('tone', 'professional'),
                'length' => $request->validated('length', 'medium'),
                'language' => $request->validated('language', 'en'),
                'error_message' => null,
            ],
        );

        GenerateCoverLetterJob::dispatch($cover->id);

        return (new CoverLetterResource($cover))->response()->setStatusCode(202);
    }

    public function show(Request $request, int $analysis): CoverLetterResource
    {
        $cover = $request->user()->analyses()->findOrFail($analysis)->coverLetter()->firstOrFail();

        return new CoverLetterResource($cover);
    }

    public function update(UpdateCoverLetterRequest $request, int $analysis): CoverLetterResource
    {
        $cover = $request->user()->analyses()->findOrFail($analysis)->coverLetter()->firstOrFail();

        $cover->update(['content' => $request->validated('content')]);

        return new CoverLetterResource($cover);
    }

    public function export(Request $request, int $analysis, CoverLetterExport $export): Response
    {
        $cover = $request->user()->analyses()->findOrFail($analysis)->coverLetter()->firstOrFail();

        abort_if($cover->status !== 'completed' || ! $cover->content, 409, 'The cover letter is not ready to export.');

        if ($request->query('format') === 'docx') {
            return response($export->docx($cover->content), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => 'attachment; filename="cover-letter.docx"',
            ]);
        }

        return response($export->pdf($cover->content), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="cover-letter.pdf"',
        ]);
    }
}
