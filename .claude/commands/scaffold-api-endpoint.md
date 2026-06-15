Scaffold a REST API endpoint for: $ARGUMENTS

Follow the project conventions:
- Keep the controller thin: validate with a Form Request, authorize ownership/role,
  delegate to a service, and return an API Resource. No business logic in the controller.
- Put any LLM/embedding work behind the `LlmClient` / `EmbeddingClient` interfaces and
  run heavy operations as a queued job — never inline in the request.
- Enforce server-side authorization (a user touches only their own resume/analyses;
  admin routes require `role = admin`).
- Match the contract in `docs/API.md` (path, method, request body, response shape,
  status codes, error format). If the endpoint is new or changed, update `docs/API.md`.
- Add a happy-path test and at least one failure-path test.
- Type everything (`declare(strict_types=1);`), then run Pint.
