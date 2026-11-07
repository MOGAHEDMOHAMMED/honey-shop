# Decisions and open items

## Approved implementation decisions

| Area | Decision |
|---|---|
| Stack | Laravel 13, PHP 8.3+, PostgreSQL 17, Eloquent/Query Builder, Blade, Bootstrap 5 RTL, vanilla JavaScript, Vite |
| Architecture | One server-rendered Laravel modular monolith; no second backend or SPA |
| Database changes | Existing SQL migrations are authoritative; no Laravel schema migrations |
| Authentication | Existing `app_users.password_hash`, session guard, existing role/permission tables, secure hash, session regeneration and invalidation |
| Administrator | Interactive, one-time `app:admin-create`; existing `Administrator` role; no seeded credentials |
| Categories | Honey, Ghee, and Nuts are the supported categories; arbitrary category creation/name changes are not exposed |
| Units | Honey/Nuts `KG`, Ghee `CAN`; mapping is enforced by catalog service; stock is not changed by product editing |
| Currency/prices | YER only. Database stores a per-base-unit `selling_price` and decimal ratio `max_discount_rate` on `products`; UI accepts a percentage |
| Session/cache | Local files; no Redis |
| Testing | PostgreSQL integration tests on separate `honey_shop_test`; Laravel tests use transactions and roll back |

## Verified schema discrepancy

The current task requested use of `products` and `product_prices`, while the live schema and applied migration history show that `product_prices` does not exist: migration `003_business_rules_simplification.sql` migrated the YER price to `products.selling_price` and then dropped `product_prices`. This implementation uses the actual schema and does not add duplicate price storage or a new migration. Confirm before any future request to reintroduce a price table.

## Database baseline and preservation

PostgreSQL 17.11 reports migrations `001`–`009` applied. All nine SQL migration files were inspected and left unchanged. No migration was created for this milestone. Existing categories/roles were preserved. The only intentional development-database data addition was the idempotent catalog permission seeder (four permission codes and their role mappings); no admin or business transactions were created.

## Still open for future modules

1. Invoice numbering and invoice rounding/total policy.
2. Refund behavior when a fully cancelled external order was already paid.
3. Whether a future product-pricing history requirement warrants a schema change rather than the current single YER master price.
4. Production hosting, secret management, and TLS deployment settings.
