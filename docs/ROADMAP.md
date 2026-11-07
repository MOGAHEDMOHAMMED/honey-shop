# Roadmap

## Completed

- PostgreSQL 17 Compose environment and SQL migrations `001`–`009`.
- Rollback-only PostgreSQL business-rule integration test.
- Shared project and agent documentation.
- Laravel 13/PHP 8.3 runtime, PostgreSQL connection, health endpoints, file-based sessions/cache, and Vite build.
- Login/logout using existing `app_users`; existing role/permission integration and secure first-admin command.
- Arabic RTL Blade layout with local Bootstrap RTL assets.
- Category listing/search and category activation management.
- Product listing/search/filter, create/edit/details, YER prices and discount percentage, and read-only stock display.
- Laravel tests isolated on PostgreSQL `honey_shop_test`.

## Current scope boundary

The implemented milestone is the application foundation and catalog only. No dashboard statistics or links to unfinished business screens are present.

## Recommended next phases

1. Opening inventory and purchase workflows: document lifecycle, line-unit conversion, transactional approval, source-idempotent movements, and supplier ledger posting.
2. Sales workflows with price/discount validation, YER totals, inventory posting, and delivery charge handling.
3. Exact-line sales/purchase returns and full external-order cancellation.
4. Expenses, vouchers, delivery expenses, and representative custody workflows.
5. Reports, broader authorization management, deployment hardening, and backup procedures.

Before implementing a later phase, resolve its relevant open items in [DECISIONS.md](DECISIONS.md) and extend tests on an isolated database.
