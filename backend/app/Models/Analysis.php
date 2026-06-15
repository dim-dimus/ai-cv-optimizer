<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AnalysisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Analysis extends Model
{
    /** @use HasFactory<AnalysisFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'job_description',
        'overall_score',
        'score_breakdown',
        'explanation',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'score_breakdown' => 'array',
            'overall_score' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<JobRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(JobRequirement::class);
    }

    /** @return HasMany<BulletSuggestion, $this> */
    public function bulletSuggestions(): HasMany
    {
        return $this->hasMany(BulletSuggestion::class);
    }

    public function coverLetter(): HasOne
    {
        return $this->hasOne(CoverLetter::class);
    }
}
