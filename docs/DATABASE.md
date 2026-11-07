# Database integration

## Verified state

The development database is `honey_shop` on PostgreSQL 17.11 in Docker Compose. `schema_migrations` records versions `001` through `012`; the live schema has 53 public tables. It contains the three seeded categories and existing roles, 12 permission codes with 27 role-permission links, and no application users, products, suppliers, purchases, opening-inventory documents, or other affected business records. Permission seeding did not alter business records.

Both `honey_shop` and the isolated `honey_shop_test` database now record SQL migrations `001` through `012`. Migrations `010`–`012` were applied forward-only after checking existing records; neither database was reset or recreated. The development database had no business records in the affected tables when those migrations were applied. The idempotent permission seeder now includes the catalog, price-history, supplier, opening-inventory, and purchase permission codes. Laravel feature tests use only `honey_shop_test`.

## Laravel mappings

| Model | Existing table | Mapping notes |
|---|---|---|
| `User` | `app_users` | UUID `id`; `password_hash`; timestamps; active flag |
| `Role` | `roles` | UUID `id`; no timestamps |
| `Permission` | `permissions` | string primary key `code`; no timestamps |
| `ProductCategory` | `product_categories` | UUID `id`; no timestamps; `is_active` |
| `Product` | `products` | UUID `id`; timestamps; PostgreSQL `base_inventory_unit`; YER price and discount ratio |
| `ProductPriceHistory` | `product_price_history` | UUID `id`; immutable prior/new price and discount snapshots; nullable actor only for the baseline snapshot |
| `Supplier` / `SupplierType` | `suppliers` / `supplier_types` | Existing supplier model and type reference; beekeepers use the existing supplier type |
| `BusinessTransaction` | `business_transactions` | UUID `id`; shared draft/approved lifecycle and actor timestamps |
| `OpeningInventory` / `OpeningInventoryLine` | `opening_inventory` / `opening_inventory_lines` | Input quantity/unit/conversion snapshot plus base quantity; only one document may be approved |
| `PurchaseInvoice` / `PurchaseLine` | `purchase_invoices` / `purchase_lines` | Supplier invoice number is separate from internal purchase reference; generated monetary/base-quantity fields remain database-owned |
| `SupplierAccountEntry` | `supplier_account_entries` | Append-only YER supplier ledger entry; purchase source key prevents duplicate postings |
| `InventoryBalance` | `inventory_balances` | `product_id` is the UUID primary key; no Eloquent writes |

Relationships use the existing `user_roles`, `role_permissions`, `products.category_id`, and `inventory_balances.product_id` foreign keys.

## Catalog data and business rules

- The three canonical categories are Honey, Ghee, and Nuts. Arbitrary category creation and renaming are not exposed because category semantics determine inventory units.
- `CatalogRules` maps Honey → `KG`, Ghee → `CAN`, Nuts → `KG`. Product forms do not accept a unit field.
- Product selling price is stored per base unit in `products.selling_price`; `max_discount_rate` is a ratio in the database and is entered/displayed as a percentage in the app.
- The applied migration `003_business_rules_simplification.sql` drops `product_prices`; there is no `product_prices` relation in the live schema. No schema change was made to recreate it.
- The app displays YER and the existing `inventory_balances.quantity`; product create/edit never writes stock.
- A category with active products cannot be deactivated. A product category cannot be changed after inventory or transaction history exists. Products have no hard-delete route.
- Product creation and price/discount changes append immutable, actor-attributed history in the same transaction. Non-price edits do not create history entries; the application exposes history only to users with `catalog.products.price-history.view`.

## Suppliers, opening inventory, and purchases

- Supplier names are unique after trimming and case normalization within each supplier type. Historical suppliers are not hard-deleted; beekeepers use the existing `supplier_types` mechanism.
- Opening-inventory and purchase documents begin as drafts. Drafts do not affect stock or supplier balances.
- One opening-inventory document may be approved. Its entered quantity, selected input unit, conversion factor, and resulting base quantity are preserved; honey `GALLON` converts to `7 KG`.
- Purchase lines preserve entered quantity/unit, conversion factor, base quantity, unit acquisition cost, generated effective base-unit cost, and generated rounded line total. The supplier's original invoice number is distinct from the internal purchase reference.
- Purchase approval is transactional: it approves the business transaction, creates source-linked stock movements, and posts one positive YER payable. Database triggers update inventory balances and enforce movement provenance; source keys and transaction status prevent duplicate approval effects.
- Purchase line totals use PostgreSQL `ROUND(entered_quantity * purchase_unit_price, 2)` and invoice totals sum already-rounded lines. Stored YER values use two decimal places. PHP does not use floats for money.
- Opening inventory stores quantities only. No acquisition-cost or stock-valuation policy has been approved or added.

## Integrity and unchanged business tables

The application does not reimplement or disable existing database checks/triggers. Important database guarantees include YER-only financial records, base/input-unit constraints, exact-line cumulative return counters, nonnegative inventory, source-linked idempotent movements, final transaction protections, append-only ledgers, and voucher-linked expense payments. These workflows are not exposed by the current Laravel UI.

Migrations `010`–`012` are forward-only SQL files. They add immutable product-price history, procurement support and guards, opening-line unit snapshots, and two-place YER precision across existing financial fields. Migration `012` also makes the future sales-line generated net amount round gross before applying the stored discount. Applied migrations `001`–`009` remain unchanged. No Laravel migration files or competing `migrations` table were added.

Use [../database/README.md](../database/README.md) and [DEVELOPMENT.md](DEVELOPMENT.md) for SQL migration/test commands.
