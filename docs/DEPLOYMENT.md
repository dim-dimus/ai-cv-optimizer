# Deployment (AWS)

Target: a small but production-shaped setup. Prefer an EU region (e.g. `eu-central-1`,
Frankfurt) for GDPR (NFR-S5).

## Topology

- **Database** — RDS for PostgreSQL (16+). `pgvector` is available as an RDS extension;
  enable it: `CREATE EXTENSION IF NOT EXISTS vector;` after first connect.
- **File storage** — private S3 bucket for uploaded resumes. Block public access; access
  via IAM role.
- **Web tier** — the Laravel API. Containerized on ECS Fargate (or a single EC2 /
  Elastic Beanstalk environment for simplicity).
- **Worker tier** — a separate service/process running `php artisan queue:work` so LLM
  jobs never share capacity with web requests (NFR-D2). Use the database or Redis
  (ElastiCache) queue driver.
- **Frontend** — Next.js on Amplify Hosting or Vercel, or containerized alongside the
  API. Set `NEXT_PUBLIC_API_URL` to the API's URL.
- **Secrets** — AWS Secrets Manager / SSM Parameter Store for `ANTHROPIC_API_KEY`,
  `VOYAGE_API_KEY`, DB credentials, `APP_KEY`. Never in images or the repo (NFR-S4).

## Environment variables

Backend (`.env`):
```
APP_ENV=production
APP_KEY=                      # php artisan key:generate
DB_CONNECTION=pgsql
DB_HOST=...rds.amazonaws.com
DB_DATABASE=cv_optimizer
DB_USERNAME=...
DB_PASSWORD=                  # from Secrets Manager
QUEUE_CONNECTION=database     # or redis
FILESYSTEM_DISK=s3
AWS_BUCKET=...
AWS_DEFAULT_REGION=eu-central-1
ANTHROPIC_API_KEY=            # from Secrets Manager
VOYAGE_API_KEY=               # from Secrets Manager
EMBEDDING_MODEL=voyage-3-large
EMBEDDING_DIM=1024
MATCH_THRESHOLD=0.75
```

Frontend:
```
NEXT_PUBLIC_API_URL=https://api.example.com
```

## Deploy steps

1. Provision RDS (pgvector), S3, and the container registry (ECR).
2. Push web + worker images; deploy both services (same image, different command).
3. Run `php artisan migrate --force` once (a one-off task / release command).
4. Confirm the worker is processing (`queue:work` healthy) — analyses never complete
   without it.
5. Deploy the frontend; point it at the API URL.
6. Smoke test: register → upload resume → run an analysis → verify a completed result.

## Operational notes

- **Health check** — expose `/api/health` (DB + queue reachable) for the load balancer
  (NFR-O3).
- **Migrations safety** — `vector(1024)` is fixed; changing the embedding model is a
  migration + re-embed of all resumes. Treat as a planned operation.
- **Cost guardrails** — the LLM-endpoint rate limit (NFR-S7) and `max_tokens`/input caps
  (NFR-C2) bound spend; watch the admin usage dashboard.
- **Backups** — enable RDS automated backups; S3 versioning optional.
- **Logs** — ship app and worker logs to CloudWatch; remember `llm_logs` payloads hold
  personal data, keep access restricted.
