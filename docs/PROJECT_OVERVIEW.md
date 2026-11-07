# Project overview

## Purpose

Honey Shop is a planned web-based management system for a retail shop selling honey, ghee, and nuts. It is intended to organize products, stock, suppliers/beekeepers, customers, purchases, direct sales, external orders, returns, representatives, financial accounts, vouchers, expenses, owner withdrawals, reporting, users, and backups.

The detailed baseline is [Software Requirements Analysis & Development Plan.md](../Software%20Requirements%20Analysis%20%26%20Development%20Plan.md). Current first-release decisions in [DECISIONS.md](DECISIONS.md) supersede earlier conflicting requirements there.

## First-release scope

- YER only.
- Product and inventory units: honey KG, nuts KG, ghee CAN; honey GALLON input converts at exactly 7 KG.
- Inventory ledger and current balances; approved movements cannot create negative stock.
- Purchases, sales, exact-line returns, full external-order cancellations after full physical return, and separate damaged-goods operations.
- Customer/supplier account history, shop cash and bank accounts, representative custody accounts, vouchers, expenses, and delivery-cost portions.
- Mobile-first, responsive, Arabic RTL experience is a product requirement; UI work has not started.

## Current implementation state

The workspace currently contains the requirements plan, Docker Compose PostgreSQL setup, nine applied SQL migrations, database tests, and database documentation/ERD. There is **no application/backend/frontend implementation** yet. No application screens or commercial workflows have been implemented.

For actual architecture and next steps, see [ARCHITECTURE.md](ARCHITECTURE.md) and [ROADMAP.md](ROADMAP.md).
