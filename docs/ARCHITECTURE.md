# Architecture

## Existing implementation

This is the architecture present in the workspace today:

1. Docker Compose runs PostgreSQL 17 (`postgres:17-alpine`) with a persistent named volume and a localhost port.
2. `database/migrations` contains ordered, transactional SQL schema changes. `schema_migrations` records versions applied to the live database.
3. `database/tests/003_business_rules_simplification.sql` is a PostgreSQL integration test script. It runs fixtures in a transaction and rolls them back.
4. Markdown requirements and database documentation describe scope and business decisions.

There is no web host, backend, frontend, API, application-service layer, dependency injection setup, application logging, health endpoint, or application test runner. Do not document these as implemented.

## Application recommendation (not yet approved)

No application technology is selected in project files. The current workstation has .NET SDK 10.0.201 and 10.0.302 installed. A reasonable low-complexity recommendation is **ASP.NET Core on .NET 10 LTS with PostgreSQL via Npgsql**, initially as one modular application rather than separate services.

This is a recommendation, not a project decision. ASP.NET Core provides built-in configuration, dependency injection, structured logging, request handling, and health-check support; .NET 10 SDKs are already available on this workstation. The app can place business services and data access in the same deployable application while keeping responsibilities separate. Npgsql would provide PostgreSQL connectivity and transaction management.

The UI approach (for example server-rendered pages or a separate SPA), data-access style, validation package, authentication method, and production hosting remain unselected. Do not add a project or dependencies until the owner approves the application stack and UI approach. The workstation's installed Node.js and Python are not evidence that either is an approved project stack.

## Intended boundaries after approval

- Web/request boundary: authentication, request validation, response mapping, and centralized error handling.
- Business services: enforce business rules and coordinate related database writes in one transaction.
- Data access: parameterized PostgreSQL commands, explicit transaction boundaries, and schema-migration integration.
- PostgreSQL: constraints, foreign keys, append-only ledgers, unit/amount invariants, and atomic inventory/payment posting.

Keep the first implementation in one application. Split deployments or add abstractions only when a measured requirement justifies them.
