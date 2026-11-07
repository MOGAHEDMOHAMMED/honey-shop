# Shared agent instructions

## Project purpose and current state

Honey Shop is a web management system for a shop selling honey, ghee, and nuts. This repository contains the Laravel application, project documentation, PostgreSQL 17 Docker Compose environment, existing SQL schema migrations, and PostgreSQL integration tests.

Current implementation:

- Laravel 13.40.0, PHP 8.3.36, Composer 2.8.10.
- PostgreSQL 17.11 with migrations `001`–`012` applied to the development database.
- Blade, Bootstrap 5.3.8 RTL, vanilla JavaScript, and Vite 8.3.6.
- Existing `app_users`, roles, and permissions tables for authentication/authorization.
- Functional login, health checks, product categories, products with price history, supplier management, opening inventory, and purchase invoices.
- Migrations `001`–`012` are applied to both `honey_shop` and the isolated `honey_shop_test` database.
- The host PHP CLI is 8.0.30 and is incompatible with Laravel 13. Use the PHP 8.3 Compose service for Artisan and Composer commands.
- See [docs/ROADMAP.md](docs/ROADMAP.md) for the implemented procurement scope and what remains.

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

- The existing PostgreSQL schema is authoritative. Current applied SQL versions are `001` through `012`.
- SQL files in `database/migrations` are the only schema migration system. Never edit an applied migration or run `php artisan migrate`, `migrate:fresh`, or `db:wipe`.
- Any schema change must be a reviewed, forward-only SQL migration with the next sequence. Inspect affected data first and stop if preservation requires an unresolved transformation.
- Do not create Laravel migration files or duplicate existing tables. Map Eloquent names, UUID keys, timestamps, enum values, generated columns, and relationships to the real schema.
- Use transactions for multi-step writes. Respect constraints and triggers; do not disable them to make a request pass.
- Business rules include YER only; honey/nuts `KG`, ghee `CAN`; honey gallon conversion `7 KG`; nonnegative stock; source-linked, idempotent inventory movement; line-specific returns; and append-only financial/inventory ledgers.
- The current schema has **no `product_prices` table**. Applied migration `003` removed it; selling price and maximum discount ratio live on `products`. Do not recreate it without an approved forward schema change.
- Beekeepers use the existing `suppliers` table and supplier-type reference; never add a separate beekeeper entity.
- YER monetary values use exact decimal arithmetic and two-place `ROUND_HALF_UP`; never use PHP floats for financial calculations. Purchase unit cost includes all acquisition costs; do not add a separate cost-allocation subsystem.
- Product selling-price and maximum-discount changes are append-only history events. They are distinct from the unit-price/discount snapshots required on approved future sales lines.
- Opening inventory and purchase drafts do not affect stock or supplier balances. Approval must atomically update transaction status, source-linked inventory movements, trigger-maintained balances, and supplier ledger entries exactly once. Preserve all approved financial/inventory history.
- Only one opening-inventory document may be approved. Opening inventory records quantities and units; the current schema has no approved opening-cost/valuation method, so do not invent one.
- Purchase invoices keep the supplier's original invoice number separately from an internal `PUR-...` reference. A zero-total draft is allowed, but approval is rejected because supplier ledger entries require a positive posting.
- Sales invoice series are approved for future work: Honey `74`, Ghee `84`, Nuts `94`, suffixes begin at `1`; allocate permanent string invoice numbers only in approval transactions using concurrency-safe counters and unique constraints. Mixed-category sales require an explicit series selection.
- Full external-order cancellation is allowed only after all shipped goods return; reverse the full receivable but refund only actual collections net of prior refunds, via a real funded-account refund voucher, atomically with inventory and ledger changes. These sales/cancellation workflows are not implemented until their milestones.

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

Laravel tests are guarded to use the separate `honey_shop_test` database (migrations `001`–`012`). They wrap each test in a transaction and roll it back. Never run destructive or schema-building Laravel tests against `honey_shop`.

Database SQL migrations must be applied manually, in order, only after inspecting `schema_migrations`. See [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) for the safe procedure.

## Git and handoff

- Check Git status and remotes before editing, committing, or pushing. Never overwrite remote history or discard another contributor's changes.
- Keep all agent instructions here; tool-specific instruction files should be short pointers to this document.
- Update directly related documentation when behavior changes.
- Report changed files, exact verification commands/results, database effects, and unresolved decisions. Never claim a test or push succeeded unless the command actually succeeded.
