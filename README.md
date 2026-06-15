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
- PostgreSQL 16+ with the `pgvector` extension available
- An Anthropic API key and a Voyage AI API key

## Quickstart

```bash
# 1. Backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
# set DB_*, ANTHROPIC_API_KEY, VOYAGE_API_KEY, AWS_* in .env
php artisan migrate
php artisan queue:work          # run in a separate terminal

# 2. Frontend
cd ../frontend
pnpm install
cp .env.example .env.local      # set NEXT_PUBLIC_API_URL
pnpm dev
```

Backend defaults to `http://localhost:8000`, frontend to `http://localhost:3000`.

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
