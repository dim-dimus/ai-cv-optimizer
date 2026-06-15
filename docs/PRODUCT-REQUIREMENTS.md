# Product Requirements

## Vision

Help a job seeker tailor their application to a specific role: measure how well their
resume matches a job description, surface concrete gaps, rewrite weak bullet points,
and draft a cover letter — fast.

## Roles

- **User** — uploads/updates their resume, runs analyses, reviews bullet suggestions,
  generates a cover letter.
- **Admin** — edits prompt templates, monitors LLM usage and logs, views the user list.

## Primary flow

Upload / update resume → paste job description → run analysis → review score + gaps →
accept/reject/edit rewritten bullets → generate cover letter → export.

## Functional requirements

Legend: **[MVP]** ships in the initial build; **[Later]** is post-MVP.

### 1. Resume & job description
- **[MVP]** Upload resume as PDF/DOCX (file only, no paste).
- **[MVP]** Parse the file to text on the backend.
- **[MVP]** Show extracted text in an editable field so the user can fix parser noise.
- **[MVP]** Store the resume. **One user = one resume**, no version history.
- **[MVP]** Update/replace the stored resume.
- **[MVP]** Reuse the stored resume as analysis input without re-uploading.
- **[MVP]** Validate file: type (pdf/docx), size ≤ 5 MB.
- **[MVP]** Paste job description as text.
- **[MVP]** Validate job description: non-empty, minimum length.
- **[Later]** Extract job description from a URL.

### 2. Match scoring
- **[MVP]** Overall score 0–100.
- **[MVP]** Category breakdown: hard skills, soft skills, experience/seniority,
  education, keywords.
- **[MVP]** Matched skills and gaps (missing requirements).
- **[MVP]** Semantic comparison via embeddings — detect job requirements absent from
  the resume even when worded differently (e.g. "AWS Lambda" ↔ "serverless").
- **[MVP]** Plain-language explanation of the score.
- **[MVP]** Stable structured output (fixed JSON schema, validated server-side).

### 3. Bullet improvement
- **[MVP]** Detect weak bullets (no metrics, passive voice, generic phrasing).
- **[MVP]** Propose a rewrite targeted at the job's requirements.
- **[MVP]** Few-shot examples of strong bullets in the prompt for consistent style.
- **[MVP]** User can accept / reject / edit each suggestion (persisted per bullet).
- **[Later]** Side-by-side before/after view.

### 4. Cover letter
- **[MVP]** Generate a cover letter from resume + job description + matches.
- **[MVP]** Parameters: tone, length, language.
- **[MVP]** Regenerate and manually edit.
- **[MVP]** Export to PDF / DOCX.

### 5. History
- **[Later]** Persist analyses for browsing.
- **[Later]** View past analyses.
- **[Later]** Compare resume versions.

> Note: the `analyses` table exists in MVP for job tracking and to return the current
> result. "History" as a browsable feature is Later; MVP shows the latest analysis.

### 6. Admin panel
- **[MVP]** View/edit prompt templates without a deploy (stored in DB).
- **[MVP]** LLM monitoring: request count, tokens, estimated cost.
- **[MVP]** LLM request/response logs for debugging.
- **[MVP]** User list and activity.
- **[Later]** Per-user rate limits / quotas.

### 7. Auth
- **[MVP]** Register with email + password.
- **[MVP]** Login / logout.
- **[MVP]** Resume and analyses are scoped to the owning user.
- **[Later]** Password reset, email verification.

### 8. Multilingual
- **[Later]** Auto-detect CV/job language.
- **[Later]** Cover letter in the job's language with override.
- **[Later]** UI internationalization.
- **MVP constraint:** analysis and UI are English-only.

## Out of scope (by choice)

Team/collaboration, sharing, payments/subscriptions, job-board integrations,
full ATS simulation. List as "future work" in the README.
