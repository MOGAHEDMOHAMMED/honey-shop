# Roadmap

## Completed

- PostgreSQL 17 Compose environment and SQL migrations `001`–`012`.
- Rollback-only PostgreSQL business-rule integration test.
- Shared project and agent documentation.
- Laravel 13/PHP 8.3 runtime, PostgreSQL connection, health endpoints, file-based sessions/cache, and Vite build.
- Login/logout using existing `app_users`; existing role/permission integration and secure first-admin command.
- Arabic RTL Blade layout with local Bootstrap RTL assets.
- Category listing/search and category activation management.
- Product listing/search/filter, create/edit/details, YER prices and discount percentage, and read-only stock display.
- Product selling-price and maximum-discount history with append-only database protection and permission-gated details.
- Supplier management using the existing supplier-type model; beekeepers remain supplier records.
- Opening-inventory drafts and one-time approval with approved input units, conversion snapshots, and inventory movement posting.
- Purchase invoice listing, draft creation/editing, supplier invoice references, line conversion and YER calculations, atomic approval, inventory posting, and supplier payable posting.
- Forward-only migrations `010`–`012` applied to the development and isolated test databases.
- Approved sales-invoice numbering and external-order refund policies documented for future modules.
- Laravel tests isolated on PostgreSQL `honey_shop_test`.

## Current scope boundary

The application foundation, catalog, price history, suppliers, opening inventory, and purchases are implemented. No dashboard analytics or unfinished workflow links are presented. Opening stock is quantity-only because no costing/valuation method has been approved.

## Recommended next phases

1. Sales workflows with approved invoice numbering, price/discount snapshots, YER totals, inventory posting, and delivery-charge handling.
2. Exact-line sales/purchase returns and full external-order cancellation with funded, atomic refunds.
3. Expenses, payment vouchers, delivery expenses, and representative custody workflows.
4. Opening-inventory costing/valuation, only after an explicit business decision.
5. Reports, broader authorization management, deployment hardening, and backup procedures.

Before implementing a later phase, resolve its relevant open items in [DECISIONS.md](DECISIONS.md) and extend tests on an isolated database.
