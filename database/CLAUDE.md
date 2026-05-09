# Database — `database/`

Loaded when editing migrations, factories, or seeders. See root `CLAUDE.md` for commands.

## Migration Conventions

### Timestamps & Column Types
- Always `timestampTz()` — **never** `timestamp()`
- `text` not `varchar(n)` — enforce length with `CHECK` constraints
- `numeric` for money, `jsonb` for structured data

### PostgreSQL Enum Columns
Blueprint cannot reference custom PG enum types. Add enum columns **after** `Schema::create`:

```php
Schema::create('my_table', function (Blueprint $table) {
    // other columns
});
DB::statement("ALTER TABLE my_table ADD COLUMN status my_enum_type NOT NULL DEFAULT 'pending'");
```

### `ALTER TYPE ... ADD VALUE`
Set `public $withinTransaction = false;` on the migration class — PostgreSQL cannot add enum values inside a transaction. No type annotation (parent property is untyped).

### Self-Referential Foreign Keys
Add via `DB::statement` AFTER `Schema::create` closes — Blueprint adds FKs before PRIMARY KEY is established:

```php
Schema::create('credit_ledger', function (Blueprint $table) { /* ... */ });
DB::statement('ALTER TABLE credit_ledger ADD CONSTRAINT ... FOREIGN KEY (parent_ledger_id) REFERENCES credit_ledger(id)');
```

### Foreign Key Syntax
```php
// Correct
$table->foreign('col')->references('id')->on('table');
// Wrong — silently ignored
$table->char('col')->references('id')->on('table');
```

## Primary Keys

| Table type | PK type |
|---|---|
| Tenant-scoped tables | `char(26)` ULID — use `HasUlid` trait on model |
| High-volume log tables (`notification_logs`) | `bigserial` / `bigIncrements` |

## Soft Deletes

Tables with `deleted_at`: `tenants`, `users`, `customers`, `dogs`, `packages`.

Packages are **archived, never deleted** — preserves FK integrity on historical orders.

## Notable Schema Details

- `credit_ledger` — no `updated_at` column (`UPDATED_AT = null` on model)
- `users.role` and `users.status` — `text` with CHECK constraints (not PG enums — users migration runs before enum types migration)
- `notifications` — standard Laravel schema + `tenant_id char(26) nullable`
- `notification_logs.notification_id` — uuid (nullable, FK → notifications.id ON DELETE SET NULL)

## Factories

- `TenantFactory::withOwner()` — creates tenant + linked `business_owner` user
- `DogFactory::forCustomer(Customer)` — sets customer + tenant
- `fake()->hexColor()` already returns `#xxxxxx` — do NOT prepend `#`
- Optional dates: use ternary — `fake()->boolean(70) ? fake()->dateTime()->format('Y-m-d') : null` (not `optional()->dateTime()->format(...)` — crashes when optional returns null)
