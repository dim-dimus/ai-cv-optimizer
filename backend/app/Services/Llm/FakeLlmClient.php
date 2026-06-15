<?php

declare(strict_types=1);

namespace App\Services\Llm;

use App\Contracts\LlmClient;

/**
 * Offline, deterministic stand-in for the chat provider in local/test
 * environments. For skill extraction it returns a JSON `{ "skills": [...] }`
 * payload derived from the prompt text, so the pipeline runs end to end
 * without calling Anthropic. Replaced by the real provider in Phase 3.
 */
final class FakeLlmClient implements LlmClient
{
    /**
     * Common words to drop so the extracted "skills" resemble real ones.
     *
     * @var array<int, string>
     */
    private const STOPWORDS = [
        'the', 'and', 'for', 'with', 'you', 'your', 'are', 'from', 'this', 'that',
        'return', 'json', 'skills', 'resume', 'text', 'list', 'only', 'each', 'into',
        'extract', 'following', 'experience', 'candidate', 'phrases', 'their',
    ];

    public function complete(LlmRequest $request): LlmResponse
    {
        $skills = $this->fakeSkills($request->prompt);
        $text = json_encode(['skills' => $skills], JSON_THROW_ON_ERROR);

        return new LlmResponse(
            text: $text,
            model: $request->model,
            inputTokens: (int) ceil(mb_strlen($request->prompt) / 4),
            outputTokens: (int) ceil(mb_strlen($text) / 4),
        );
    }

    /**
     * @return array<int, string>
     */
    private function fakeSkills(string $prompt): array
    {
        // Prefer the resume body the template wraps in triple quotes, so the
        // stand-in returns plausible skills rather than words from the instructions.
        if (preg_match('/"""\s*(.+?)\s*"""/s', $prompt, $block) === 1) {
            $source = $block[1];
        } else {
            $source = $prompt;
        }

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
