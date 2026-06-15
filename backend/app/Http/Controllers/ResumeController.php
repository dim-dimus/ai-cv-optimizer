<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ResumeParseException;
use App\Http\Requests\StoreResumeRequest;
use App\Http\Requests\UpdateResumeRequest;
use App\Http\Resources\ResumeResource;
use App\Jobs\SyncResumeSkillsJob;
use App\Models\Resume;
use App\Services\ResumeParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ResumeController extends Controller
{
    public function show(Request $request): ResumeResource
    {
        $resume = $request->user()->resume()->first();

        abort_if($resume === null, 404, 'No resume found.');

        return new ResumeResource($resume);
    }

    public function store(StoreResumeRequest $request, ResumeParser $parser): JsonResponse
    {
        $file = $request->file('file');

        try {
            $text = $parser->parse(
                $file->getRealPath(),
                (string) $file->getMimeType(),
                $file->getClientOriginalName(),
            );
        } catch (ResumeParseException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $disk = config('filesystems.default');
        $path = $file->store('resumes', $disk);

        $user = $request->user();
        $existing = $user->resume()->first();

        // One resume per user: replacing means deleting the previous file.
        if ($existing !== null) {
            Storage::disk($disk)->delete($existing->file_path);
        }

        $resume = Resume::updateOrCreate(
            ['user_id' => $user->id],
            [
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_mime' => (string) $file->getMimeType(),
                'parsed_text' => $text,
                'language' => 'en',
                'skills_synced_at' => null, // text changed → cache invalid
            ],
        );

        SyncResumeSkillsJob::dispatch($resume->id);

        return (new ResumeResource($resume))
            ->response()
            ->setStatusCode($existing === null ? 201 : 200);
    }

    public function update(UpdateResumeRequest $request): ResumeResource
    {
        $resume = $request->user()->resume()->first();

        abort_if($resume === null, 404, 'No resume found.');

        $resume->update([
            'parsed_text' => $request->validated('parsed_text'),
            'skills_synced_at' => null, // edited text → re-sync skills
        ]);

        SyncResumeSkillsJob::dispatch($resume->id);

        return new ResumeResource($resume);
    }

    public function destroy(Request $request): JsonResponse
    {
        $resume = $request->user()->resume()->first();

        abort_if($resume === null, 404, 'No resume found.');

        Storage::disk(config('filesystems.default'))->delete($resume->file_path);
        $resume->delete();

        return response()->json(['message' => 'Resume deleted.']);
    }
}
