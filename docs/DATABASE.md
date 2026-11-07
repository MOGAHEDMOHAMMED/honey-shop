# Database

## Verified environment

The development database is PostgreSQL **17.11**, database `honey_shop`, in the running Docker Compose service. Migrations `001_initial_schema` through `009_approval_and_return_guards` are recorded in `schema_migrations`. The database contains 43 public tables. At inspection, business transaction, inventory movement, and voucher tables had no rows; seeded reference rows included three currencies, categories, roles, supplier types, and the default cash account.

Applied migration files are listed in [DEVELOPMENT.md](DEVELOPMENT.md). Read [database/README.md](../database/README.md) for connection details and commands.

## Main entities

- **Identity:** `app_users`, `roles`, `permissions`, `user_roles`, `role_permissions`.
- **Catalog and parties:** `product_categories`, `products`, `currencies`, `supplier_types`, `suppliers`, `customers`, `representatives`, `delivery_providers`.
- **Transaction base:** `business_transactions` stores transaction type, status, timestamps, and user references. Purchase, sale, return, cancellation, adjustment, opening-balance, expense, withdrawal, and voucher records extend it.
- **Purchasing:** `purchase_invoices`, `purchase_lines`, `purchase_returns`, `purchase_return_lines`.
- **Sales:** `sales_invoices`, `sales_lines`, `sales_returns`, `sales_return_lines`, `order_cancellations`.
- **Inventory:** `opening_inventory`, `opening_inventory_lines`, `stock_adjustments`, `stock_adjustment_lines`, `damaged_goods`, `inventory_movements`, `inventory_balances`.
- **Finance:** `financial_accounts`, `vouchers`, `account_transfers`, `account_transactions`, `customer_account_entries`, `supplier_account_entries`, `customer_invoice_allocations`, `supplier_invoice_allocations`.
- **Other:** `expenses_categories`, `expenses`, `owner_withdrawals`, `audit_log`, `schema_migrations`.

The full table definitions and relationships are in [the initial schema migration](../database/migrations/001_initial_schema.sql), subsequent migrations, and [the core ERD](../database/erd.md).

## Important constraints and behavior

- PostgreSQL enums restrict base units to `KG`, `CAN` and entered units to `KG`, `GALLON`, `CAN`; `CHECK` constraints repeat the allowed values on relevant columns. Honey gallons require conversion factor 7; other entered units require factor 1. Category/unit compatibility is checked by a trigger on transaction lines.
- Purchase and sales lines store entered quantity/unit, conversion factor, generated base quantity, and historical line price. Product price and maximum discount percentage are on `products`.
- YER is the only active currency; legacy SAR/USD reference rows are preserved inactive. Financial/business rows constrain currency to YER.
- Inventory movements must point to a matching source detail row. A unique `(movement_type, source_id)` index prevents posting a source movement twice. An atomic trigger updates `inventory_balances`; the balance constraint rejects negative stock.
- Purchase and sales detail lines can only be added to draft transactions. Approved transaction status cannot be reverted or deleted. Application services must assemble the draft and lines, approve it, then create the corresponding ledger movements in one transaction.
- Sales and purchase return lines reference the exact original detail line and invoice. Conditional updates to returned-quantity counters enforce cumulative limits under row locks. Sales returns restore saleable stock. A full order cancellation requires an external order and confirmed complete return; partial returns disqualify full cancellation.
- A linked voucher posts one account transaction. The voucher primary key, append-only trigger, and account-ledger unique index prevent repeat posting for the same voucher transaction. Expense payments atomically increase `paid_amount`; `remaining_amount` is generated and overpayment is rejected.
- A partial unique index allows one default cashbox and one representative custody account per representative. Representative custody is a distinct account type, never shop cash.
- Related delivery expenses are limited to one per sale; a customer-paid delivery cannot create a shop delivery expense.

## Important application responsibilities

The schema does not yet provide an application API. Application services must validate authorization, calculate invoice totals/discount limits, apply the rule that a honey gallon is priced at seven times the per-KG price, coordinate all document/ledger postings atomically, create representative accounts with GetOrCreate behavior, and calculate the shared-delivery shop portion. See [DECISIONS.md](DECISIONS.md) for unresolved choices.
