# Data Model

PostgreSQL + `pgvector`. Normalize what mutates or is searched (skills, requirements,
bullets); keep the LLM scoring snapshot in JSONB. Every table has `id` (bigint PK) and
`created_at` / `updated_at` unless noted.

Enable the extension once: `CREATE EXTENSION IF NOT EXISTS vector;`

## users
- `name`, `email` (unique), `password` (hashed)
- `role` enum `user` | `admin`, default `user`
- `email_verified_at` nullable (Later)
- Tokens live in framework table `personal_access_tokens` (Sanctum)

## resumes — 1:1 with user
- `user_id` FK, **UNIQUE** (enforces one resume per user)
- `original_filename`, `file_path` (S3 key — file is kept), `file_mime`
- `parsed_text` text — the edited extracted text; this is what we reuse
- `language` default `en`
- `skills_synced_at` nullable — cache marker; reset when `parsed_text` changes

## resume_skills — many per resume
- `resume_id` FK
- `skill_text`
- `embedding` **`vector(1024)`**
- Computed once per resume sync (NFR-C3 cache)

## analyses — many per user
- `user_id` FK
- `status` enum `queued` | `processing` | `completed` | `failed`
- `job_description` text
- `overall_score` smallint 0–100, nullable until completed
- `score_breakdown` jsonb — per-category scores (read-only LLM snapshot)
- `explanation` text nullable
- `error_message` text nullable
- `completed_at` timestamp nullable
- Indexes: `(user_id, status)`

## job_requirements — many per analysis
- `analysis_id` FK
- `requirement_text`
- `category` enum `hard_skill` | `soft_skill` | `experience` | `education` | `keyword`, nullable
- `embedding` **`vector(1024)`**
- `is_matched` bool
- `matched_resume_skill_id` FK → resume_skills, **nullable** (null for gaps)
- `similarity` real nullable
- Matched = `is_matched = true`; gap = `false`. Index: `analysis_id`

## bullet_suggestions — many per analysis
- `analysis_id` FK
- `original_text`, `suggested_text`, `rationale` (nullable)
- `status` enum `pending` | `accepted` | `rejected` | `edited`, default `pending`
- `edited_text` text nullable
- `position` int (ordering)
- Index: `analysis_id`

## cover_letters — 1:1 with analysis
- `analysis_id` FK, UNIQUE
- `status` enum `queued` | `processing` | `completed` | `failed`
- `tone`, `length`, `language` default `en`
- `content` text nullable
- `error_message` text nullable
- Regeneration overwrites `content` (no history)

## prompt_templates — admin-managed
- `slug` unique: `extract_skills` | `scoring` | `bullet_rewrite` | `cover_letter`
- `name`, `description`
- `content` text — template with placeholders, e.g. `{{resume_text}}`
- `model` string (e.g. a Claude model id), `max_tokens` int, `temperature` real
- `is_active` bool, `version` int, `updated_by` FK users (nullable)
- Loaded by slug at runtime. Optional `prompt_template_versions` for audit (Later)

## llm_logs — observability
- `user_id` FK nullable, `analysis_id` FK nullable
- `provider` enum `anthropic` | `voyage`
- `model`
- `operation` enum `extract_skills` | `extract_requirements` | `scoring` | `bullet_rewrite` | `cover_letter` | `embedding`
- `prompt_tokens`, `completion_tokens`, `total_tokens`
- `cost_usd` decimal, `latency_ms` int
- `status` `success` | `failed`, `error` nullable
- `request_meta` / `response_meta` jsonb — minimal, contains personal data, admin-only
- Index: `(created_at, user_id, analysis_id)`. Usage dashboard = aggregates over this
  table; a `daily_usage` rollup is Later.

## Framework tables (Laravel creates these)
`personal_access_tokens` (Sanctum); `jobs`, `failed_jobs`, `job_batches` (queue);
`cache`; `password_reset_tokens` (Later).

## pgvector usage
- Column type `vector(1024)` (Voyage `voyage-3-large`).
- Nearest skill query uses cosine distance operator `<=>`:
  `... ORDER BY embedding <=> :query_vector LIMIT 1`.
- In Laravel: enable the extension and declare the column via `DB::statement(...)` in a
  migration; use the pgvector PHP integration or raw queries (verify the current
  package on Packagist).
- HNSW/IVFFlat indexes are unnecessary at this scale (tens of rows per user); add later
  if needed.

## Key relationships
- users 1—1 resumes; resumes 1—* resume_skills
- users 1—* analyses; analyses 1—* job_requirements; 1—* bullet_suggestions; 1—1 cover_letters
- job_requirements *—1 resume_skills (nullable best match)
- users / analyses 1—* llm_logs
