# CLAUDE.md

Persistent context for Claude Code. Read this fully before any task. When a task
touches product behavior, architecture, the schema, or the API, also open the
relevant file in `docs/` — this file is the summary, those are the source of truth.

## What this project is

AI CV Optimizer: a user uploads one resume (PDF/DOCX), pastes a job description, and
receives a structured match score, matched-skills/gaps analysis, rewritten bullet
points, and a tailored cover letter. Heavy LLM work runs asynchronously in a queue;
the frontend polls for job status.

## Tech stack (pin these; verify latest stable before bumping)

- Frontend: Next.js (App Router) + React + TypeScript, server components where sensible
- Backend: Laravel (PHP 8.3+), REST API only (no Blade UI)
- Auth: Laravel Sanctum, token-based for the SPA
- DB: PostgreSQL 16+ with `pgvector`
- Queue: Laravel Queue + dedicated worker process
- Storage: S3 (use the `s3` filesystem disk; `local` in dev)
- LLM: Anthropic Claude via official SDK/HTTP. Embeddings: Voyage AI.

## Repository layout

- `frontend/` — Next.js app; user UI and `/admin` route group share the app
- `backend/` — Laravel; controllers thin, logic in services, LLM behind interfaces
- `docs/` — authoritative specs

## Common commands

Backend (run from `backend/`):
- `composer install` — install deps
- `php artisan migrate` / `php artisan migrate:fresh --seed` — schema
- `php artisan serve` — dev API on :8000
- `php artisan queue:work` — process jobs (required for any analysis to complete)
- `php artisan test` — run tests
- `./vendor/bin/pint` — format PHP (Laravel Pint)

Frontend (run from `frontend/`):
- `pnpm dev` — dev server on :3000
- `pnpm build` — production build
- `pnpm lint` / `pnpm typecheck` — eslint + tsc
- `pnpm test` — run tests

## Architecture rules (do not violate)

1. All LLM and embedding calls go through a service layer behind an interface
   (`LlmClient`, `EmbeddingClient`). Never call the Anthropic/Voyage HTTP API
   directly from a controller or job body. This keeps providers swappable.
2. Every LLM response that must be structured is validated against a schema before
   use. On invalid output: one corrective retry, then fail the job with a clear error.
3. Heavy operations (skill extraction, scoring, bullet rewrite, cover letter) run as
   queued jobs, never inline in an HTTP request. The endpoint returns a job/analysis
   id; the client polls status.
4. Resume skill embeddings are cached. Recompute only when `resumes.parsed_text`
   changes (reset `skills_synced_at`). Never re-embed an unchanged resume.
5. Prompt text lives in the `prompt_templates` table, loaded by slug at runtime.
   Do not hardcode prompts in PHP. Editing a template must not require a deploy.
6. Every LLM/embedding call writes an `llm_logs` row (provider, model, operation,
   tokens, cost, latency, status).

## Key data invariants

- One resume per user: `resumes.user_id` is UNIQUE. Enforce in code and schema.
- Embedding columns are `vector(1024)` (Voyage `voyage-3-large`). Changing the model
  means changing the dimension, which is a migration — flag it loudly.
- `analyses.status`, `cover_letters.status` ∈ {queued, processing, completed, failed}.
- A `job_requirement` with `is_matched = false` is a gap; `matched_resume_skill_id`
  is null for gaps.
- Authorization is server-side: a user can only read/write their own resume and
  analyses. Never rely on the UI to hide other users' data.

## Coding conventions

PHP / Laravel:
- Controllers thin; business logic in `app/Services`, queued work in `app/Jobs`.
- Use Form Requests for validation, API Resources for response shaping.
- Type everything; `declare(strict_types=1);`. Format with Pint before committing.

TypeScript / Next.js:
- Strict TypeScript, no `any`. Share API response types in a `types/` module.
- Data fetching via a typed API client; handle loading/error/empty states explicitly.
- Tailwind for styling; keep components small and focused.

General:
- Sentence-case UI text. Human-readable error messages, never raw stack traces.
- Conventional Commits (`feat:`, `fix:`, `chore:` …). Small, focused PRs.

## Security rules

- Secrets only via env / AWS Secrets Manager. Never commit keys; never send keys to
  the client. The frontend never holds the Anthropic or Voyage key.
- Validate uploads: mime + extension (pdf/docx), size ≤ 5 MB, store in a private S3
  bucket, never execute.
- `llm_logs` request/response payloads contain personal data (CV text): restrict to
  admin, and keep stored payloads minimal.
- This processes personal data in the EU (GDPR): support full account+data deletion;
  treat Anthropic and Voyage as sub-processors.

## Definition of done

- Code typed, formatted (Pint / eslint), and passing `typecheck`.
- New endpoints documented in `docs/API.md`; schema changes in `docs/DATA-MODEL.md`.
- Happy path + at least one failure path tested (LLM failure, invalid upload).
- No secrets in code; no LLM call outside the service layer; job-based for heavy work.

## Do NOT

- Do not add features marked "Later" in `docs/PRODUCT-REQUIREMENTS.md` without being asked.
- Do not call LLM/embedding APIs inline in a request handler.
- Do not hardcode prompts.
- Do not store more personal data than needed; do not log secrets.
- Do not change the embedding dimension casually.
