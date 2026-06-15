<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Contracts\LlmClient;

/**
 * Offline, deterministic stand-in for the chat provider in local/test
 * environments. Branches on the request operation to return the JSON shape each
 * pipeline step expects, so the whole flow runs without calling Anthropic.
 * Replaced by AnthropicClient when an API key is configured.
 */
final class FakeLlmClient implements LlmClient
{
    /**
     * @var array<int, string>
     */
    private const STOPWORDS = [
        'the', 'and', 'for', 'with', 'you', 'your', 'are', 'from', 'this', 'that',
        'return', 'json', 'skills', 'resume', 'text', 'list', 'only', 'each', 'into',
        'extract', 'following', 'experience', 'candidate', 'phrases', 'their',
        'requirements', 'requirement', 'description', 'job', 'category',
    ];

    public function complete(LlmRequest $request): LlmResponse
    {
        $text = match ($request->operation) {
            'extract_requirements' => $this->fakeRequirements($request->prompt),
            'scoring' => $this->fakeScoring($request->prompt),
            'bullet_rewrite' => $this->fakeBullets($request->prompt),
            'cover_letter' => $this->fakeCoverLetter(),
            default => $this->fakeSkills($request->prompt),
        };

        return new LlmResponse(
            text: $text,
            model: $request->model,
            inputTokens: (int) ceil(mb_strlen($request->prompt) / 4),
            outputTokens: (int) ceil(mb_strlen($text) / 4),
        );
    }

    private function fakeSkills(string $prompt): string
    {
        return json_encode(['skills' => $this->phrases($prompt)], JSON_THROW_ON_ERROR);
    }

    private function fakeRequirements(string $prompt): string
    {
        $categories = ['hard_skill', 'soft_skill', 'experience', 'education', 'keyword'];
        $requirements = [];
        foreach ($this->phrases($prompt) as $i => $phrase) {
            $requirements[] = ['text' => $phrase, 'category' => $categories[$i % count($categories)]];
        }

        return json_encode(['requirements' => $requirements], JSON_THROW_ON_ERROR);
    }

    private function fakeScoring(string $prompt): string
    {
        // Derive a plausible overall from the matched/gap balance in the prompt.
        $matched = substr_count($prompt, "\n- ");
        $score = $matched > 0 ? min(95, 50 + $matched * 5) : 60;

        return json_encode([
            'overall_score' => $score,
            'breakdown' => [
                'hard_skills' => $score,
                'soft_skills' => max(0, $score - 10),
                'experience' => min(100, $score + 5),
                'education' => max(0, $score - 15),
                'keywords' => $score,
            ],
            'explanation' => 'Generated offline by the development stand-in; not a real assessment.',
        ], JSON_THROW_ON_ERROR);
    }

    private function fakeBullets(string $prompt): string
    {
        $phrases = array_slice($this->phrases($prompt), 0, 3);
        $bullets = [];
        foreach ($phrases as $phrase) {
            $bullets[] = [
                'original' => "Worked with {$phrase}",
                'suggested' => "Delivered measurable results using {$phrase}, improving a key metric by 30%",
                'rationale' => 'Offline stand-in: adds scope and a quantified result.',
            ];
        }

        return json_encode(['bullets' => $bullets], JSON_THROW_ON_ERROR);
    }

    private function fakeCoverLetter(): string
    {
        return "Dear Hiring Manager,\n\nThis cover letter was generated offline by the development "
            .'stand-in and is not a real assessment. With the relevant experience on my resume, I am '
            ."confident I would contribute to your team.\n\nSincerely,\nThe Candidate";
    }

    /**
     * Pull plausible phrases from the resume/JD block the template wraps in triple quotes.
     *
     * @return array<int, string>
     */
    private function phrases(string $prompt): array
    {
        $source = preg_match('/"""\s*(.+?)\s*"""/s', $prompt, $block) === 1 ? $block[1] : $prompt;

        preg_match_all('/[A-Za-z][A-Za-z0-9+#.\-]{2,}/', $source, $matches);

        $seen = [];
        foreach ($matches[0] as $word) {
            $normalized = mb_strtolower($word);
            if (in_array($normalized, self::STOPWORDS, true)) {
                continue;
            }
            $seen[$normalized] = $word;
            if (count($seen) >= 12) {
                break;
            }
        }

        return array_values($seen);
    }
}
