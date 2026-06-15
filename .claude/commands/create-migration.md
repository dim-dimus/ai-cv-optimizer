Create a Laravel migration (and Eloquent model + factory if the table is new) for: $ARGUMENTS

Follow these rules:
- Match the schema and conventions in `docs/DATA-MODEL.md` exactly — column names,
  types, enums, nullability, and indexes.
- For vector columns use `vector(1024)` via a raw `DB::statement` (and ensure
  `CREATE EXTENSION IF NOT EXISTS vector;` runs before any vector column is created).
- Add foreign keys with the correct on-delete behavior and the indexes listed in the
  data model.
- Enforce invariants in the schema where possible (e.g. unique `resumes.user_id`).
- Add `declare(strict_types=1);`, type the model, and update `docs/DATA-MODEL.md` if
  this introduces anything new.
- Do not run the migration; show me the files and the SQL it will produce.
