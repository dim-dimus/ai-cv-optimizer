<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;

class PromptTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'slug' => 'extract_skills',
                'name' => 'Extract resume skills',
                'description' => 'Turns resume text into a flat list of skill / experience phrases.',
                'content' => <<<'PROMPT'
                You extract skills and experience phrases from a resume.

                Read the resume text below and return ONLY a JSON object of the form:
                {"skills": ["...", "..."]}

                Rules:
                - Each entry is a short skill, tool, technology, or experience phrase.
                - Do not invent skills that are not supported by the text.
                - No prose, no markdown, no explanation — JSON only.

                Resume text:
                """
                {{resume_text}}
                """
                PROMPT,
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 512,
                'temperature' => 0.0,
                'is_active' => true,
                'version' => 1,
            ],
        ];

        foreach ($templates as $template) {
            PromptTemplate::updateOrCreate(['slug' => $template['slug']], $template);
        }
    }
}
