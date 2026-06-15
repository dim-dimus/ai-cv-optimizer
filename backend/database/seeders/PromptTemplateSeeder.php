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
                'model' => 'claude-haiku-4-5',
                'max_tokens' => 2048,
                'temperature' => 0.0,
                'is_active' => true,
                'version' => 1,
            ],
            [
                'slug' => 'extract_requirements',
                'name' => 'Extract job requirements',
                'description' => 'Turns a job description into categorized requirement phrases.',
                'content' => <<<'PROMPT'
                You extract the concrete requirements from a job description.

                Read the job description below and return ONLY a JSON object of the form:
                {"requirements": [{"text": "...", "category": "hard_skill"}, ...]}

                Each requirement has:
                - "text": a short skill, tool, technology, qualification, or experience phrase.
                - "category": one of "hard_skill", "soft_skill", "experience", "education", "keyword".

                Rules:
                - Do not invent requirements that are not in the text.
                - No prose, no markdown, no explanation — JSON only.

                Job description:
                """
                {{job_description}}
                """
                PROMPT,
                'model' => 'claude-haiku-4-5',
                'max_tokens' => 2048,
                'temperature' => 0.0,
                'is_active' => true,
                'version' => 1,
            ],
            [
                'slug' => 'scoring',
                'name' => 'Score resume against job',
                'description' => 'Produces an overall score, category breakdown, and explanation.',
                'content' => <<<'PROMPT'
                You score how well a resume matches a job description.

                You are given the matched requirements (covered by the resume) and the gaps
                (requirements the resume does not cover). Return ONLY a JSON object:
                {
                  "overall_score": 0-100,
                  "breakdown": {
                    "hard_skills": 0-100, "soft_skills": 0-100, "experience": 0-100,
                    "education": 0-100, "keywords": 0-100
                  },
                  "explanation": "One or two plain-language sentences."
                }

                Matched requirements:
                {{matched}}

                Gaps:
                {{gaps}}

                Resume text:
                """
                {{resume_text}}
                """

                Job description:
                """
                {{job_description}}
                """
                PROMPT,
                'model' => 'claude-sonnet-4-6',
                'max_tokens' => 1024,
                'temperature' => 0.2,
                'is_active' => true,
                'version' => 1,
            ],
        ];

        foreach ($templates as $template) {
            PromptTemplate::updateOrCreate(['slug' => $template['slug']], $template);
        }
    }
}
