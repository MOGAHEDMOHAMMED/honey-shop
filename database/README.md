# PostgreSQL database

The application uses PostgreSQL 17 in Docker Compose. The persistent development database is `honey_shop`; its Compose volume is `postgres_data`.

## Connection

| Setting | Value |
|---|---|
| Compose hostname | `postgres` |
| Host connection | `127.0.0.1:5433` in the current local `.env` |
| Database | `honey_shop` |
| User | `honey_shop` |
| Password | Local ignored `.env` |

Do not disclose or commit `.env` values.

## Schema and migration history

The ordered SQL files in `database/migrations` are the sole schema-management system. The live development database records `001_initial_schema` through `009_approval_and_return_guards` in `schema_migrations`.

Before any migration:

1. Check Compose/PostgreSQL health and query `schema_migrations`.
2. Inspect affected data, constraints, indexes, and triggers.
3. Apply only an unapplied, forward-only SQL file in sequence.
4. Stop if preserving existing records requires an unresolved transformation.

Never edit an applied SQL migration. Never run Laravel `artisan migrate`, `migrate:fresh`, `db:wipe`, or remove the Compose volume.

## Business-rule test

The rollback-only PostgreSQL integration test can run against the development database because it wraps fixtures in a transaction:

```powershell
Get-Content database\tests\003_business_rules_simplification.sql -Raw |
  docker compose exec -T postgres psql -U honey_shop -d honey_shop -v ON_ERROR_STOP=1
```

Run Laravel feature tests only on the separate `honey_shop_test` database as described in [../docs/DEVELOPMENT.md](../docs/DEVELOPMENT.md).

## Current database rules

- Units are enums/check-constrained: base `KG`/`CAN`, input `KG`/`GALLON`/`CAN`; one honey gallon is exactly 7 KG.
- Only YER is active and valid for first-release business transactions. Inactive historical currency reference rows are preserved.
- Inventory movements must match their source detail and are unique per movement type/source; triggers update balances atomically and reject negative stock.
- Approved sales and purchase lines are immutable except for transaction-safe cumulative-return counters.
- Sales/purchase return details reference original lines. Full external-order cancellation requires complete physical return and cannot follow partial returns.
- Expenses use a generated outstanding balance; approved payment vouchers update expense and account ledger atomically.
- Representative custody accounts are distinct from the main cashbox.

## Catalog schema

Three categories—Honey, Ghee, Nuts—are seeded in `product_categories`. `products` stores UUID, category, base unit, SKU/name, active flag, minimum stock, `selling_price`, and `max_discount_rate`. Migration `003` removed `product_prices`; no such table exists in the live schema.

`inventory_balances` is a trigger-maintained read model. Product management must never write stock. See [../docs/DATABASE.md](../docs/DATABASE.md) for Laravel mappings and application responsibilities.

To stop containers without deleting data:

```powershell
docker compose down
```

Never use `docker compose down -v` unless permanent deletion of the database volume is explicitly intended.
