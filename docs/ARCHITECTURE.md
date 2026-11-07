# Architecture

## Runtime and dependencies

- Laravel 13.40.0 on PHP 8.3.36, run by the Compose `app` service.
- PostgreSQL 17.11, run by the existing Compose `postgres` service.
- Eloquent and Laravel Query Builder integrate with the existing SQL-managed schema.
- Blade pages use locally installed Bootstrap 5.3.8 RTL, vanilla JavaScript, and Vite 8.3.6.
- Composer 2.8.10 manages PHP dependencies. The root `package-lock.json` locks frontend dependencies.

The host PHP CLI is 8.0.30 and does not satisfy Laravel 13. Use the Compose PHP runtime for Artisan and Composer commands. Node.js/npm run on the host for Vite.

## Application organization

- `app/Models`: explicit mappings for users/roles/permissions, categories/products/price history, suppliers, purchase invoices/lines, opening inventory, business transactions, and supplier ledger entries.
- `app/Http/Controllers`: auth, health, catalog, supplier, purchase, and opening-inventory request/response coordination.
- `app/Http/Requests`: server-side validation for authentication and catalog, supplier, purchase, and opening-inventory operations.
- `app/Services`: transactional catalog/history, supplier, purchase draft/approval, and opening-inventory workflows.
- `app/Support`: category/base-unit rules, transaction input-unit conversion, and exact decimal YER money operations.
- `app/Console/Commands`: interactive first-administrator provisioning.
- `resources/views`: Arabic RTL login, shared layouts, category and product pages, and safe error pages.
- `lang/ar`: Arabic interface and validation strings.
- `database/seeders`: idempotent permission and role-permission seeding. The directory `database/migrations` remains the existing ordered SQL migration system.
- `tests`: Laravel feature/unit tests isolated from the working database.

Controllers remain thin. Catalog writes use service transactions; controllers do not change inventory. Product stock is read-only.

## Authentication and authorization

Laravel's session guard authenticates the `App\Models\User` model mapped to `app_users`. `password_hash` is the stored password column. Successful login regenerates the session; logout invalidates it and regenerates the CSRF token.

Roles and permissions map to `roles`, `permissions`, `user_roles`, and `role_permissions`. The idempotent permission seeder provides catalog, product price-history, supplier, opening-inventory, and purchase permissions. Administrator and Inventory and Purchasing User receive the management/approval permissions; Sales User receives only catalog and price-history read access. Administrator receives the app-wide Gate bypass. No parallel authorization tables are created.

`app:admin-create` locks the existing Administrator role, refuses to create a second administrator, hashes the interactive password, and assigns the existing role in one transaction.

## Database boundary

The existing schema is the source of truth. Laravel migrations are not used; never run `php artisan migrate`. PostgreSQL SQL migrations are applied manually in sequence after checking `schema_migrations`. Existing database triggers and constraints remain enabled.

The live schema has no `product_prices` table: migration `003` removed it and stores `selling_price` and `max_discount_rate` directly on `products`. Migration `010` added immutable `product_price_history` and procurement/opening-inventory support; migrations `011` and `012` apply two-decimal YER precision to purchase and existing financial columns. The application maps those actual tables without recreating `product_prices`.

Purchase and opening-inventory writes use service transactions. Approval locks the document and relevant rows, transitions the business transaction, creates source-linked inventory movements, and (for purchases) adds one supplier payable. Existing PostgreSQL triggers update balances and enforce nonnegative stock, movement provenance, append-only ledgers, final-document immutability, and one-time opening inventory. Drafts never post stock or supplier balances.

Purchase monetary calculations are generated in PostgreSQL using `NUMERIC` and `ROUND(..., 2)`. PHP `Money` uses BCMath for exact half-up operations and formatting; it does not use floating-point arithmetic for financial values.

## Sessions, cache, errors, health

Sessions and cache use local file stores; queues are synchronous. There is no Redis or extra infrastructure. Secure cookie flags are enabled in production, cookies are HTTP-only and same-site lax, and session content is encrypted. Laravel handles centralized exception reporting; production debug output is off and user-facing errors are Arabic.

- `GET /health/live`: application process response.
- `GET /health/ready`: PostgreSQL `SELECT 1`; failures return a generic unavailable status and are logged without connection details.

## Out of scope

No separate API/frontend, microservices, repository layer, user-management UI, financial modules, or unimplemented business screens are present.
