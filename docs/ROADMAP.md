# Roadmap — MVP, step by step

The MVP is broken into logical phases that build on one another. Each phase has a goal,
tasks sized for individual Claude Code sessions, and acceptance criteria. Treat each task
as a prompt to the agent; point it at the relevant `docs/` file. Work the phases in order —
each one assumes the previous is done. Keep "Later" items out unless asked.

## Phase 1 — Foundations

Goal: both apps run, auth works, schema exists.

- Scaffold `backend/` (Laravel) and `frontend/` (Next.js + TS + Tailwind).
- Postgres + pgvector locally; enable the extension; configure the queue + filesystem
  (`local`) disks.
- Migrations for all tables in `docs/DATA-MODEL.md` (including `vector(1024)` columns).
- Sanctum auth: register / login / logout / me; `role` on users.
- Frontend: auth pages, typed API client, protected routing.

Acceptance: a user can register, log in, hit `/api/auth/me`; `migrate:fresh` builds the
full schema; `queue:work` runs.

## Phase 2 — Resume

Goal: upload, parse, edit, store one resume per user.

- `POST /api/resume` upload (pdf/docx), validate (mime/ext, ≤ 5 MB), store file in S3
  disk, parse to text (PDF + DOCX parsers).
- `GET` / `PATCH` / `DELETE /api/resume`; enforce one-per-user (unique + upsert).
- `ExtractSkills` + embedding sync job; populate `resume_skills`; set `skills_synced_at`;
  skip when cache fresh.
- Frontend: upload UI, editable extracted-text field, save.

Acceptance: upload a PDF and a DOCX, see editable text, save; re-upload replaces; skills
+ embeddings are stored once and not recomputed on unchanged text.

## Phase 3 — Analysis core (the LLM heart)

Goal: scoring + gap analysis end to end via the queue.

- `LlmClient` (Anthropic) and `EmbeddingClient` (Voyage) behind interfaces; `llm_logs`
  writing.
- `prompt_templates` seeded (`extract_skills`, `scoring`); load by slug.
- `ScoreMatchJob`: extract JD requirements → embed → pgvector nearest-match →
  matched/gaps → Claude scoring → persist; status transitions.
- `POST /api/analyses`, `GET /api/analyses/{id}`, `latest`; structured-output validation
  + corrective retry.
- Frontend: paste JD, run, poll status, render score + matched + gaps.

Acceptance: a real resume + JD returns a 0–100 score, a category breakdown, and a
matched/gaps list where a differently-worded skill is still matched.

## Phase 4 — Bullets + cover letter

Goal: the two generation features with user control and export.

- `bullet_rewrite` template + `RewriteBulletsJob`; `bullet_suggestions` with
  accept/reject/edit (`PATCH /api/bullets/{id}`).
- `cover_letter` template + `GenerateCoverLetterJob`; params (tone/length/language);
  regenerate + manual edit; export to PDF/DOCX.
- Frontend: bullet review UI (accept/reject/edit), cover-letter panel + export.

Acceptance: weak bullets get targeted rewrites the user can accept/reject/edit; a cover
letter generates, edits, regenerates, and downloads as PDF and DOCX.

## Phase 5 — Admin, hardening, deploy

Goal: admin panel, polish, ship to AWS.

- Admin: prompt-template editor (no deploy), usage dashboard (aggregate `llm_logs`),
  log viewer, user list; gate by `role`.
- Hardening: rate limit on LLM endpoints, double-submit guard, friendly errors,
  health-check endpoint, basic tests (happy + one failure path each).
- Deploy per `DEPLOYMENT.md`: RDS (pgvector), S3, web tier, worker tier, secrets, run
  migrations, smoke test.

Acceptance: admin edits a prompt and sees the effect without redeploying; usage shows
tokens/cost; the app runs on AWS with a working queue and a successful end-to-end run.

## Scope control / cut lines

If scope needs trimming, cut in this order: WebSocket push (keep polling), DOCX export
(keep PDF), admin log viewer (keep usage). Never cut: auth scoping, structured-output
validation, queue-based execution, embedding cache.

## Working with Claude Code

- Start each session by pointing at the relevant doc: "implement the Phase 3 ScoreMatchJob
  per docs/LLM-INTEGRATION.md and docs/DATA-MODEL.md".
- Use the slash commands in `.claude/commands/` for repetitive scaffolding.
- After each feature, ask the agent to update `docs/API.md` / `docs/DATA-MODEL.md` if the
  contract or schema changed (it is in the Definition of Done in `CLAUDE.md`).
