# Architecture

## Components

```
┌─────────────────────┐        HTTPS / JSON        ┌──────────────────────────┐
│   Next.js frontend  │  ───────────────────────►  │      Laravel API         │
│  (UI + /admin)      │  ◄───────────────────────  │  controllers → services  │
│  Sanctum token      │      poll job status       │                          │
└─────────────────────┘                            └────────────┬─────────────┘
                                                                 │ dispatch
                                                                 ▼
                                              ┌──────────────────────────────────┐
                                              │        Laravel Queue (jobs)        │
                                              │  ExtractSkills / ScoreMatch /      │
                                              │  RewriteBullets / GenerateCover    │
                                              └───────┬───────────────┬────────────┘
                                                      │               │
                                       ┌──────────────▼───┐   ┌────────▼─────────┐
                                       │   Anthropic      │   │    Voyage AI     │
                                       │   Claude (gen)   │   │   (embeddings)   │
                                       └──────────────────┘   └──────────────────┘

   PostgreSQL + pgvector  (users, resumes, embeddings, analyses, logs)
   Amazon S3              (uploaded resume files)
```

## Layered backend

- **Controllers** — thin; validate (Form Requests), authorize, dispatch jobs, shape
  responses (API Resources). No business logic, no LLM calls.
- **Services** — business logic. `LlmClient` and `EmbeddingClient` interfaces wrap the
  providers so they are swappable and individually testable/mockable.
- **Jobs** — all heavy LLM/embedding work. Update `status` and persist results.
- **Models** — Eloquent; encode invariants (unique resume per user, status enums).

## Tech choices & rationale

- **Queue over synchronous** — analyses take 5–30 s; doing this inline would tie up web
  workers and time out. Jobs + polling keep the API responsive and the worker tier
  independently scalable.
- **Embeddings separate from Claude** — Anthropic has no embeddings API; Voyage provides
  `voyage-3-large` (1024 dims). Claude does extraction/reasoning/generation; embeddings
  do deterministic semantic similarity. See `LLM-INTEGRATION.md`.
- **Prompts in DB** — lets the admin iterate on prompt quality without redeploys.
- **Sanctum** — clean token auth for a separate SPA frontend.

## Flow: run an analysis

1. `POST /api/analyses` with `{ job_description }`. The API loads the user's resume,
   creates an `analyses` row with `status = queued`, dispatches `ScoreMatchJob`,
   returns `{ analysis_id, status }`.
2. The job: ensures resume skills + embeddings are current (extract via Claude + embed
   via Voyage if `skills_synced_at` is stale); extracts job requirements via Claude;
   embeds them; for each requirement finds the nearest resume skill via pgvector cosine
   distance; marks matched/gap; asks Claude for category scores + explanation; persists
   `job_requirements`, `overall_score`, `score_breakdown`; sets `status = completed`.
3. The frontend polls `GET /api/analyses/{id}` until `status` is `completed` or `failed`.

Bullet rewriting and cover-letter generation are separate dispatched jobs against the
same analysis, each with its own status, so they can run and be retried independently.

## Failure handling

- Provider 429/5xx → retry with backoff (NFR-R1). Exhausted → job fails, `status =
  failed`, `error_message` set, surfaced to the user as a readable message.
- Invalid structured output → one corrective retry, then fail (NFR-R2).
- Parsing failure → user edits the extracted text and retries (NFR-R3).

## Environments

- **Dev** — local Next.js + `php artisan serve` + local Postgres (pgvector) + `local`
  filesystem disk + a running `queue:work`.
- **Prod (AWS)** — see `DEPLOYMENT.md`.
