<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ResumeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resume extends Model
{
    /** @use HasFactory<ResumeFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_filename',
        'file_path',
        'file_mime',
        'parsed_text',
        'language',
        'skills_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'skills_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ResumeSkill, $this> */
    public function skills(): HasMany
    {
        return $this->hasMany(ResumeSkill::class);
    }
}
