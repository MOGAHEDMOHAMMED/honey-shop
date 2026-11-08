# Roadmap

## Completed

- PostgreSQL 17 Compose environment and SQL migrations `001`–`014`.
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
- Automatic product codes continuing the existing numeric sequence, and automatic internal purchase invoice numbers distinct from optional supplier references.
- Customer listing, search, create/edit/details, and ledger-derived balances.
- Direct sales and external-order drafts, dynamic invoice lines, unit and discount validation, live totals, YER rounding, stock checks, atomic approval, inventory posting, and customer receivable posting.
- External shipment status, delivery-provider/representative selection, customer delivery charges, and shop/customer/shared bearer recording.
- Concurrency-safe Honey (`74`), Ghee (`84`), and Nuts (`94`) sales invoice series allocated only at approval; mixed-category sales require an explicit series.
- Forward-only migrations `010`–`014` applied to the development and isolated test databases.
- Approved sales return, cancellation/refund, expenses, and payment-voucher policies documented for later modules.
- Laravel tests isolated on PostgreSQL `honey_shop_test`.

## Current scope boundary

The application foundation, catalog, price history, suppliers, opening inventory, purchases, customers, and direct/external sales approval are implemented. No dashboard analytics or unfinished workflow links are presented. Opening stock is quantity-only because no costing/valuation method has been approved.

## Recommended next phases

1. Exact-line sales/purchase returns and full external-order cancellation with funded, atomic refunds.
2. Expenses, payment vouchers, delivery expenses, and representative custody workflows.
3. Opening-inventory costing/valuation, only after an explicit business decision.
4. Reports, broader authorization management, deployment hardening, and backup procedures.

Before implementing a later phase, resolve its relevant open items in [DECISIONS.md](DECISIONS.md) and extend tests on an isolated database.
