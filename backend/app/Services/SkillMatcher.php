<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Finds the nearest resume skill to a query embedding using pgvector cosine
 * distance (`<=>`). similarity = 1 - distance (LLM-INTEGRATION.md).
 */
class SkillMatcher
{
    /**
     * @param  array<int, float>  $queryVector
     * @return array{id: int, similarity: float}|null null when the resume has no skills
     */
    public function nearest(int $resumeId, array $queryVector): ?array
    {
        $literal = '['.implode(',', $queryVector).']';

        $row = DB::selectOne(
            'SELECT id, 1 - (embedding <=> ?::vector) AS similarity
             FROM resume_skills
             WHERE resume_id = ?
             ORDER BY embedding <=> ?::vector
             LIMIT 1',
            [$literal, $resumeId, $literal],
        );

        if ($row === null) {
            return null;
        }

        return ['id' => (int) $row->id, 'similarity' => (float) $row->similarity];
    }
}
