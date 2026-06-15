# AI CV Optimizer

Paste a CV and a job description, get a structured match score, gap analysis,
rewritten bullet points, and a tailored cover letter. The semantic matching layer
uses embeddings; the reasoning and generation use Claude.

This is a learning project built full-stack with Claude Code as the development agent.

## Stack

| Layer        | Choice                                              |
|--------------|-----------------------------------------------------|
| Frontend     | Next.js (App Router) + React + TypeScript           |
| Admin panel  | Same Next.js app, `/admin` route group              |
| Backend      | Laravel (PHP) REST API                              |
| Auth         | Laravel Sanctum (token-based, SPA)                  |
| Database     | PostgreSQL + `pgvector`                             |
| Queue        | Laravel Queue (database/Redis driver) + workers     |
| File storage | Amazon S3 (local disk in dev)                       |
| LLM          | Anthropic Claude (generation/reasoning)             |
| Embeddings   | Voyage AI `voyage-3-large` → `vector(1024)`         |
| Deploy       | AWS (RDS, S3, container/web tier, worker tier)      |

> Anthropic does not provide an embeddings API, so embeddings come from Voyage.
> See `docs/LLM-INTEGRATION.md`.

## Repository structure

```
ai-cv-optimizer/
├── frontend/          # Next.js app (UI + admin panel)
├── backend/           # Laravel API + queue jobs
├── docs/              # all project documentation (read these first)
├── CLAUDE.md          # agent context — Claude Code reads this automatically
└── .claude/commands/  # custom slash commands
```

## Prerequisites

- Node.js 20+ and pnpm (or npm)
- PHP 8.3+ and Composer
- Docker (for the local PostgreSQL + `pgvector` container), or your own PostgreSQL 16+
  with the `pgvector` extension available
- An Anthropic API key and a Voyage AI API key (only needed once LLM features land)

## Running locally

The local database runs in Docker (`docker-compose.yml`) as a `pgvector` image. To avoid
clashing with any other Postgres on the default port, it is published on host port
**5433**. The backend `.env.example` is already pointed at it.

### First-time setup

```bash
# from the repo root — start PostgreSQL (pgvector) in the background
docker compose up -d

# Backend
cd backend
composer install
cp .env.example .env            # already targets the 5433 pgvector container
php artisan key:generate
php artisan migrate              # builds the full schema (enables the vector extension)

# create the separate database the test suite uses
docker exec ai-cv-optimizer-postgres \
  psql -U app -d ai_cv_optimizer -c "CREATE DATABASE ai_cv_optimizer_test;"
docker exec ai-cv-optimizer-postgres \
  psql -U app -d ai_cv_optimizer_test -c "CREATE EXTENSION IF NOT EXISTS vector;"

# Frontend
cd ../frontend
pnpm install
cp .env.example .env.local       # sets NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

### Start everything (day to day)

Run each in its own terminal:

```bash
docker compose up -d                       # 1. database (skip if already running)
cd backend && php artisan serve            # 2. API     → http://localhost:8000
cd backend && php artisan queue:work       # 3. queue worker (required for any analysis)
cd frontend && pnpm dev                    # 4. UI      → http://localhost:3000
```

### Stop everything

```bash
# stop the API, queue worker, and frontend: Ctrl+C in each terminal, or:
pkill -f "artisan serve"; pkill -f "queue:work"; pkill -f "next dev"

# stop the database container (data is preserved in the pgdata volume)
docker compose down

# to also wipe the database data, remove the volume too:
docker compose down -v
```

Backend defaults to `http://localhost:8000`, frontend to `http://localhost:3000`,
PostgreSQL to `localhost:5433`.

### Useful checks

```bash
cd backend  && php artisan test            # backend tests (uses ai_cv_optimizer_test)
cd backend  && ./vendor/bin/pint           # format PHP
cd frontend && pnpm lint && pnpm typecheck # frontend lint + types
```

## Documentation index

Start with `CLAUDE.md`, then read in this order:

1. `docs/PRODUCT-REQUIREMENTS.md` — what we are building (scope, MVP vs Later)
2. `docs/NON-FUNCTIONAL-REQUIREMENTS.md` — performance, security, cost, reliability
3. `docs/ARCHITECTURE.md` — components and data/job flows
4. `docs/DATA-MODEL.md` — database schema
5. `docs/API.md` — endpoints and contracts
6. `docs/LLM-INTEGRATION.md` — prompts, structured output, embedding pipeline
7. `docs/ROADMAP.md` — the phased build plan
8. `docs/DEPLOYMENT.md` — AWS deployment

## Scope (MVP)

English-only analysis, accounts (email + password), one resume per user,
PDF/DOCX upload, scoring + gaps + bullet rewrites + cover letter, admin panel for
prompt templates and LLM usage. Multilingual support, analysis history, and
per-user quotas are explicitly post-MVP — see the requirements docs.
