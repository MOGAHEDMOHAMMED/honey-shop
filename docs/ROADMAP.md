# Roadmap

## Completed

- Read and retain the original requirements baseline.
- Prepare Docker Compose PostgreSQL 17 development environment.
- Apply and track migrations `001`–`009`.
- Add YER-only schema, unit rules, source-linked inventory ledger, return limits, full-order cancellation, damaged-goods operation, expense/voucher payment posting, custody uniqueness, and default cashbox.
- Add rollback-only integration tests and shared development documentation.

## Current stage

**Foundation handoff / application stack decision.** The workspace is still database and documentation only. No application screens or web services have been started. See [open decisions](DECISIONS.md) before scaffolding.

## Suggested next phases after stack approval

1. Create the minimal application host, environment/secrets configuration, PostgreSQL connection, health endpoint, validation/error/logging defaults, and automated test setup.
2. Add secure authentication and initial administrator provisioning.
3. Implement product/catalog and inventory services; test the database movement source/idempotency workflow.
4. Implement supplier/purchase and customer/sales services with atomic approval and posting.
5. Implement returns/cancellations, expenses/vouchers, representative custody, and delivery-expense workflows.
6. Add responsive Arabic RTL UI, reports, audit/access review, backup/restore, and production readiness.

Complete and test each phase before broadening scope. Do not implement the roadmap as part of the current foundation task.
