# API

REST/JSON under `/api`. Auth via Sanctum bearer token in `Authorization: Bearer <token>`.
All timestamps ISO 8601 UTC. Heavy operations return a resource with a `status` the
client polls.

## Conventions

- Success: `2xx` with the resource as JSON.
- Validation error: `422` with `{ "message", "errors": { field: [..] } }`.
- Auth error: `401`; forbidden (wrong owner / not admin): `403`; not found: `404`.
- Other failures: `4xx/5xx` with `{ "message" }` (human-readable, no stack traces).
- A resource owned by another user returns `403`/`404`, never its data.

## Auth

| Method | Path                 | Body                          | Notes                         |
|--------|----------------------|-------------------------------|-------------------------------|
| POST   | `/api/auth/register` | `{ name, email, password }`   | returns user + token          |
| POST   | `/api/auth/login`    | `{ email, password }`         | returns user + token          |
| POST   | `/api/auth/logout`   | —                             | revokes current token         |
| GET    | `/api/auth/me`       | —                             | current user                  |

## Resume (one per user)

| Method | Path                 | Body / form                   | Notes                                   |
|--------|----------------------|-------------------------------|-----------------------------------------|
| GET    | `/api/resume`        | —                             | current resume or `404` if none         |
| POST   | `/api/resume`        | multipart `file` (pdf/docx)   | upload + parse; creates or replaces     |
| PATCH  | `/api/resume`        | `{ parsed_text }`             | save edited text; resets skill cache    |
| DELETE | `/api/resume`        | —                             | remove resume + file                    |

`GET` / `POST` / `PATCH /api/resume` return the resource wrapped in a `data`
envelope (Laravel API Resource convention):
```json
{
  "data": {
    "id": 1,
    "original_filename": "cv.pdf",
    "parsed_text": "…extracted…",
    "language": "en",
    "skills_synced_at": null,
    "updated_at": "2026-06-15T10:39:49+00:00"
  }
}
```
`POST` returns `201` on first upload, `200` when replacing an existing resume. The
client shows `parsed_text` in an editable field and saves edits via `PATCH`. After
upload or edit, skill extraction + embeddings run on the queue; `skills_synced_at`
becomes non-null once the sync completes. Invalid uploads (wrong type, > 5 MB) return
`422`; an unparseable file returns `422` with a readable `message`. `DELETE` returns
`{ "message": "Resume deleted." }`.

## Analyses

| Method | Path                          | Body                  | Notes                                  |
|--------|-------------------------------|-----------------------|----------------------------------------|
| POST   | `/api/analyses`               | `{ job_description }` | uses stored resume; dispatches job     |
| GET    | `/api/analyses/{id}`          | —                     | full result; poll until terminal status|
| GET    | `/api/analyses/latest`        | —                     | most recent analysis (MVP home view)   |

Responses are wrapped in a `data` envelope (API Resource convention). `POST` returns
`201` with a stored resume required first — without one it returns `422`. `job_description`
must be 30–20000 characters.

`POST /api/analyses` → `201`:
```json
{ "data": { "id": 42, "status": "queued" } }
```

`GET /api/analyses/{id}` when completed:
```json
{
  "data": {
    "id": 42,
    "status": "completed",
    "overall_score": 78,
    "score_breakdown": {
      "hard_skills": 80, "soft_skills": 70, "experience": 85,
      "education": 60, "keywords": 75
    },
    "explanation": "Strong backend match; missing explicit CI/CD and Kubernetes.",
    "completed_at": "2026-06-15T13:18:26+00:00",
    "matched": [
      { "requirement": "AWS Lambda", "matched_skill": "serverless backends on AWS", "similarity": 0.86 }
    ],
    "gaps": [
      { "requirement": "Kubernetes", "category": "hard_skill" }
    ]
  }
}
```
While running: `{ "data": { "id": 42, "status": "processing" } }`. On failure:
`{ "data": { "id": 42, "status": "failed", "error_message": "…" } }`. Reading another
user's analysis returns `404`.

## Bullet suggestions

| Method | Path                                         | Body                         | Notes                          |
|--------|----------------------------------------------|------------------------------|--------------------------------|
| POST   | `/api/analyses/{id}/bullets`                 | —                            | dispatch rewrite job           |
| GET    | `/api/analyses/{id}/bullets`                 | —                            | list with statuses             |
| PATCH  | `/api/bullets/{bulletId}`                    | `{ status, edited_text? }`   | accept / reject / edit         |

Bullet item:
```json
{ "id": 7, "original_text": "Worked on backend", "suggested_text": "Built and scaled a NestJS microservice handling 2M req/day",
  "rationale": "Adds scope and a metric", "status": "pending", "edited_text": null, "position": 0 }
```

## Cover letter

| Method | Path                                | Body                              | Notes                       |
|--------|-------------------------------------|-----------------------------------|-----------------------------|
| POST   | `/api/analyses/{id}/cover-letter`   | `{ tone, length, language }`      | dispatch generate/regen job |
| GET    | `/api/analyses/{id}/cover-letter`   | —                                 | status + content            |
| PATCH  | `/api/analyses/{id}/cover-letter`   | `{ content }`                     | save manual edits           |
| GET    | `/api/analyses/{id}/cover-letter/export?format=pdf|docx` | —          | returns the file            |

## Admin (role: admin)

| Method | Path                                 | Body                                  | Notes                       |
|--------|--------------------------------------|---------------------------------------|-----------------------------|
| GET    | `/api/admin/prompt-templates`        | —                                     | list                        |
| GET    | `/api/admin/prompt-templates/{slug}` | —                                     | single                      |
| PUT    | `/api/admin/prompt-templates/{slug}` | `{ content, model, max_tokens, temperature, is_active }` | edit, no deploy |
| GET    | `/api/admin/usage`                   | `?from&to`                            | token/cost aggregates       |
| GET    | `/api/admin/llm-logs`                | `?operation&status&page`              | request/response logs       |
| GET    | `/api/admin/users`                   | `?page`                               | user list + activity        |

## Polling

The client polls `GET` on the relevant resource (analysis / bullets / cover letter)
every ~2 s while `status ∈ {queued, processing}`, stopping at `completed` or `failed`.
WebSocket push (Laravel Echo) is an optional enhancement; polling is the MVP baseline.
