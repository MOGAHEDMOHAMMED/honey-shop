# Honey Shop Management System

Laravel web application for managing a honey, ghee, and nuts shop. The application uses server-rendered Blade pages, an Arabic RTL interface, and the existing PostgreSQL 17 schema.

## Current milestone

- Laravel 13.40.0 on PHP 8.3.36.
- Cookie-session login against the existing `app_users` table and role/permission tables.
- Arabic RTL application layout.
- Product category browsing/search and activation management for Honey, Ghee, and Nuts.
- Product search, category filtering, create/edit/details, YER selling prices, maximum discount percentages, append-only price history, and read-only stock display.
- Supplier search, create/edit/details, supplier types (including beekeepers), and activation management.
- Draft and approval workflows for opening inventory and purchase invoices, including unit conversion, inventory posting, and supplier payable ledger entries.
- Exact two-decimal YER purchase calculations using PostgreSQL generated values and `ROUND_HALF_UP`.
- Sales, returns, expenses, payment vouchers, and other financial modules are not implemented.

## Start here

- [Shared agent instructions](AGENTS.md)
- [Project overview](docs/PROJECT_OVERVIEW.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Database integration](docs/DATABASE.md)
- [Development setup and testing](docs/DEVELOPMENT.md)
- [Approved decisions and open items](docs/DECISIONS.md)
- [Roadmap](docs/ROADMAP.md)

## Local development

This project uses the Compose PHP 8.3 runtime because the workstation PHP CLI is older than Laravel 13 requires. Keep the existing local `.env`; do not overwrite its PostgreSQL password.

```powershell
docker compose up -d postgres
docker compose build app
docker compose run --rm --no-deps app composer install
npm ci
docker compose run --rm --no-deps app php artisan key:generate
docker compose run --rm app php artisan db:seed --class="Database\Seeders\CatalogPermissionsSeeder" --force
docker compose up -d app
```

Open `http://localhost:8000`. Health endpoints are `/health/live` and `/health/ready`.

The administrator is created separately and interactively:

```powershell
docker compose exec app php artisan app:admin-create
```

See [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) before applying database SQL or running tests. Existing schema changes are managed only by the ordered SQL migrations in `database/migrations`; **do not run `php artisan migrate`**.
