# Implementation Plan

The build order below turns `ROADMAP.md` into concrete, ordered tasks. It is grouped by
the same five logical phases; each phase lists steps in dependency order, the artifacts
they produce, and a verification gate that must pass before moving on. Every task is sized
for one Claude Code session — start it by pointing the agent at the cited `docs/` file.

Source-of-truth docs: `PRODUCT-REQUIREMENTS.md` (scope), `ARCHITECTURE.md` (layers/flow),
`DATA-MODEL.md` (schema), `API.md` (contracts), `LLM-INTEGRATION.md` (prompts/pipeline),
`NON-FUNCTIONAL-REQUIREMENTS.md` (NFRs), `DEPLOYMENT.md` (AWS). Honor every rule in
`CLAUDE.md` (service-layer LLM calls, prompts in DB, queue for heavy work, embedding cache,
`llm_logs` on every call, server-side authz).

## Conventions for every task

- Keep controllers thin: Form Request → authorize → service/job → API Resource.
- `declare(strict_types=1);` everywhere; run `./vendor/bin/pint` before done.
- No `any` in TS; share API types in `frontend/types/`; mirror them to the API contract.
- Each new endpoint → update `API.md`; each schema change → update `DATA-MODEL.md`.
- Each feature ships a happy path test + at least one failure path test.

---

## Phase 1 — Foundations

Goal: both apps run, auth works, the full schema migrates, the queue processes.

1. **Scaffold backend.** Create `backend/` (Laravel, PHP 8.3+), install Sanctum, configure
   `.env` for local Postgres, `local` filesystem disk, and the database queue connection.
   Add Pint. Verify: `php artisan serve` boots, `php artisan migrate` runs on an empty DB.
2. **Scaffold frontend.** Create `frontend/` (Next.js App Router + TS strict + Tailwind).
   Add a typed `lib/api` client (base URL, bearer-token injection, typed error handling)
   and a `types/` module. Verify: `pnpm dev`, `pnpm lint`, `pnpm typecheck` all pass.
3. **Enable pgvector + queue tables.** Migration enabling `CREATE EXTENSION IF NOT EXISTS
   vector;` plus framework tables (`jobs`, `failed_jobs`, `job_batches`, `cache`,
   `personal_access_tokens`). Verify: `queue:work` starts and drains a test job.
4. **Migrate the full schema** per `DATA-MODEL.md`: `users` (+`role`), `resumes`
   (UNIQUE `user_id`, `skills_synced_at`), `resume_skills` (`vector(1024)`), `analyses`,
   `job_requirements` (`vector(1024)`, nullable `matched_resume_skill_id`),
   `bullet_suggestions`, `cover_letters`, `prompt_templates`, `llm_logs`. Add Eloquent
   models + factories encoding invariants (unique resume, status enums). Use the
   `/create-migration` command. Verify: `migrate:fresh --seed` builds everything; the
   vector columns exist with the right dimension.
5. **Auth (Sanctum).** `register / login / logout / me` per `API.md`; hashed passwords
   (NFR-S1); `role` returned. Form Requests for validation. Verify: register → token →
   `GET /api/auth/me` returns the user; logout revokes the token.
6. **Frontend auth.** Login/register pages, token persistence, protected route group,
   redirect-on-401. Verify: a user can register, log in, land on a protected page, and be
   bounced when unauthenticated.

**Gate:** `migrate:fresh --seed` builds the full schema; a user registers/logs in through
the UI; `queue:work` runs. No business logic yet.

---

## Phase 2 — Resume

Goal: upload, parse, edit, and store exactly one resume per user, with cached skill
embeddings.

1. **Upload + validate + store.** `POST /api/resume` (multipart `file`): Form Request
   validates mime + extension (pdf/docx) and size ≤ 5 MB (NFR-S3); store to the S3 disk
   (`local` in dev) under a private path. Enforce one-per-user via upsert (replace file +
   row). Verify: oversized/wrong-type uploads return `422`; valid upload returns the
   resource.
2. **Parse to text.** `ResumeParser` service (PDF + DOCX → text), called from the upload
   flow; populate `parsed_text`. On parse failure return a clear message (NFR-R3).
   Verify: a real PDF and a real DOCX both yield text; an encrypted/empty PDF returns a
   readable error.
3. **Read / edit / delete.** `GET` (or `404`), `PATCH { parsed_text }` (resets
   `skills_synced_at` to null), `DELETE` (removes row + file). All scoped to the owner
   (NFR-S2). Verify: another user's resume returns `403`/`404`, never data.
4. **Skill extraction + embedding sync job.** `SyncResumeSkillsJob`: if `skills_synced_at`
   is stale, run `extract_skills` (Claude, via `LlmClient`) → embed each via
   `EmbeddingClient` (Voyage) → write `resume_skills` → set `skills_synced_at`. Skip when
   fresh (NFR-C3). (Depends on the Phase 3 service interfaces; if building strictly in
   order, stub the clients here and wire real providers in Phase 3, or pull step 1 of
   Phase 3 forward.) Verify: re-saving unchanged text does not re-embed; changed text does.
5. **Frontend resume UI.** Upload control with loading state (NFR-P4), editable extracted
   text, save via `PATCH`, replace/delete. Verify: upload → edit → save round-trips; UI
   shows progress and errors.

**Gate:** upload a PDF and a DOCX, see editable text, save; re-upload replaces; skills +
embeddings stored once and not recomputed on unchanged text.

---

## Phase 3 — Analysis core (the LLM heart)

Goal: scoring + gap analysis end to end through the queue, with validated structured
output and full logging.

1. **Provider service layer.** `LlmClient` (Anthropic) and `EmbeddingClient` (Voyage)
   behind interfaces (NFR-M2); per-call timeout ~60 s + capped backoff on 429/5xx
   (NFR-R1); each call writes an `llm_logs` row (provider, model, operation, tokens,
   cost, latency, status) with minimal, admin-only payloads (NFR-O1). Cost computed from
   token counts × per-model rates in config. Verify with mocked HTTP: a log row is written
   on success and failure; retries fire on 429/5xx.
2. **Prompt templates in DB.** Seed `prompt_templates` for `extract_skills` and `scoring`
   (content, model, `max_tokens`, `temperature`, `is_active`); load by slug at runtime
   (never hardcode — `CLAUDE.md`). Verify: editing a template row changes behavior with no
   code change.
3. **Structured-output validation.** A schema-validate helper: parse Claude JSON against
   the fixed schema; on invalid output do exactly one corrective retry (feed the error
   back), then fail (NFR-R2). Verify: malformed-then-valid passes after one retry;
   malformed-twice fails the job with a clear error.
4. **`ScoreMatchJob`** per `ARCHITECTURE.md` flow + `LLM-INTEGRATION.md` pipeline: ensure
   resume skills fresh (reuse Phase 2 sync) → extract JD requirements (Claude) → embed
   (Voyage) → nearest `resume_skills` by cosine `<=>`, `similarity = 1 - distance`,
   threshold from config (start 0.75) → mark matched/gap → `scoring` (Claude) for
   breakdown + explanation → persist `job_requirements`, `overall_score`,
   `score_breakdown`, `explanation`; status `queued → processing → completed|failed`,
   atomic writes (NFR-R4). Verify: a differently-worded skill (e.g. "AWS Lambda" ↔
   "serverless") still matches; a genuinely missing one becomes a gap.
5. **Analysis endpoints.** `POST /api/analyses` (creates row `queued`, dispatches job,
   returns `{id, status}`), `GET /api/analyses/{id}` (full result / terminal status),
   `GET /api/analyses/latest`. Owner-scoped. API Resources shape `matched`/`gaps` per
   `API.md`. Verify: poll transitions `queued → processing → completed`; failure surfaces
   `error_message`.
6. **Frontend analysis flow.** Paste JD (validate non-empty/min length), run, poll
   `GET {id}` every ~2 s until terminal (NFR-P2), render score + breakdown + matched +
   gaps; double-submit guard (NFR-R5). Verify: full happy path on a real resume + JD.

**Gate:** a real resume + JD returns a 0–100 score, a category breakdown, and a
matched/gaps list where a differently-worded skill is matched.

---

## Phase 4 — Bullets + cover letter

Goal: the two generation features with user control and export, each its own job/status.

1. **Bullet rewrite.** Seed `bullet_rewrite` template (few-shot strong-bullet examples in
   content, NFR-C4). `RewriteBulletsJob`: detect weak bullets, rewrite against JD/gaps,
   persist `bullet_suggestions` (`pending`). Endpoints: `POST /api/analyses/{id}/bullets`
   (dispatch), `GET` (list), `PATCH /api/bullets/{bulletId}` (`accept|reject|edit` +
   `edited_text`). Verify: weak bullets get targeted rewrites; accept/reject/edit persists
   per bullet.
2. **Cover letter generate.** Seed `cover_letter` template. `GenerateCoverLetterJob` with
   params `tone`/`length`/`language`; `POST /api/analyses/{id}/cover-letter` dispatches
   generate or regenerate (overwrites `content`, no history); own status. Stream output
   where it helps UX (NFR-P3). Verify: generation completes; regenerate replaces content.
3. **Cover letter edit + export.** `PATCH` saves manual edits; `GET …/export?format=pdf|
   docx` returns the file. Verify: edit persists; both PDF and DOCX download and open.
4. **Frontend.** Bullet review UI (accept/reject/edit inline), cover-letter panel
   (params, generate, regenerate, edit, export buttons). Verify: end-to-end from analysis
   result → bullets → cover letter → download.

**Gate:** weak bullets get rewrites the user can accept/reject/edit; a cover letter
generates, edits, regenerates, and downloads as PDF and DOCX.

---

## Phase 5 — Admin, hardening, deploy

Goal: admin panel, production hardening, and a working AWS deployment.

1. **Admin API + UI** (role-gated, NFR-S6): prompt-template list/get/`PUT` (edit with no
   deploy, NFR-M1); `GET /api/admin/usage` (aggregate `llm_logs` tokens/cost, FR-6.2);
   `GET /api/admin/llm-logs` (paginated, admin-only payloads); `GET /api/admin/users`.
   Frontend `/admin` route group gated by `role`. Verify: editing a prompt changes output
   with no redeploy; usage shows tokens/cost; non-admins get `403`.
2. **Hardening.** Rate limit LLM-triggering endpoints (NFR-S7); double-submit/dedup guard
   (NFR-R5); friendly errors everywhere (NFR-U2, no stack traces); health-check endpoint
   (NFR-O3); correlation id per analysis run (NFR-O2). Verify: rapid duplicate submits are
   blocked; `/health` returns OK.
3. **Test pass.** Ensure each feature has happy + one failure path (LLM failure, invalid
   upload, structured-output failure, authz denial). Verify: `php artisan test` and
   `pnpm test` green; `typecheck`/`lint`/Pint clean.
4. **GDPR.** Full account + data deletion (resume, analyses, files, logs); disclose
   Anthropic + Voyage as sub-processors (NFR-S5). Verify: deletion removes all personal
   data including S3 files.
5. **Deploy** per `DEPLOYMENT.md`: RDS Postgres + pgvector, private S3, web tier, worker
   tier, secrets via Secrets Manager (NFR-S4), run migrations, smoke test. Verify: a full
   end-to-end run (upload → analyze → bullets → cover letter) succeeds on AWS with the
   queue worker processing jobs.

**Gate:** admin edits a prompt and sees the effect without redeploying; usage shows
tokens/cost; the app runs on AWS with a working queue and a successful end-to-end run.

---

## Cross-cutting / do-not-skip

These must hold in every phase, not just at the end:

- No LLM/embedding call outside `LlmClient`/`EmbeddingClient`; never from a controller.
- All heavy work in queued jobs; HTTP handlers return an id and the client polls.
- Every LLM/embedding call logs to `llm_logs`; structured output is schema-validated.
- Resume embeddings recompute only when `parsed_text` changes.
- Authorization is server-side; a user never reads another user's data.
- Prompts live in `prompt_templates`, loaded by slug — never hardcoded.

## Sequencing notes

- Phase 2 step 4 (skill sync) depends on the provider interfaces from Phase 3 step 1.
  Either stub the clients in Phase 2 and swap in the real providers in Phase 3, or pull
  Phase 3 step 1 forward — it is the one cross-phase dependency.
- Everything else follows the listed order. Phases 3 and 4 are the highest-risk (LLM
  correctness, structured output); leave buffer there.
- Cut order if scope tightens (from `ROADMAP.md`): WebSocket push → DOCX export → admin
  log viewer. Never cut: auth scoping, structured-output validation, queue execution,
  embedding cache.
