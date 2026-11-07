# Development

## Current prerequisites

- Docker Desktop with Docker Compose.
- PostgreSQL 17 image via the Compose service.
- PowerShell on Windows for the commands below.

No application runtime, dependency manager, or application run/test command is established yet. .NET SDK 10 is installed on the current workstation but is only a recommendation, not an approved project dependency.

## Start and inspect the database

From the project root:

```powershell
docker compose up -d
docker compose ps
docker compose exec -T postgres psql -U honey_shop -d honey_shop -c "SELECT current_setting('server_version');"
```

The checked development environment maps the service to `localhost:5433`; Compose reads this from the ignored local `.env`. The template defaults to `5432`. Do not print or commit `.env` credentials.

## Migration history and applying changes

The live database was inspected and reports these applied versions:

```text
001_initial_schema
002_ledger_and_currency_rules
003_business_rules_simplification
004_inventory_balance_posting_fix
005_transaction_safe_return_limits
006_return_counter_trigger_fix
007_explicit_unit_checks
008_inventory_movement_idempotency
009_approval_and_return_guards
```

Always query `schema_migrations` before applying a migration. Apply only versions not recorded there and in sequence. Example for one verified pending migration:

```powershell
Get-Content database\migrations\009_approval_and_return_guards.sql -Raw |
  docker compose exec -T postgres psql -U honey_shop -d honey_shop -v ON_ERROR_STOP=1
```

Do not rerun an already applied migration, edit an applied file, delete/recreate the database, or remove the Compose volume. New schema changes require a new sequential migration and a review of existing rows first.

## Database tests

```powershell
Get-Content database\tests\003_business_rules_simplification.sql -Raw |
  docker compose exec -T postgres psql -U honey_shop -d honey_shop -v ON_ERROR_STOP=1
```

This script verifies business-rule behavior in the live PostgreSQL server and rolls back fixtures. A zero exit code and `ROLLBACK` indicate successful completion. There is no application test suite yet.

## Secrets and local state

`.env` is ignored by Git and contains local database credentials/port. Use environment-based secret configuration for any future app; never put credentials in checked-in config, code, tests, logs, or documentation.
