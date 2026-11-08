# Development

## Prerequisites and versions

- Docker Desktop and Docker Compose.
- PHP 8.3.36 and Composer 2.8.10 are provided by the project `app` image.
- Node.js 24.12.0 and npm 11.6.2 are used on the host for Vite.
- PostgreSQL 17.11 runs in the Compose `postgres` service.

The host PHP CLI is 8.0.30 and is not compatible with Laravel 13. Run PHP/Composer commands through Docker Compose.

## Environment setup

Preserve an existing `.env`. For a new clone only, copy `.env.example` to `.env`, then set a unique local `POSTGRES_PASSWORD`. The file is ignored by Git. Compose maps PostgreSQL to `127.0.0.1:5433` by default; the `app` container connects to host `postgres:5432`.

Build dependencies and assets:

```powershell
docker compose up -d postgres
docker compose build app
docker compose run --rm --no-deps app composer install
npm ci
```

Set `APP_KEY` once for a new environment, only if it is empty; do not rotate an existing key unnecessarily:

```powershell
docker compose run --rm --no-deps app php artisan key:generate
```

The application uses local file sessions/cache, encrypted session contents, synchronous queues, and no Redis.

## Database and application startup

The database schema is managed only by the ordered SQL migrations in `database/migrations`. Never run `php artisan migrate`, `migrate:fresh`, or `db:wipe`.

The development database has migrations `001`–`014` applied. Start the app and seed the idempotent catalog, procurement, customer, and sales permissions:

```powershell
docker compose run --rm app php artisan db:seed --class="Database\Seeders\CatalogPermissionsSeeder" --force
docker compose up -d app
docker compose ps
```

If applying a new schema migration, first query `schema_migrations`, inspect affected data and constraints, and apply only a new pending SQL file in sequence. Do not edit or rerun an applied migration. See [../database/README.md](../database/README.md).

Open `http://localhost:8000`. Health checks:

- `http://localhost:8000/health/live`
- `http://localhost:8000/health/ready`

Create the initial admin interactively after seeding permissions:

```powershell
docker compose exec app php artisan app:admin-create
```

The command requires a unique username, display name, a confirmed password of 12–72 characters, hashes it, assigns the existing `Administrator` role, and refuses duplicate initialization. It does not create default credentials.

## Tests

Laravel tests must use the separate `honey_shop_test` database. It already exists locally and is initialized with SQL migrations `001`–`014`. Test setup forcibly selects that database, opens a transaction, and rolls it back after each test. Do not use Laravel `RefreshDatabase` or run destructive tests against `honey_shop`.

For a new local database environment, inspect first and create the test database only if it does not exist:

```powershell
$exists = docker compose exec -T postgres psql -U honey_shop -d honey_shop -tAc "SELECT 1 FROM pg_database WHERE datname = 'honey_shop_test'"
if ($LASTEXITCODE -ne 0) { throw "Could not inspect PostgreSQL databases" }
if (-not $exists) {
    docker compose exec -T postgres psql -U honey_shop -d honey_shop -v ON_ERROR_STOP=1 -c "CREATE DATABASE honey_shop_test OWNER honey_shop"
    if ($LASTEXITCODE -ne 0) { throw "Could not create honey_shop_test" }
}
```

If it already exists, inspect its migration history and records; do not drop or recreate it. For a newly created empty test database, apply the existing SQL migration files once and in filename order:

```powershell
Get-ChildItem database\migrations\*.sql | Sort-Object Name | ForEach-Object {
    Get-Content $_.FullName -Raw |
      docker compose exec -T postgres psql -U honey_shop -d honey_shop_test -v ON_ERROR_STOP=1
    if ($LASTEXITCODE -ne 0) { throw "Migration failed: $($_.Name)" }
}
```

```powershell
docker compose exec -T app php artisan test
npm run build
Get-Content database\tests\003_business_rules_simplification.sql -Raw |
  docker compose exec -T postgres psql -U honey_shop -d honey_shop_test -v ON_ERROR_STOP=1
```

The SQL integration test uses `BEGIN`/`ROLLBACK`; run it against `honey_shop_test`, not the operational database. Check exit status and output; do not infer success from command startup.

## Useful commands

```powershell
docker compose exec -T app php artisan about
docker compose exec -T app php artisan route:list
docker compose run --rm --no-deps app composer show laravel/framework
npm ci
npm run dev
npm run build
docker compose ps
```

Do not use `docker compose down -v`; it deletes the PostgreSQL data volume.
