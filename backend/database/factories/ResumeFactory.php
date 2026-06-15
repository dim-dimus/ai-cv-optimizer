<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resume>
 */
class ResumeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_filename' => $this->faker->word().'.pdf',
            'file_path' => 'resumes/'.$this->faker->uuid().'.pdf',
            'file_mime' => 'application/pdf',
            'parsed_text' => $this->faker->paragraphs(3, true),
            'language' => 'en',
            'skills_synced_at' => null,
        ];
    }
}
