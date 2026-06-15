# LLM Integration

## Providers and models

- **Anthropic Claude** — extraction, scoring, bullet rewriting, cover-letter generation.
  Use a balanced model (e.g. Claude Sonnet) for scoring/generation and a cheaper, faster
  model (e.g. Claude Haiku) for simple extraction/classification. Store the model id per
  prompt template so it is tunable without a deploy. Verify current model ids in the
  Anthropic docs.
- **Voyage AI** — embeddings, `voyage-3-large`, 1024 dimensions. Anthropic has no
  embeddings API; this is the recommended pairing.

Both are called only through service interfaces (`LlmClient`, `EmbeddingClient`).

## Operations and their prompts

Each operation maps to a `prompt_templates` row (loaded by slug at runtime):

| slug            | provider  | purpose                                              |
|-----------------|-----------|------------------------------------------------------|
| `extract_skills`| Claude    | resume text → list of skills/experience phrases      |
| `scoring`       | Claude    | resume + JD + match data → category scores + summary |
| `bullet_rewrite`| Claude    | weak bullets + JD context → rewritten bullets        |
| `cover_letter`  | Claude    | resume + JD + matches + params → cover letter        |
| (embedding)     | Voyage    | text → vector(1024); no prompt template              |

## Structured output

Claude is instructed to return only JSON matching a fixed schema; the service validates
before use. On invalid JSON: one corrective retry (feed the error back), then fail the
job (NFR-R2).

`extract_skills` →
```json
{ "skills": ["AWS Lambda", "NestJS", "PostgreSQL", "team mentoring"] }
```

`scoring` →
```json
{
  "overall_score": 78,
  "breakdown": { "hard_skills": 80, "soft_skills": 70, "experience": 85, "education": 60, "keywords": 75 },
  "explanation": "One or two sentences, plain language."
}
```

`bullet_rewrite` →
```json
{
  "bullets": [
    { "original": "Worked on backend",
      "suggested": "Built and scaled a NestJS microservice handling 2M req/day",
      "rationale": "Adds scope and a quantified result" }
  ]
}
```

Keep schemas small and explicit. Few-shot examples (especially for `bullet_rewrite`)
live inside the template content and benefit from prompt caching (NFR-C4).

## Embedding & matching pipeline

This is the core of FR-2.4. Embeddings provide a deterministic, cheap, explainable
similarity signal; Claude does the surrounding reasoning.

1. **Resume skills** — when the resume is new or `parsed_text` changed: `extract_skills`
   (Claude) → embed each skill (Voyage) → store in `resume_skills`, set
   `skills_synced_at`. Skip entirely if the cache is fresh (NFR-C3).
2. **Job requirements** — per analysis: extract requirements (Claude, can reuse
   `extract_skills` style with JD input or a dedicated prompt) → embed each (Voyage).
3. **Match** — for each requirement, query the user's `resume_skills` for the nearest
   vector by cosine distance (`<=>`). Compute `similarity = 1 - distance`.
    - `similarity >= THRESHOLD` (start at 0.75) → `is_matched = true`, store
      `matched_resume_skill_id` and `similarity`.
    - else → gap (`is_matched = false`, null match).
4. **Score & explain** — pass matched/gap summary to Claude (`scoring`) for the category
   breakdown and explanation.
5. **Downstream** — gaps drive `bullet_rewrite`; matches + JD drive `cover_letter`.

`THRESHOLD` is a tunable constant; expose it in config and iterate against real resumes.

## Reliability, cost, logging

- **Timeouts/retries** — every provider call has a ~60 s timeout and backoff retries on
  429/5xx, capped (NFR-R1).
- **Input caps** — bound resume + JD length and `max_tokens` per call (NFR-C2).
- **Caching** — resume embeddings cached by `skills_synced_at`; job-text embeddings can
  be keyed by content hash. Prompt caching for static template parts.
- **Logging** — every Claude/Voyage call writes an `llm_logs` row (model, operation,
  tokens, cost, latency, status). Cost is computed from token counts and current
  per-model rates kept in config. Payloads stored minimally (personal data, admin-only).

## Cost model (estimate)

Unit prices (verify before relying on them — they change):

- Claude Sonnet 4.6: $3 input / $15 output per million tokens (MTok).
- Claude Haiku 4.5: $1 input / $5 output per MTok.
- Voyage `voyage-3-large`: ~$0.18 / MTok, with the first 200M tokens free per account.
- Output is ~5x input across Claude models; output is the expensive side.

Assumptions for one analysis: resume ~1,000 tokens, job description ~700 tokens,
prompt/instructions and few-shot included. Scoring and generation on Sonnet; cheap
extraction on Haiku.

| Operation                       | Model  | ~in   | ~out  | ~cost   |
|---------------------------------|--------|-------|-------|---------|
| Extract resume skills¹          | Haiku  | 1.3k  | 0.15k | $0.002  |
| Extract job requirements        | Haiku  | 1.0k  | 0.15k | $0.0018 |
| Embeddings (resume¹ + JD)       | Voyage | ~0.3k | —     | ~$0 (free tier) |
| Scoring + explanation           | Sonnet | 2.4k  | 0.25k | $0.011  |
| Bullet rewrite                  | Sonnet | 2.5k  | 0.6k  | $0.0165 |
| Cover letter                    | Sonnet | 2.2k  | 0.55k | $0.015  |

¹ Cached — charged only on the first analysis of a resume (NFR-C3), then $0.

Totals:

- Scoring + gaps only (resume cached): **≈ $0.013 (~1.3¢)**.
- Full run (scoring + bullets + cover letter): **≈ $0.045 (~4–5¢)**.
- First analysis of a new resume: + ~$0.002 for skill extraction.
- At scale: ~$4.5 per 100 full runs, ~$45 per 1,000. Embeddings are effectively free.

Estimate is ±2x with document and prompt length. Cost levers: prompt caching on static
system/few-shot (up to 90% off cached input); move bullets/cover letter to Haiku if
quality allows; cap `max_tokens` and input length (NFR-C2). Batch API (50% off) is
asynchronous and unsuitable here since the user waits on a queued result. `llm_logs`
records actual tokens and cost per call (FR-6.2) so this estimate can be checked against
real usage.

## Provider abstraction (why)

`LlmClient` / `EmbeddingClient` interfaces keep controllers and jobs provider-agnostic,
make the pipeline unit-testable with mocks, and let the embedding model be swapped via
config — with the one caveat that changing embedding dimension is a schema migration
(`vector(1024)` is fixed per column).
