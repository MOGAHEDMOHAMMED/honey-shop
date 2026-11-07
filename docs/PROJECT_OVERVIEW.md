# Project overview

## Purpose

Honey Shop is a web-based management system for a shop selling honey, ghee, and nuts. It is intended to support catalog, inventory, purchasing, sales, returns, customer/supplier balances, expenses, vouchers, representative custody, and reporting as later milestones are implemented.

## Approved first-release rules

- Yemeni Rial (YER) only.
- Honey and nuts use `KG`; ghee uses `CAN`. Honey gallon input converts to exactly `7 KG`.
- The existing PostgreSQL schema, SQL migration history, constraints, and triggers are authoritative.
- Product category names are the three seeded categories: Honey, Ghee, and Nuts.
- Arabic is the primary language and the application uses right-to-left layout.

## Implemented

- Laravel 13.40.0 modular monolith, PHP 8.3.36, PostgreSQL 17, Blade, Bootstrap 5 RTL, vanilla JavaScript, and Vite.
- Session login against `app_users`, with roles and permissions loaded from the existing database tables.
- Interactive one-time initial-administrator command.
- Application and PostgreSQL readiness endpoints.
- Category listing/search and active-state management.
- Product listing/search/category filtering, create/edit/details, YER price and discount display, append-only price history, and read-only inventory balance.
- Supplier listing/search, create/edit/details, supplier types (including beekeepers), and activation.
- Opening-inventory drafts and one-time approval with input-unit conversion and trigger-maintained stock posting.
- Purchase drafts, editing, details, approval, rounded line/document totals, inventory posting, and supplier payable ledger posting.
- Forward-only PostgreSQL migrations `010`–`012` for price history, supplier/purchase support, opening-inventory unit snapshots, and two-decimal YER precision across financial columns.

## Not implemented

Sales, returns, external orders, deliveries, expenses, vouchers, representative operations, reporting, and dashboard analytics remain future work. Opening inventory and purchase workflows are implemented, but no inventory-adjustment or transfer workflow is exposed.

See [ARCHITECTURE.md](ARCHITECTURE.md), [DATABASE.md](DATABASE.md), and [ROADMAP.md](ROADMAP.md).
