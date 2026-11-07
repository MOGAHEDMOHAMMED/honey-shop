# Database integration

## Verified state

The development database is `honey_shop` on PostgreSQL 17.11 in Docker Compose. `schema_migrations` records versions `001` through `009`. The live schema has 43 public tables. At initial app inspection it contained three active categories, the seeded roles, no application users, no products, no inventory balances, and no catalog permissions. The app permission seeder added four permission rows and ten role-permission associations; it did not alter existing business records.

The isolated `honey_shop_test` database was created separately and populated by applying the existing SQL files `001`–`009` in order. It is the only database used by Laravel tests.

## Laravel mappings

| Model | Existing table | Mapping notes |
|---|---|---|
| `User` | `app_users` | UUID `id`; `password_hash`; timestamps; active flag |
| `Role` | `roles` | UUID `id`; no timestamps |
| `Permission` | `permissions` | string primary key `code`; no timestamps |
| `ProductCategory` | `product_categories` | UUID `id`; no timestamps; `is_active` |
| `Product` | `products` | UUID `id`; timestamps; PostgreSQL `base_inventory_unit`; YER price and discount ratio |
| `InventoryBalance` | `inventory_balances` | `product_id` is the UUID primary key; no Eloquent writes |

Relationships use the existing `user_roles`, `role_permissions`, `products.category_id`, and `inventory_balances.product_id` foreign keys.

## Catalog data and business rules

- The three canonical categories are Honey, Ghee, and Nuts. Arbitrary category creation and renaming are not exposed because category semantics determine inventory units.
- `CatalogRules` maps Honey → `KG`, Ghee → `CAN`, Nuts → `KG`. Product forms do not accept a unit field.
- Product selling price is stored per base unit in `products.selling_price`; `max_discount_rate` is a ratio in the database and is entered/displayed as a percentage in the app.
- The applied migration `003_business_rules_simplification.sql` drops `product_prices`; there is no `product_prices` relation in the live schema. No schema change was made to recreate it.
- The app displays YER and the existing `inventory_balances.quantity`; product create/edit never writes stock.
- A category with active products cannot be deactivated. A product category cannot be changed after inventory or transaction history exists. Products have no hard-delete route.

## Integrity and unchanged business tables

The application does not reimplement or disable existing database checks/triggers. Important database guarantees include YER-only financial records, base/input-unit constraints, exact-line cumulative return counters, nonnegative inventory, source-linked idempotent movements, final transaction protections, append-only ledgers, and voucher-linked expense payments. These workflows are not exposed by the current Laravel UI.

No new schema migration was created for the Laravel milestone. Migrations `001`–`009` remain unchanged. No Laravel migration files or competing `migrations` table were added.

Use [../database/README.md](../database/README.md) and [DEVELOPMENT.md](DEVELOPMENT.md) for SQL migration/test commands.
