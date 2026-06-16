<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LlmLogResource;
use App\Models\LlmLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LlmLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $logs = LlmLog::query()
            ->when($request->query('operation'), fn (Builder $q, $op) => $q->where('operation', $op))
            ->when($request->query('status'), fn (Builder $q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(25);

        return LlmLogResource::collection($logs);
    }
}
