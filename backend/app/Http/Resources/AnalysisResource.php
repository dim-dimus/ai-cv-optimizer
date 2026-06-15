<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Analysis;
use App\Models\JobRequirement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Analysis
 */
class AnalysisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'status' => $this->status,
        ];

        if ($this->status === 'failed') {
            $data['error_message'] = $this->error_message;
        }

        if ($this->status === 'completed') {
            $requirements = $this->requirements;

            $data += [
                'overall_score' => $this->overall_score,
                'score_breakdown' => $this->score_breakdown,
                'explanation' => $this->explanation,
                'completed_at' => $this->completed_at?->toIso8601String(),
                'matched' => $requirements
                    ->where('is_matched', true)
                    ->map(static fn (JobRequirement $r): array => [
                        'requirement' => $r->requirement_text,
                        'matched_skill' => $r->matchedResumeSkill?->skill_text,
                        'similarity' => $r->similarity !== null ? round($r->similarity, 2) : null,
                    ])
                    ->values(),
                'gaps' => $requirements
                    ->where('is_matched', false)
                    ->map(static fn (JobRequirement $r): array => [
                        'requirement' => $r->requirement_text,
                        'category' => $r->category,
                    ])
                    ->values(),
            ];
        }

        return $data;
    }
}
