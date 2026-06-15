<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptTemplate extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'content',
        'model',
        'max_tokens',
        'temperature',
        'is_active',
        'version',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'max_tokens' => 'integer',
            'temperature' => 'float',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
