# Models — `app/Models/`

Loaded when editing Eloquent models. See `app/CLAUDE.md` for credit ledger rules.

## Key Traits

### `HasUlid` (`app/Models/Concerns/HasUlid.php`)
- `initializeHasUlid()` sets `$incrementing = false`, `$keyType = 'string'`
- `bootHasUlid()` auto-generates a ULID on the `creating` event if `id` is empty
- All tenant-scoped tables use ULID PKs (`char(26)`)

### `BelongsToTenant` (`app/Models/Concerns/BelongsToTenant.php`)
- Auto-sets `tenant_id` from `current.tenant.id` binding on `creating`
- Applies `TenantScope` (adds `WHERE tenant_id = ?`) whenever the binding is non-null
- Escape hatch: `Dog::allTenants()->get()` — static method that removes the scope
- `Tenant` model does **not** use this trait — query it directly with `Tenant::where('slug', ...)`

### `TenantScope` (`app/Models/Scopes/TenantScope.php`)
- Skips the WHERE clause when `current.tenant.id` is null (platform admin routes)
- Tests set the binding: `app()->instance('current.tenant.id', $tenant->id)`
- Tests clear it in `tearDown`: `app()->forgetInstance('current.tenant.id')`

## Credit Ledger Model

`CreditLedger` (`credit_ledger` table):
- **Append-only** — enforced by a PostgreSQL RULE. No `UPDATE` or `DELETE` ever.
- `public const UPDATED_AT = null` — no `updated_at` column
- `dogs.credit_balance` is a denormalized fast-read column, updated after each ledger write by `DogCreditService`

## Soft Deletes

Tables with `deleted_at`: `tenants`, `users`, `customers`, `dogs`, `packages`.

Packages are **archived, never hard-deleted** — preserves FK integrity on historical orders.

## Package Type Enum

Valid values: `one_time`, `subscription`, `unlimited`. NOT `day_pack`.

## Notable Column Names

- `credit_ledger.delta` — the signed credit change (not `amount`)
- `orders.total_amount` — decimal (not `amount_cents`)
- `customers.email` — nullable (staff can create customers without email)
