# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Initial setup
composer setup

# Run all services (Laravel server, queue worker, log viewer, Vite)
composer dev

# Run tests
composer test

# Run a single test file
php artisan test tests/Feature/ExampleTest.php

# Run tests matching a filter
php artisan test --filter=SomeTestName

# Code style (Laravel Pint)
./vendor/bin/pint

# Migrations
php artisan migrate

# Tinker (REPL)
php artisan tinker
```

The `compose.yaml` configures Laravel Sail with MySQL 8.4, Redis, Meilisearch, Mailpit, and Selenium for Docker-based development.

## Project Overview

PawPass is a **multi-tenant SaaS platform for doggy daycare businesses** built on Laravel 12 + Vue.js. Each tenant (daycare) gets a branded subdomain and processes payments through Stripe Connect.

**Tech stack:** Laravel 12, PostgreSQL (native pg types), Vue.js, Vite, Tailwind CSS v4, Redis, Stripe Connect Express, Twilio (SMS), Resend (email), S3/R2 (dog photos), Meilisearch.

## Multi-Tenancy Architecture

- **Shared database** — all tenants in one PostgreSQL instance; every tenant-scoped table has `tenant_id char(26)`.
- A global Eloquent scope (`BelongsToTenant` trait) auto-filters all queries — no manual `WHERE tenant_id` in application code.
- Tenant is resolved from the subdomain on web routes, and from the JWT claim `tenant_id` on API routes.

```
{slug}.pawpass.com        → TenantMiddleware resolves slug
{slug}.pawpass.com/my/*   → Customer portal (CustomerPortalMiddleware)
{slug}.pawpass.com/api/*  → Admin/portal API
platform.pawpass.com      → Platform admin
```

## Core Domain Concepts

| Concept | Description |
|---|---|
| **Tenant** | One daycare business, identified by a slug |
| **Package** | Product sold to customers — one-time day packs or monthly subscriptions |
| **Credits** | Currency of attendance; one credit deducted per dog per check-in |
| **Credit Ledger** | Append-only PostgreSQL table — the source of truth for all credit transactions |
| **Attendance** | Check-in/check-out record per dog per visit |

### Roles

`platform_admin` > `business_owner` > `staff` > `customer`

## Credit Ledger

The `credit_ledger` table is **append-only** (enforced by a PostgreSQL RULE — no UPDATE/DELETE ever). `dogs.credit_balance` is a denormalized fast-read column updated after each ledger write.

All credit operations go through `DogCreditService`:
- `issueFromOrder`, `deductForAttendance`, `removeAllOnRefund`
- `addGoodwill`, `applyCorrection`, `transfer`, `expireCredits`

Ledger entry types: `purchase`, `subscription`, `deduction`, `refund`, `goodwill`, `correction_add`, `correction_remove`, `expiry_removal`, `transfer_in`, `transfer_out`.

A refund always removes **all** remaining credits regardless of refund amount. Manual adjustments (`goodwill`, `correction_*`, `transfer_*`) require a `note`. Dog-to-dog transfers are only allowed within the same customer account.

## Payments (Stripe Connect Express)

Flow: Customer → Stripe platform account → payout → business Connect Express account. Platform fee defaults to 5%, snapshotted on `orders.platform_fee_pct` at purchase time.

**One-time purchase:** `POST /api/portal/v1/orders` → creates PaymentIntent → frontend calls `stripe.confirmCardPayment` → `payment_intent.succeeded` webhook → `DogCreditService::issueFromOrder()`.

**Subscription:** SetupIntent flow → `invoice.payment_succeeded` webhook fires on each renewal → writes `subscription` ledger entry.

Webhook endpoints are all on `platform.pawpass.com/webhooks/*` and require signature verification (Stripe HMAC, Twilio, Resend shared secret).

## Database Schema Conventions

- **PostgreSQL** with native types: `text`, `timestamptz`, `jsonb`, `numeric`
- **ULIDs** (`char(26)`) for public-facing primary keys (tenant-scoped tables)
- **bigserial** for high-volume internal/log tables (`notification_logs`, etc.)
- All timestamps use `timestamptz` (stored UTC, displayed in tenant timezone)
- `text` over `varchar(n)` — use `CHECK` constraints for length limits
- Soft deletes (`deleted_at`) on `tenants`, `users`, `customers`, `dogs`, `packages`
- Packages are **archived, never deleted** — preserves FK integrity on historical orders

Migration order matters due to circular FKs between `tenants` and `users`, and between `users` and `customers`. See `requirements.md` §9 for the 25-step migration sequence.

## API Design

Three API namespaces, all returning `{ "data": ..., "meta": ... }` envelopes:

- `GET|POST /api/portal/v1/*` — customer self-service (60 req/min per JWT)
- `GET|POST /api/admin/v1/*` — staff/owner dashboard (60 req/min per JWT)
- `GET|POST /api/platform/v1/*` — cross-tenant platform admin (120 req/min per JWT)

**Auth:** JWT with RS256, 15-minute access tokens + 30-day opaque refresh tokens. JWT payload: `sub`, `tenant_id`, `role`, `iat`, `exp`.

`Idempotency-Key` header (UUID/ULID) is required on `POST /api/portal/v1/orders` and `POST /api/admin/v1/dogs/{id}/credits/*`.

## Notification System

All notifications go through `NotificationService::dispatch()`, which queues `SendNotificationJob` to the `notifications` queue. Channels: email (Resend), SMS (Twilio), in-app (database, always on).

`credits.low` and `credits.empty` events use a **60-second grouping window** to consolidate multi-dog alerts into one message per customer. `dogs.credits_alert_sent_at` enforces a 24-hour dedup per dog.

Critical notification types (`payment.confirmed`, `credits.empty`, auth events, etc.) always fire even if a tenant has disabled them.

## Scheduled Jobs

| Job | Schedule |
|---|---|
| `WarmTenantReportCaches` | 2 AM per-tenant timezone |
| `WarmPlatformReportCaches` | 3 AM UTC |
| `ExpireSubscriptionCredits` | 1 AM UTC |
| `PruneOldNotifications` | 4 AM UTC |
| `PruneRawWebhooks` | 4 AM UTC (7-day retention) |
| `PruneDispatchedPending` | 4 AM UTC |

## Testing

Tests live in `tests/Unit/` and `tests/Feature/`. The `phpunit.xml` configures the test environment to use SQLite (`DB_DATABASE=testing`), array cache/mail/session, and sync queue.

---

## Standard Workflow

### 1. Understand Before Changing

* Read the full problem carefully.
* Identify the exact expected behavior.
* Locate and review only the files directly related to the change.
* Do not modify anything yet.

### 2. Write a Plan in `tasks/todo.md`

* Break the work into small, independent steps.
* Each step must be:

  * Focused on a single concern
  * Easy to verify
  * Minimal in scope
* Use a checklist format so items can be marked complete.
* Include a short “Verification” note under each task explaining how success will be confirmed, such as a test, build check, or behavior change.

Example structure:

```md
## Task: Short Description

- [ ] Add failing test for X
  - Verification: Test fails for the correct reason

- [ ] Implement minimal change to pass test
  - Verification: Test passes

- [ ] Refactor if needed without changing behavior
  - Verification: All tests still pass
```

### 3. Pause for Approval

* Share the proposed plan.
* Wait for confirmation before making changes.

### 4. Work in Strict TDD Order

For each checklist item:

1. Add or update a test that defines the desired behavior.
2. Run tests and confirm the test fails for the expected reason.
3. Implement the smallest possible code change to make it pass.
4. Run tests again and confirm they pass.
5. Refactor only if it improves clarity and does not change behavior.
6. Mark the task complete in `todo.md`.

No large refactors. No speculative improvements.

### 5. Keep Changes Minimal

* Modify as few files as possible.
* Avoid unrelated cleanups.
* Prefer small diffs over clever solutions.
* If something feels large, break it into smaller tasks.

### 6. High Level Updates Only

After each completed task:

* Provide a short summary of:

  * What changed
  * Why it changed
  * How it was verified
* Do not paste large code blocks unless necessary.

### 7. Final Verification

When all tasks are complete:

* Run the full test suite.
* Run `npm run build`.
* Confirm:

  * No type errors
  * No build failures
  * No test regressions

### 8. Add a Review Section to `tasks/todo.md`

At the end of the file, include:

```md
## Review

### Summary of Changes
- Brief list of what was added or modified

### Tests Added or Updated
- List of new or modified tests

### Build Status
- Tests: Passing
- Build: Successful

### Notes
- Any follow up work or technical debt identified
```
