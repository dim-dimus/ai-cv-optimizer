<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobRequirement extends Model
{
    protected $fillable = [
        'analysis_id',
        'requirement_text',
        'category',
        'embedding',
        'is_matched',
        'matched_resume_skill_id',
        'similarity',
    ];

    protected function casts(): array
    {
        return [
            'is_matched' => 'boolean',
            'similarity' => 'float',
        ];
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    public function matchedResumeSkill(): BelongsTo
    {
        return $this->belongsTo(ResumeSkill::class, 'matched_resume_skill_id');
    }
}
