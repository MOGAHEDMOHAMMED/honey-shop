# Shared agent instructions

## Project purpose and current state

Honey Shop is a web management system for a shop selling honey, ghee, and nuts. This repository contains the Laravel application, project documentation, PostgreSQL 17 Docker Compose environment, existing SQL schema migrations, and PostgreSQL integration tests.

Current implementation:

- Laravel 13.40.0, PHP 8.3.36, Composer 2.8.10.
- PostgreSQL 17.11 with migrations `001`–`009` applied to the development database.
- Blade, Bootstrap 5.3.8 RTL, vanilla JavaScript, and Vite 8.3.6.
- Existing `app_users`, roles, and permissions tables for authentication/authorization.
- Functional login, health checks, product categories, and products.
- The host PHP CLI is 8.0.30 and is incompatible with Laravel 13. Use the PHP 8.3 Compose service for Artisan and Composer commands.
- See [docs/ROADMAP.md](docs/ROADMAP.md) for what has not been implemented.

## Start every task

1. Read this file and the relevant project documents under `docs`.
2. Inspect the existing implementation before creating an alternative. Check `git status --short --branch` and preserve staged, unstaged, and untracked work.
3. For database work, check Compose health, PostgreSQL version, `schema_migrations`, relevant records, constraints, indexes, and triggers. Do not infer database state from files alone.
4. Read the applicable existing SQL migrations and tests before changing database behavior.
5. Keep changes scoped. Ask before changing unresolved business rules or making destructive changes.

## Approved technologies and architecture

- One Laravel modular monolith; do not add another backend or a separate frontend.
- PHP 8.3+, Laravel 13, PostgreSQL 17, Eloquent/Query Builder, Blade, Bootstrap 5 RTL, vanilla JavaScript, and Vite.
- Use Laravel conventions: thin controllers, Form Requests for validation, focused services for coordinated business rules, Eloquent models with explicit schema mappings, and Gates/Policies for authorization.
- Do not add React, Vue, TypeScript, Livewire, Inertia, Redis, microservices, repository frameworks, or unnecessary abstractions/packages.
- Keep user-facing text Arabic and mark pages `lang="ar"` and `dir="rtl"`. Do not add runtime CDN dependencies.

## Database and migration rules

- The existing PostgreSQL schema is authoritative. Current applied SQL versions are `001` through `009`.
- SQL files in `database/migrations` are the only schema migration system. Never edit an applied migration or run `php artisan migrate`, `migrate:fresh`, or `db:wipe`.
- Any schema change must be a reviewed, forward-only SQL migration with the next sequence. Inspect affected data first and stop if preservation requires an unresolved transformation.
- Do not create Laravel migration files or duplicate existing tables. Map Eloquent names, UUID keys, timestamps, enum values, generated columns, and relationships to the real schema.
- Use transactions for multi-step writes. Respect constraints and triggers; do not disable them to make a request pass.
- Business rules include YER only; honey/nuts `KG`, ghee `CAN`; honey gallon conversion `7 KG`; nonnegative stock; source-linked, idempotent inventory movement; line-specific returns; and append-only financial/inventory ledgers.
- The current schema has **no `product_prices` table**. Applied migration `003` removed it; selling price and maximum discount ratio live on `products`. Do not recreate it without an approved forward schema change.

## Security and validation

- Validate all input server-side. Use parameterized Eloquent/Query Builder operations.
- Store passwords only as hashes through Laravel `Hash`; never log passwords or session secrets.
- Use the existing `app_users`, `roles`, `permissions`, `user_roles`, and `role_permissions` tables. Do not introduce a parallel identity or permission system.
- Keep `.env` and secret files ignored. Commit `.env.example`, never local credentials.
- Keep debug output disabled outside isolated tests. Do not expose SQL, credentials, stack traces, or internal connection details in responses.
- Use file sessions/cache and synchronous queues unless an approved requirement changes them. Do not add Redis.

## Coding and naming conventions

- Follow Laravel/PHP conventions and PSR-12; use Laravel Pint for formatting.
- Use descriptive English identifiers and translation files for Arabic interface text.
- Preserve existing lower-case SQL `snake_case` naming and database constraints.
- Do not add comments unless they clarify non-obvious behavior.
- Keep category semantics fixed to Honey, Ghee, and Nuts. Their base-unit mapping is implemented centrally in `App\Support\CatalogRules`.
- Product edits must not change stock. Do not add hard-delete operations for products with history.

## Development and verification

Run commands from the project root in PowerShell. Use the Compose PHP runtime:

```powershell
docker compose ps
docker compose exec -T app php artisan about
docker compose exec -T app php artisan route:list
docker compose exec -T app php artisan test
npm ci
npm run build
Get-Content database\tests\003_business_rules_simplification.sql -Raw |
  docker compose exec -T postgres psql -U honey_shop -d honey_shop -v ON_ERROR_STOP=1
```

Laravel tests are guarded to use the separate `honey_shop_test` database. They wrap each test in a transaction and roll it back. Never run destructive or schema-building Laravel tests against `honey_shop`.

Database SQL migrations must be applied manually, in order, only after inspecting `schema_migrations`. See [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) for the safe procedure.

## Git and handoff

- Check Git status and remotes before editing, committing, or pushing. Never overwrite remote history or discard another contributor's changes.
- Keep all agent instructions here; tool-specific instruction files should be short pointers to this document.
- Update directly related documentation when behavior changes.
- Report changed files, exact verification commands/results, database effects, and unresolved decisions. Never claim a test or push succeeded unless the command actually succeeded.
