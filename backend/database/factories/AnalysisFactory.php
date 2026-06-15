<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Analysis;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Analysis>
 */
class AnalysisFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'queued',
            'job_description' => $this->faker->paragraphs(2, true),
            'overall_score' => null,
            'score_breakdown' => null,
            'explanation' => null,
            'error_message' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'completed',
            'overall_score' => $this->faker->numberBetween(0, 100),
            'score_breakdown' => [
                'hard_skills' => 80, 'soft_skills' => 70, 'experience' => 85,
                'education' => 60, 'keywords' => 75,
            ],
            'explanation' => $this->faker->sentence(),
            'completed_at' => now(),
        ]);
    }
}
