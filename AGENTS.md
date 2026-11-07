# Shared agent instructions

## Project

Honey Shop Management System: a web-based business-management application for a shop selling honey, ghee, and nuts. The detailed original requirements are in [Software Requirements Analysis & Development Plan.md](Software%20Requirements%20Analysis%20%26%20Development%20Plan.md); current approved scope and decisions are in [docs/DECISIONS.md](docs/DECISIONS.md).

## Actual technology and status

- Database: PostgreSQL 17 (the running development server reported 17.11).
- Local database service: Docker Compose using `postgres:17-alpine`.
- Schema: versioned PostgreSQL SQL migrations in `database/migrations`.
- The repository currently has no application source, package/dependency manifest, backend, frontend, CI configuration, or application test runner. Do not present any as already established.
- .NET SDK 10 is installed on the current workstation, but .NET is not an approved project choice. The recommended application stack is documented as pending approval in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) and [docs/DECISIONS.md](docs/DECISIONS.md).

## Start every task

1. Read this file, the relevant documents under `docs`, and the applicable sections of the requirements plan.
2. Inspect the current files and existing implementation before creating an alternative or parallel implementation.
3. Check Git with `git status --short --branch` before editing. This workspace has not been detected as a Git repository; report that fact and do not initialize one without approval.
4. For database work, inspect Docker Compose health, PostgreSQL version, `schema_migrations`, relevant data, constraints, indexes, and triggers. Verify actual database state; a migration file alone does not prove it ran.
5. Preserve pre-existing work. Never reset, clean, delete, or overwrite unrelated files or data. If a change risks losing existing records, stop and report the exact conflict.
6. Resolve business or architecture ambiguity from approved decisions where possible. Otherwise document it and ask before making a material choice.

## Architecture and coding rules

- Follow the architecture actually present. Until a backend is approved, make database/documentation changes only; do not scaffold an application.
- Keep business rules out of presentation code. When the application is authorized, use a small modular application with clear service/data-access boundaries and database transactions for related writes.
- Reuse existing code and patterns. Do not create a second implementation of an existing capability.
- Use descriptive names. Existing SQL uses lowercase `snake_case` tables/columns and uppercase SQL keywords; preserve that convention. Migration filenames are sequential and descriptive, for example `010_short_business_change.sql`.
- Prefer small, focused changes. Do not add a dependency, service, abstraction, or feature without a documented need.
- Validate inputs server-side. Use parameterized database operations. Never place secrets in source or documentation; keep local credentials in ignored `.env`.
- Do not log passwords, tokens, or sensitive customer data. Surface failures; do not silently ignore database or validation errors.
- Do not implement screens or business modules outside the task's requested scope.

## Database management

- PostgreSQL 17 is the source database. Existing applied migrations are `001` through `009`; verify with `schema_migrations` before work.
- Never edit an applied migration. Add the next numbered forward migration.
- Inspect data and provide a preflight guard before any migration that could reject, transform, or remove existing records. Never drop/recreate the database or Docker volume.
- Each migration must be transactional when PostgreSQL supports it. Keep seeds idempotent and migration history accurate.
- Preserve YER-only rules, KG/CAN base units, KG/GALLON/CAN entered units, 7 KG per honey gallon, nonnegative inventory, source-idempotent movements, line-specific returns, full-only order cancellation, and voucher-linked expense payments.
- Keep database logic and application responsibilities explicit. Unit compatibility, authorization, delivery expense orchestration, representative-account GetOrCreate, and coordinated business workflows belong in application services as documented.

## Commands

Run from the project root in PowerShell:

```powershell
docker compose ps
docker compose exec -T postgres psql -U honey_shop -d honey_shop -c "SELECT current_setting('server_version');"
docker compose exec -T postgres psql -U honey_shop -d honey_shop -c "SELECT version FROM schema_migrations ORDER BY version;"
Get-Content database\tests\003_business_rules_simplification.sql -Raw |
  docker compose exec -T postgres psql -U honey_shop -d honey_shop -v ON_ERROR_STOP=1
```

The database test script wraps fixtures in a transaction and rolls them back. Check its exit code and output; do not claim tests passed unless executed.

To apply a pending migration, first verify its version is absent from `schema_migrations`, then pipe that migration's SQL to `docker compose exec -T postgres psql -U honey_shop -d honey_shop -v ON_ERROR_STOP=1`. Apply migrations in filename order. See [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md).

## Git and collaboration

- Check branch/status and inspect diffs before editing and before handoff.
- Do not discard another contributor's edits, amend or create commits, switch branches, or stage changes unless explicitly requested.
- Keep migration and documentation edits scoped to the task. Avoid multiple agents independently editing the same files; coordinate ownership and review changes together.
- This directory currently is not inside a Git repository. Do not imply a clean working tree or branch exists.

## Handoff

Keep the repository self-describing: update the directly related documentation when behavior or workflow changes. Report the verified database/migration state, commands and test results, modified files, unresolved decisions, and next recommended step. Do not rely on conversation history as project documentation.
