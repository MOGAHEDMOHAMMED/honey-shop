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
- Product listing/search/category filtering, create/edit/details, YER price and discount display, and read-only inventory balance.

## Not implemented

Purchases, sales, opening inventory UI, stock workflows, returns, external orders, deliveries, expenses, vouchers, representative operations, reporting, and dashboard analytics remain future work. No screens in these areas are represented as functional.

See [ARCHITECTURE.md](ARCHITECTURE.md), [DATABASE.md](DATABASE.md), and [ROADMAP.md](ROADMAP.md).
