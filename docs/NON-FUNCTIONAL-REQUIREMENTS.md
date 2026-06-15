# Non-Functional Requirements

## Performance
- **NFR-P1** Synchronous actions (login, load/save resume) respond < 500 ms p95
  (excluding LLM work).
- **NFR-P2** LLM operations take 5–30 s and run in the queue; the UI always shows
  progress, never blocks silently.
- **NFR-P3** Stream Claude output where it improves UX (cover letter, explanations).
- **NFR-P4** PDF/DOCX parsing (≤ 5 MB) completes < 5 s with a loading state.

## Reliability & error handling
- **NFR-R1** Every LLM call has a timeout (~60 s) and retries with backoff on 429/5xx,
  capped.
- **NFR-R2** Structured output is validated against a schema; invalid JSON triggers one
  corrective retry, then a clear error (critical for FR-2.6, FR-3.5).
- **NFR-R3** Parsing failures (encrypted/empty PDF) produce a clear message; the user
  can fix the text in the editable field (FR-1.3).
- **NFR-R4** Resume saves are atomic; a failed LLM job never corrupts stored data.
- **NFR-R5** Guard against double submit (disable + request dedupe) to avoid paying for
  duplicate LLM calls.

## Security & privacy
- **NFR-S1** Auth via Laravel Sanctum; passwords hashed (argon2/bcrypt).
- **NFR-S2** Server-side authorization: users access only their own resume/analyses.
- **NFR-S3** Upload safety: validate mime + extension, ≤ 5 MB, private S3 bucket, files
  outside the web root, never executed.
- **NFR-S4** Secrets in env / AWS Secrets Manager; never in the repo or on the client.
- **NFR-S5** GDPR (EU): data minimization, full account+data deletion, disclose
  Anthropic + Voyage as sub-processors; prefer an EU region.
- **NFR-S6** Admin routes gated by role; prompt-template edits are auditable.
- **NFR-S7** Basic rate limiting on LLM endpoints to prevent abuse/cost spikes
  (full per-user quotas are Later).

## LLM cost
- **NFR-C1** Track tokens and cost per call, persisted (`llm_logs`).
- **NFR-C2** Cap `max_tokens` and input length (resume + job description) for
  predictable cost.
- **NFR-C3** Cache embeddings: recompute only when resume text changes; key job-text
  embeddings by hash.
- **NFR-C4** Use Claude prompt caching for static parts (system + few-shot examples).

## Observability
- **NFR-O1** Log each LLM/embedding request/response: model, tokens, latency, cost,
  status. Payloads contain personal data — restrict access.
- **NFR-O2** Structured app logs with a correlation id per analysis run.
- **NFR-O3** Health-check endpoint for AWS.

## Maintainability
- **NFR-M1** Prompts in DB, edited without deploy, effective immediately.
- **NFR-M2** LLM and embedding providers behind interfaces, swappable.
- **NFR-M3** Embedding model + dimension configurable via env (note: the pgvector
  column dimension is fixed; changing it is a migration).
- **NFR-M4** Shared FE/BE contract types (TS types mirror API schema).

## Deployment & scalability
- **NFR-D1** Stateless API (tokens, no session affinity) → horizontally scalable.
- **NFR-D2** Heavy LLM work runs on a separate worker tier (Laravel Queue), not web
  workers.
- **NFR-D3** AWS target: RDS PostgreSQL with `pgvector`, S3 for files, container/web
  tier for the API, worker tier for the queue.
- **NFR-D4** Config via env: local dev ↔ prod AWS.

## Usability
- **NFR-U1** Clear progress/streaming on long operations; block resubmits.
- **NFR-U2** Human-readable errors.
- **NFR-U3** Desktop-first for MVP with basic responsiveness.
