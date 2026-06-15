Review the current diff (or the PR / branch referenced in: $ARGUMENTS) against this
project's rules. Report findings grouped by severity (blocker / should-fix / nit).

Check specifically:
- Architecture: no LLM/embedding calls outside the service interfaces; heavy work is
  queued, not inline; prompts are loaded from `prompt_templates`, not hardcoded.
- Structured output: any LLM JSON response is schema-validated with a corrective retry.
- Data invariants: one resume per user; `vector(1024)` unchanged; status enums correct;
  gaps have a null `matched_resume_skill_id`.
- Security/privacy: no secrets in code or sent to the client; uploads validated; server-
  side authorization; `llm_logs` payloads minimal and admin-only.
- Cost: input/`max_tokens` caps; embedding cache respected (no re-embedding unchanged
  resumes).
- Quality: strict types, Pint/eslint clean, errors human-readable, tests cover a happy
  and a failure path.
- Docs: `docs/API.md` and `docs/DATA-MODEL.md` updated if contracts/schema changed.

Be concrete: cite file and line, and propose the fix. Do not rubber-stamp.
