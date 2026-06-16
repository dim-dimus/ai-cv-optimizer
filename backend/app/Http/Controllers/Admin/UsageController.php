<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LlmLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Aggregates llm_logs into token/cost usage (FR-6.2).
 */
class UsageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $base = LlmLog::query()
            ->when($request->query('from'), fn (Builder $q, $from) => $q->where('created_at', '>=', $from))
            ->when($request->query('to'), fn (Builder $q, $to) => $q->where('created_at', '<=', $to));

        $totals = (clone $base)
            ->selectRaw('count(*) as calls, coalesce(sum(total_tokens), 0) as tokens, coalesce(sum(cost_usd), 0) as cost')
            ->selectRaw("coalesce(sum(case when status = 'failed' then 1 else 0 end), 0) as failures")
            ->first();

        $byOperation = (clone $base)
            ->selectRaw('operation, provider, count(*) as calls, coalesce(sum(total_tokens), 0) as tokens, coalesce(sum(cost_usd), 0) as cost')
            ->groupBy('operation', 'provider')
            ->orderBy('operation')
            ->get();

        return response()->json([
            'data' => [
                'totals' => [
                    'calls' => (int) $totals->calls,
                    'tokens' => (int) $totals->tokens,
                    'cost_usd' => round((float) $totals->cost, 6),
                    'failures' => (int) $totals->failures,
                ],
                'by_operation' => $byOperation->map(fn ($row): array => [
                    'operation' => $row->operation,
                    'provider' => $row->provider,
                    'calls' => (int) $row->calls,
                    'tokens' => (int) $row->tokens,
                    'cost_usd' => round((float) $row->cost, 6),
                ]),
            ],
        ]);
    }
}
