# Honey Shop database

The development database runs PostgreSQL 17 in Docker Compose. Its persistent data is stored in the `postgres_data` volume. SQL migrations in `database\migrations` are the source of truth for the schema.

## Connection

| Setting | Value |
| --- | --- |
| Host | `localhost` |
| Port | `5433` in the current local `.env` (`POSTGRES_PORT`) |
| Database | `honey_shop` |
| User | `honey_shop` |
| Password | Local `.env` |

The password file is excluded from Git. Do not commit credentials.

## Migrations

The development database records migrations `001` through `009` in `schema_migrations`.

For a new database, apply migrations `001` through `009` once in filename order. For an existing database, first inspect `schema_migrations` and apply only pending versions. Never edit or rerun an applied migration.

```powershell
Get-Content database\migrations\003_business_rules_simplification.sql -Raw |
  docker compose exec -T postgres psql -U honey_shop -d honey_shop -v ON_ERROR_STOP=1
Get-Content database\migrations\004_inventory_balance_posting_fix.sql -Raw |
  docker compose exec -T postgres psql -U honey_shop -d honey_shop -v ON_ERROR_STOP=1
Get-Content database\migrations\005_transaction_safe_return_limits.sql -Raw |
  docker compose exec -T postgres psql -U honey_shop -d honey_shop -v ON_ERROR_STOP=1
Get-Content database\migrations\006_return_counter_trigger_fix.sql -Raw |
  docker compose exec -T postgres psql -U honey_shop -d honey_shop -v ON_ERROR_STOP=1
Get-Content database\migrations\007_explicit_unit_checks.sql -Raw |
  docker compose exec -T postgres psql -U honey_shop -d honey_shop -v ON_ERROR_STOP=1
Get-Content database\migrations\008_inventory_movement_idempotency.sql -Raw |
  docker compose exec -T postgres psql -U honey_shop -d honey_shop -v ON_ERROR_STOP=1
Get-Content database\migrations\009_approval_and_return_guards.sql -Raw |
  docker compose exec -T postgres psql -U honey_shop -d honey_shop -v ON_ERROR_STOP=1
```

Each migration is transactional. Stop on errors, inspect the reported conflict, and do not edit an applied migration; create a new forward migration instead.

Run the rollback-only PostgreSQL integration tests with:

```powershell
Get-Content database\tests\003_business_rules_simplification.sql -Raw |
  docker compose exec -T postgres psql -U honey_shop -d honey_shop -v ON_ERROR_STOP=1
```

## Current database rules

- Inventory units are PostgreSQL enums: base units `KG` and `CAN`; entered units `KG`, `GALLON`, and `CAN`. Honey gallons use a fixed factor of 7. Purchase and sales lines snapshot entered quantity, unit, factor, base quantity, and price. Product/category unit compatibility is guarded by database triggers and must also be validated in application services.
- Purchase and sales lines are inserted while their business transaction is a draft. Approved transactions cannot be reverted to draft or deleted.
- Each inventory movement has a trigger-validated `source_id`; the `(movement_type, source_id)` unique index prevents reposting the same source operation. Inventory balance changes are atomic and negative stock is rejected.
- Only `YER` is active. Existing `SAR` and `USD` reference rows are preserved but inactive. Business tables reject non-YER amounts.
- Approved sales and purchase lines are immutable except for their return-quantity counters. Return insertion updates those counters with conditional row updates, preventing concurrent returns from exceeding the original line quantity. Returns post inventory and customer/supplier ledger entries atomically.
- A full external-order cancellation requires confirmation that the complete shipment returned, rejects orders with partial returns, and posts the full stock and customer-balance reversal once. Updating order status alone does not restore stock.
- Approved damaged-goods operations post an outgoing inventory movement. Negative stock is rejected.
- `expenses.paid_amount` is advanced by linked approved payment vouchers. The generated `remaining_amount` is `amount - paid_amount`; voucher posting and the financial-account ledger update are atomic.
- The delivery expense link is unique per sale. Shop-paid delivery expenses are allowed; customer-paid delivery expenses are rejected. Shared delivery requires the application to record the customer charge on the invoice and the shop portion as an expense.
- One default YER Main Cashbox is seeded. A partial unique index allows only one default cash account and one YER custody account per representative. Representative custody is not shop cash.
- Approved inventory, account, customer, and supplier ledgers are append-only. Use reversal or return transactions rather than edits/deletions.

## Application work still required

This repository currently contains database files only; no application framework or service layer exists. Implement transaction services for sales, purchases, return/cancellation flows, representative custody account lookup/creation, and delivery-expense creation when the application stack is established. Those services must coordinate document, ledger, and inventory writes in a single transaction and enforce server-side authorization. No application screens are included here.

The first administrator must be provisioned securely by the application; there is no seeded account or default password. Do not use the Docker bootstrap database user in production.

To stop the development container without deleting its data:

```powershell
docker compose down
```

The data volume is retained. Do not use `docker compose down -v` unless you explicitly intend to delete the development database.
