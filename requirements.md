# PawPass — Complete Product Specification

> **Version:** 1.0 | **Stack:** Laravel · Vue.js · PostgreSQL · Stripe Connect · Twilio · Postmark
> **Purpose:** Engineering reference for all developers working on PawPass. This document is the single source of truth.

-----

## Table of Contents

1. [Product Overview](#1-product-overview)
1. [Feature Specification](#2-feature-specification)
1. [Credit Ledger](#3-credit-ledger)
1. [Payments & Billing](#4-payments--billing)
1. [Tenant Architecture](#5-tenant-architecture)
1. [Customer Portal](#6-customer-portal)
1. [Notification System](#7-notification-system)
1. [Business Admin Dashboard](#8-business-admin-dashboard)
1. [Database Schema](#9-database-schema)
1. [API Design](#10-api-design)
1. [Reporting & Analytics](#11-reporting--analytics)
1. [Platform Subscription Plans](#12-platform-subscription-plans)

-----

## 1. Product Overview

PawPass is a multi-tenant SaaS platform for doggy daycare businesses. Each business (tenant) gets a branded subdomain, manages their own packages, customers, and dogs, and accepts payments through Stripe Connect. Customers access a self-service portal to purchase packages, track credits, and manage their dogs.

### Core Concepts

|Concept |Description |
|-------------------|--------------------------------------------------------------------------|
|**Tenant** |One daycare business. Identified by a slug (e.g. `happypaws`). |
|**Package** |A product sold to customers — one-time day packs or monthly subscriptions.|
|**Credits** |The currency of attendance. Each check-in deducts one credit per dog. |
|**Credit Ledger** |An append-only log of every credit transaction. The source of truth. |
|**Attendance** |A check-in/check-out record per dog per visit. |
|**Customer Portal**|The `/my/*` subdomain path where customers self-serve. |
|**Admin Dashboard**|The staff/owner interface for running daily operations. |

### Roles

|Role |Access |
|----------------|------------------------------------------------------------------|
|`platform_admin`|PawPass internal — cross-tenant access, billing, suspension |
|`business_owner`|Full admin access including packages, reports, settings |
|`staff` |Operational access — check-in, customers, dogs, credit adjustments|
|`customer` |Portal only — their own dogs, purchases, credit history |

### Phase Roadmap

- **Phase 1 (MVP):** One-time packages, manual check-in, customer portal, email notifications, monthly payouts
- **Phase 2:** Subscriptions, SMS notifications, daily/weekly payouts, PWA install, bulk check-in
- **Phase 3:** Multi-dog packages, credit transfers, reporting dashboard, platform analytics

-----

## 2. Feature Specification

### P0 — Must Have (MVP)

- Tenant self-signup with email verification and Stripe Connect onboarding
- Package creation (one-time, fixed credits)
- Customer registration and login via customer portal
- One-time package purchase via Stripe
- Credit deduction on check-in
- Staff check-in / check-out interface (daily roster)
- Customer credit balance display
- Email notifications: purchase confirmed, credits low, credits empty
- Owner: view financial summary, manage packages
- Platform admin: tenant management, suspension

### P1 — Should Have

- Monthly subscription packages (unlimited credits)
- SMS notifications via Twilio
- Bulk check-in for multiple dogs simultaneously
- Zero-credit override with required note
- Manual credit adjustments (goodwill, correction, transfer)
- Staff invite system
- Customer portal PWA (installable, offline-capable)
- Dog photo upload
- Invoice PDF download
- Attendance history for customers
- Daily/weekly payout schedules (paid feature)

### P2 — Nice to Have

- Multi-dog packages (one purchase covers N dogs)
- Scheduling / pre-booking
- Real-time notification bell (Laravel Echo + Pusher)
- Advanced reporting dashboard with charts
- Public API for third-party integrations

-----

## 3. Credit Ledger

### Design Principles

- **Append-only.** No `UPDATE` or `DELETE` ever runs against `credit_ledger`. Enforced by a PostgreSQL rule.
- **Balance derived, not stored.** `dogs.credit_balance` is a denormalized fast-read column updated after each ledger write. The ledger is the audit truth.
- **Every credit event is a row.** Purchase, deduction, refund, adjustment, expiry — all produce a ledger entry.

### Entry Types

|Type |Delta|When |
|-------------------|-----|-----------------------------------------------------|
|`purchase` |+ |One-time package purchased and paid |
|`subscription` |+ |Subscription renewed — credits for the billing period|
|`deduction` |− |Dog checked in |
|`refund` |− |Refund issued — removes all remaining credits |
|`goodwill` |+ |Staff manually adds credits |
|`correction_add` |+ |Staff corrects an error |
|`correction_remove`|− |Staff corrects an over-credit |
|`expiry_removal` |− |Subscription credits expired at period end |
|`transfer_out` |− |Credits transferred to another dog |
|`transfer_in` |+ |Credits received from another dog |

### Key Rules

- A refund (full or partial) always removes **all** remaining credits — not a proportional amount.
- Credits from subscriptions carry an `expires_at` timestamp — the end of the billing period.
- Dog-to-dog transfers are only allowed within the same customer account.
- Manual adjustments (`goodwill`, `correction_*`, `transfer_*`) require a `note`.
- The `parent_ledger_id` field links `transfer_in`/`transfer_out` pairs to each other.

### DogCreditService — Key Methods

```php
// Issue credits from a purchase
DogCreditService::issueFromOrder(Order $order, Dog $dog): void

// Deduct one credit on check-in
DogCreditService::deductForAttendance(Attendance $attendance): void

// Remove all credits on refund
DogCreditService::removeAllOnRefund(Order $order, Dog $dog): void

// Goodwill credit
DogCreditService::addGoodwill(Dog $dog, int $credits, string $note, User $by): void

// Correction
DogCreditService::applyCorrection(Dog $dog, int $delta, string $note, User $by): void

// Dog-to-dog transfer
DogCreditService::transfer(Dog $from, Dog $to, int $credits): void

// Nightly expiry sweep
DogCreditService::expireCredits(Dog $dog): void
```

-----

## 4. Payments & Billing

### Architecture

PawPass uses **Stripe Connect Express**. The platform account collects payments and distributes payouts to connected business accounts.

```
Customer → Stripe (platform account) → Payout → Business (Connect Express account)
↓
Platform fee retained
```

### Platform Fee

- Configurable per tenant, defaulting to **5%** of gross.
- Snapshotted onto `orders.platform_fee_pct` at purchase time — changing the fee does not affect historical orders.
- Platform admin can override per-tenant via `/api/platform/v1/tenants/{id}`.

### One-Time Purchase Flow

1. Customer selects package → frontend calls `POST /api/portal/v1/orders`
1. API creates order record, calls Stripe to create PaymentIntent with `application_fee_amount`
1. Returns `client_secret` to frontend
1. Frontend calls `stripe.confirmCardPayment(client_secret)`
1. Stripe fires `payment_intent.succeeded` webhook
1. Webhook handler: marks order paid, calls `DogCreditService::issueFromOrder()`, fires `payment.confirmed` notification

### Subscription Flow

1. Customer selects subscription package → `POST /api/portal/v1/subscriptions`
1. API creates Stripe SetupIntent, returns `client_secret`
1. Frontend confirms payment method, Stripe creates subscription
1. `invoice.payment_succeeded` webhook fires on each renewal
1. Webhook handler: writes `subscription` ledger entry, updates `credits_expire_at` on dog

### Refund Rules

- Full or partial refund amount via Stripe — regardless of amount, **all remaining credits are removed**.
- Refund creates a `refund` ledger entry with a negative delta equal to the remaining balance.
- Webhook `charge.refunded` confirms the money movement; the credit removal happens immediately on API call.

### Payout Schedules

|Schedule |Cost |
|----------------------|--------------------|
|Monthly (1st of month)|Free |
|Weekly |Phase 2 paid feature|
|Daily |Phase 2 paid feature|

### Key Webhook Events Handled

|Event |Action |
|-------------------------------|---------------------------------------|
|`payment_intent.succeeded` |Issue credits, confirm order |
|`payment_intent.payment_failed`|Log, notify customer |
|`invoice.payment_succeeded` |Renew subscription credits |
|`invoice.payment_failed` |Mark `past_due`, notify customer |
|`customer.subscription.deleted`|Cancel, remove credits, notify |
|`charge.dispute.created` |Flag order, alert platform admin |
|`charge.dispute.closed` |Resolve based on outcome |
|`account.updated` |Update Stripe Connect onboarding status|

-----

## 5. Tenant Architecture

### Multi-Tenancy Model

- **Shared database** — all tenants in one PostgreSQL instance.
- Every tenant-scoped table carries `tenant_id char(26)`.
- A global Eloquent scope (`BelongsToTenant` trait) auto-filters all queries — no manual `WHERE tenant_id` anywhere in application code.
- Tenant resolved from `Host` header (subdomain) on web routes, and from JWT claim `tenant_id` on API routes.

### Subdomain Routing

```
happypaws.pawpass.com → TenantMiddleware resolves slug "happypaws"
happypaws.pawpass.com/my/* → Customer portal
happypaws.pawpass.com/api/* → Admin/portal API
platform.pawpass.com → Platform admin
```

### BelongsToTenant Trait

```php
trait BelongsToTenant
{
protected static function bootBelongsToTenant(): void
{
static::addGlobalScope(new TenantScope);

static::creating(function ($model) {
if (auth()->check()) {
$model->tenant_id ??= auth()->user()->tenant_id;
}
});
}
}
```

### Tenant Lifecycle

```
pending_verification → active → suspended → cancelled
↘ (30-day grace on cancellation)
```

### Self-Signup Flow

1. Business owner fills signup form (name, email, business name, desired slug)
1. System validates slug availability against `tenants` and `reserved_slugs` tables
1. Creates `tenant` (status: `pending_verification`) and `user` (role: `business_owner`)
1. Sends email verification link
1. On verify: status → `active`, triggers Stripe Connect onboarding redirect
1. On Stripe `account.updated` webhook with `charges_enabled: true`: marks `stripe_onboarded_at`

### Reserved Slugs (seed list)

`www`, `app`, `api`, `platform`, `admin`, `docs`, `mail`, `static`, `assets`, `status`, `support`, `help`, `billing`, `webhook`, `webhooks`, `dashboard`, `login`, `register`, `signup`, `auth`, `oauth`, `health`, `stripe`, `twilio`, `postmark`, `internal`, `system`, `pawpass`

-----

## 6. Customer Portal

### Routes

All portal routes live under `/{slug}.pawpass.com/my/*` and are protected by `CustomerPortalMiddleware`.

|Route |Page |
|-----------------------|-------------------------------------------|
|`GET /my` |Dashboard — credit summary, recent activity|
|`GET /my/dogs` |Dog list |
|`GET /my/dogs/add` |Add dog form |
|`GET /my/dogs/{id}` |Dog detail — credits, attendance |
|`GET /my/purchase` |Package selection |
|`GET /my/history` |Purchase history |
|`GET /my/attendance` |Attendance history |
|`GET /my/notifications`|Notification inbox |
|`GET /my/account` |Account settings |

### Dog Management Rules

- Customers can add and edit their own dogs.
- Customers **cannot delete** dogs — staff only.
- Dog photo upload: JPEG/PNG, max 5MB, resized to 800×800, stored on S3/R2.

### PWA

- Dynamic per-tenant `manifest.json` at `/my/manifest.json` — injects tenant name, logo, and brand colour.
- Service worker caches shell assets and API responses for offline use.
- Installable on iOS and Android via browser prompt.

### Registration Flow

1. Customer visits `/{slug}.pawpass.com/my/register`
1. Fills name, email, password, optional phone
1. System creates `user` (role: `customer`) and `customer` record in a single transaction
1. Sends email verification link
1. On verify: portal access granted

-----

## 7. Notification System

### Architecture

Every trigger event calls `NotificationService::dispatch()`, which:

1. Checks if the notification type is critical (always fires) or enabled for the tenant
1. Resolves recipients
1. For groupable types (`credits.low`, `credits.empty`): enqueues to the 60-second grouping window
1. For all other types: dispatches `SendNotificationJob` immediately to the `notifications` queue

All jobs process asynchronously. Delivery results are logged to `notification_logs` regardless of success or failure.

### Channels

|Channel|Provider |Can Be Disabled |
|-------|--------------------------------|----------------------|
|Email |Postmark |Per-type, per-customer|
|SMS |Twilio (single platform account)|Per-type, per-customer|
|In-App |Database |Never — always on |

### Notification Types

|Type |Critical|Tenant Can Disable|
|-----------------------------|--------|------------------|
|`payment.confirmed` |✓ |No |
|`payment.refunded` |✓ |No |
|`subscription.renewed` | |Yes |
|`subscription.payment_failed`|✓ |No |
|`subscription.cancelled` |✓ |No |
|`credits.low` | |Yes |
|`credits.empty` |✓ |No |
|`goodwill.added` | |Yes |
|`credit.correction` | |Yes |
|`credits.transferred` | |Yes |
|`credits.expired` | |Yes |
|`checkin.confirmed` | |Yes |
|`auth.verify_email` |✓ |No |
|`auth.password_reset` |✓ |No |

### Tenant Settings Model

Default-on: a row in `tenant_notification_settings` **disables** that type. Absence = enabled.
Critical types are enforced in code — even if a settings row exists, they always fire.

### Multi-Dog Grouping (credits.low / credits.empty)

When multiple dogs trigger `credits.low` or `credits.empty` for the same customer within 60 seconds, PawPass sends **one** consolidated notification instead of one per dog.

```php
// Flow for groupable types
NotificationService::enqueueGrouped($event)
→ NotificationPending::firstOrCreate(tenant_id, user_id, type, dispatched_at=null)
→ If new: schedule DispatchGroupedAlertJob::dispatch($pending->id)->delay(60s)
→ If existing: append dog_id to pending.dog_ids

// DispatchGroupedAlertJob
→ Atomically claims row (whereNull dispatched_at + update)
→ Loads all accumulated dog_ids
→ Upgrades type to credits.empty if any dog has balance = 0
→ Sends one notification per channel covering all dogs
```

**24-hour dedup:** `dogs.credits_alert_sent_at` prevents re-alerting the same dog within 24 hours.

### Retry Policy

|Attempt |Delay |On Failure |
|---------|---------|---------------------------------|
|1st |Immediate|Log |
|2nd |60s |Log |
|3rd |120s |Log |
|After 3rd|— |Mark failed, alert platform admin|

-----

## 8. Business Admin Dashboard

### Navigation

- Desktop: persistent left sidebar
- Mobile/tablet: bottom tab bar with “More” overflow drawer
- Owner-only items (Packages, Reports, most Settings) are hidden from staff, not just disabled

### Role Access Summary

|Action |Owner|Staff|
|----------------------------|-----|-----|
|View dashboard & roster |✓ |✓ |
|Check in / check out dogs |✓ |✓ |
|Bulk check-in |✓ |✓ |
|Manage customers & dogs |✓ |✓ |
|Apply credit adjustments |✓ |✓ |
|Issue refunds |✓ |✓ |
|Create/edit/archive packages|✓ |✗ |
|View financial reports |✓ |✗ |
|Notification settings |✓ |✗ |
|Business profile & branding |✓ |✗ |
|Manage staff accounts |✓ |✗ |

### Daily Roster — Dog States

|State |Badge |Button |
|---------------------------|------------|-------------------|
|Credits > 0, not checked in|● Ready |Check In |
|Credits low, not checked in|⚠ Low |Check In |
|0 credits, block enabled |✗ No Credits|Check In (disabled)|
|Currently checked in |✓ Here |Check Out |
|Checked out today |✓ Done |— |

### Check-In Flow

1. Staff clicks **Check In**
1. If credits > 0: attendance row created, credit deducted, row updates to “Here”
1. If 0 credits + block enabled: button disabled, Override button shown
1. Override requires a note; creates attendance row with `zero_credit_override = true`; no credit deducted

### Bulk Check-In

- Staff enters selection mode → selects dogs → clicks “Check In Selected (N)”
- Confirmation modal shows per-dog credit preview including zero-credit dogs
- On confirm: all valid check-ins fire as parallel API calls
- Zero-credit dogs in selection require a shared override note in the modal

### Package Rules

- Packages are **archived, never deleted** — historical orders maintain FK integrity
- Active subscriptions on an archived package continue to renew until the customer cancels
- Archiving hides the package from the customer portal immediately

### Staff Invite Flow

1. Owner enters staff email → clicks Invite
1. System creates `user` with `status = pending_invite`, sends email with one-time signup link (expires 48h)
1. Staff clicks link, sets password, account activated
1. Owner cannot set the staff member’s password directly

-----

## 9. Database Schema

### Conventions

- **PostgreSQL** — all native pg types: `text`, `timestamptz`, `jsonb`, `numeric`
- **ULIDs** (`char(26)`) for public-facing primary keys — sortable, URL-safe, non-enumerable
- **bigserial** for high-volume internal/log tables (`notification_logs`, `tenant_notification_settings`, etc.)
- All timestamps use `timestamptz` (stored UTC, displayed in tenant timezone)
- `text` over `varchar(n)` — PostgreSQL has no performance difference; use `CHECK` constraints for length limits
- Soft deletes (`deleted_at`) on `tenants`, `users`, `customers`, `dogs`, `packages`

### Table Catalogue

|# |Table |PK |Group |
|--|-------------------------------|---------|-------------|
|1 |`tenants` |ULID |Core |
|2 |`users` |ULID |Core |
|3 |`customers` |ULID |Core |
|4 |`dogs` |ULID |Core |
|5 |`packages` |ULID |Commerce |
|6 |`orders` |ULID |Commerce |
|7 |`order_dogs` |bigserial|Commerce |
|8 |`subscriptions` |ULID |Commerce |
|9 |`credit_ledger` |ULID |Credits |
|10|`attendances` |ULID |Operations |
|11|`notifications` |ULID |Notifications|
|12|`notification_logs` |bigserial|Notifications|
|13|`notification_pending` |ULID |Notifications|
|14|`tenant_notification_settings` |bigserial|Notifications|
|15|`user_notification_preferences`|bigserial|Notifications|
|16|`tenant_settings` |bigserial|Config |
|17|`sms_optouts` |bigserial|Config |
|18|`email_suppressions` |bigserial|Config |
|19|`platform_audit_log` |ULID |Platform |
|20|`reserved_slugs` |bigserial|Platform |

### Key Table Definitions

#### `tenants`

```sql
id char(26) PRIMARY KEY
name text NOT NULL
slug text NOT NULL UNIQUE -- CHECK(slug ~ '^[a-z0-9-]{3,63}$')
owner_user_id char(26) REFERENCES users(id)
status tenant_status NOT NULL -- active|pending_verification|suspended|cancelled
stripe_account_id text
stripe_onboarded_at timestamptz
platform_fee_pct numeric(5,2) NOT NULL DEFAULT 5.0
payout_schedule text NOT NULL DEFAULT 'monthly'
low_credit_threshold integer NOT NULL DEFAULT 2
checkin_block_at_zero boolean NOT NULL DEFAULT true
timezone text NOT NULL DEFAULT 'America/Chicago'
primary_color char(7) -- CHECK(primary_color ~ '^#[0-9A-Fa-f]{6}$')
created_at timestamptz NOT NULL
updated_at timestamptz NOT NULL
deleted_at timestamptz
```

#### `dogs`

```sql
id char(26) PRIMARY KEY
tenant_id char(26) NOT NULL REFERENCES tenants(id)
customer_id char(26) NOT NULL REFERENCES customers(id)
name text NOT NULL
breed text
dob date
sex text -- CHECK IN ('male','female','unknown')
photo_url text
vet_name text
vet_phone text
credit_balance integer NOT NULL DEFAULT 0 -- denormalized; ledger is truth
credits_expire_at timestamptz -- subscription credits only
credits_alert_sent_at timestamptz -- 24h dedup for low/empty alerts
created_at timestamptz NOT NULL
updated_at timestamptz NOT NULL
deleted_at timestamptz -- staff only; customers cannot delete
```

#### `credit_ledger`

```sql
id char(26) PRIMARY KEY
tenant_id char(26) NOT NULL REFERENCES tenants(id)
dog_id char(26) NOT NULL REFERENCES dogs(id)
type ledger_type NOT NULL
delta integer NOT NULL -- positive = add, negative = remove
balance_after integer NOT NULL -- snapshot for display
expires_at timestamptz -- subscription entries only
order_id char(26) REFERENCES orders(id)
attendance_id char(26) REFERENCES attendances(id)
subscription_id char(26) REFERENCES subscriptions(id)
parent_ledger_id char(26) REFERENCES credit_ledger(id) -- transfer pairs
note text
created_by char(26) REFERENCES users(id) -- NULL for system entries
created_at timestamptz NOT NULL
-- NO updated_at. Append-only — protected by PostgreSQL RULE.
```

#### `attendances`

```sql
id char(26) PRIMARY KEY
tenant_id char(26) NOT NULL REFERENCES tenants(id)
dog_id char(26) NOT NULL REFERENCES dogs(id)
checked_in_at timestamptz NOT NULL
checked_out_at timestamptz
checked_in_by char(26) NOT NULL REFERENCES users(id)
checked_out_by char(26) REFERENCES users(id)
zero_credit_override boolean NOT NULL DEFAULT false
override_note text
edited_at timestamptz
edited_by char(26) REFERENCES users(id)
edit_note text
original_in timestamptz -- preserved before edit
original_out timestamptz -- preserved before edit
created_at timestamptz NOT NULL
updated_at timestamptz NOT NULL
```

### PostgreSQL Enum Types

```sql
CREATE TYPE tenant_status AS ENUM ('active','pending_verification','suspended','cancelled');
CREATE TYPE user_role AS ENUM ('platform_admin','business_owner','staff','customer');
CREATE TYPE package_type AS ENUM ('one_time','subscription');
CREATE TYPE order_status AS ENUM ('paid','partially_refunded','refunded','disputed');
CREATE TYPE sub_status AS ENUM ('active','past_due','cancelled','unpaid');
CREATE TYPE ledger_type AS ENUM (
'purchase','subscription','deduction','refund','goodwill',
'correction_add','correction_remove','expiry_removal',
'transfer_in','transfer_out'
);
CREATE TYPE notif_channel AS ENUM ('email','sms','in_app');
CREATE TYPE notif_status AS ENUM ('queued','sent','delivered','failed','skipped');
```

### Migration Order

```
01 create_enum_types
02 create_reserved_slugs_table
03 create_tenants_table (owner_user_id FK added later)
04 create_users_table
05 alter_tenants_add_owner_fk (resolves tenants ↔ users circular FK)
06 create_customers_table
07 alter_users_add_customer_fk (resolves users ↔ customers circular FK)
08 create_dogs_table
09 create_packages_table
10 create_orders_table
11 create_order_dogs_table
12 create_subscriptions_table
13 create_attendances_table
14 create_credit_ledger_table
15 create_notifications_table
16 create_notification_logs_table
17 create_notification_pending_table
18 create_tenant_notification_settings_table
19 create_user_notification_preferences_table
20 create_tenant_settings_table
21 create_sms_optouts_table
22 create_email_suppressions_table
23 create_platform_audit_log_table
24 seed_reserved_slugs
25 add_append_only_rule_to_credit_ledger
```

### Key Indexes

```sql
-- Tenant lookup on every request
CREATE UNIQUE INDEX tenants_slug_key ON tenants(slug) WHERE deleted_at IS NULL;

-- Email unique per tenant
CREATE UNIQUE INDEX users_tenant_email_key ON users(tenant_id, email) WHERE deleted_at IS NULL;

-- Roster — all active dogs for tenant
CREATE INDEX dogs_tenant_id_idx ON dogs(tenant_id) WHERE deleted_at IS NULL;

-- Credit ledger — primary read path
CREATE INDEX ledger_dog_created_idx ON credit_ledger(dog_id, created_at DESC);

-- Stripe webhook idempotency
CREATE UNIQUE INDEX orders_stripe_pi_id_key ON orders(stripe_pi_id);
CREATE UNIQUE INDEX subs_stripe_sub_id_key ON subscriptions(stripe_sub_id);

-- Roster active check-ins
CREATE INDEX attend_tenant_active_idx ON attendances(tenant_id, checked_out_at)
WHERE checked_out_at IS NULL;

-- Nightly expiry sweep
CREATE INDEX dogs_credits_expire_at_idx ON dogs(credits_expire_at)
WHERE credits_expire_at IS NOT NULL AND deleted_at IS NULL;

-- Notification grouping window
CREATE INDEX pending_user_type_idx ON notification_pending(user_id, type, dispatched_at)
WHERE dispatched_at IS NULL;

-- SMS opt-out check
CREATE UNIQUE INDEX sms_optouts_phone_key ON sms_optouts(phone);
```

-----

## 10. API Design

### Base URLs

```
# Customer portal
https://{slug}.pawpass.com/api/portal/v1/*

# Admin dashboard
https://{slug}.pawpass.com/api/admin/v1/*

# Platform admin
https://platform.pawpass.com/api/platform/v1/*

# Webhooks
https://platform.pawpass.com/webhooks/*
```

### Authentication — JWT

- **Access tokens:** 15-minute expiry, JWT signed with RS256
- **Refresh tokens:** 30-day expiry, opaque, stored in `personal_access_tokens`
- JWT payload: `sub` (user ULID), `tenant_id`, `role`, `iat`, `exp`
- `tenant_id: null` for platform admin tokens
- All API routes (except `auth/*`) require `Authorization: Bearer {access_token}`

### Response Envelope

```json
// Success
{ "data": { ... }, "meta": {} }

// Collection
{ "data": [ ... ], "meta": { "total": 48, "per_page": 20, "next_cursor": "..." } }

// Validation error (422)
{ "message": "The given data was invalid.", "errors": { "email": ["..."] } }

// Other errors
{ "message": "Unauthenticated.", "error_code": "AUTH_REQUIRED" }
```

### Rate Limiting

|Surface |Limit |
|--------------|-------------------|
|Portal + Admin|60 req/min per JWT |
|Platform Admin|120 req/min per JWT|
|Webhooks |600 req/min per IP |

Returns `429` with `Retry-After` header on breach.

### Idempotency

`Idempotency-Key` header (UUID/ULID) required on:

- `POST /api/portal/v1/orders`
- `POST /api/admin/v1/dogs/{id}/credits/*`

Missing key → `400 IDEMPOTENCY_KEY_REQUIRED`

-----

### Customer Portal Endpoints

#### Auth

|Method|Path |Description |
|------|-------------------------------------|---------------------------------------|
|`POST`|`/api/portal/v1/auth/login` |Login — returns access + refresh tokens|
|`POST`|`/api/portal/v1/auth/refresh` |Refresh access token |
|`POST`|`/api/portal/v1/auth/logout` |Revoke refresh token |
|`POST`|`/api/portal/v1/auth/register` |Customer self-registration |
|`POST`|`/api/portal/v1/auth/verify-email` |Verify email from link token |
|`POST`|`/api/portal/v1/auth/forgot-password`|Send password reset email |
|`POST`|`/api/portal/v1/auth/reset-password` |Reset password |

#### Account & Dogs

|Method |Path |Description |
|-------|-----------------------------|------------------------------|
|`GET` |`/account` |Get customer profile |
|`PATCH`|`/account` |Update name, email, phone |
|`PATCH`|`/account/password` |Change password |
|`GET` |`/account/notification-prefs`|Get channel preferences |
|`PUT` |`/account/notification-prefs`|Update channel opt-outs |
|`GET` |`/dogs` |List dogs |
|`POST` |`/dogs` |Add dog |
|`GET` |`/dogs/{id}` |Dog detail with credit balance|
|`PATCH`|`/dogs/{id}` |Update dog profile |

#### Purchasing

|Method|Path |Description |
|------|----------------------------|---------------------------------------------|
|`GET` |`/packages` |List visible packages |
|`POST`|`/orders` |Create order — returns Stripe `client_secret`|
|`GET` |`/orders` |Order history |
|`GET` |`/orders/{id}/invoice` |Download invoice PDF |
|`POST`|`/subscriptions` |Subscribe — returns Stripe `client_secret` |
|`POST`|`/subscriptions/{id}/cancel`|Cancel at period end |

#### Credits & Notifications

|Method |Path |Description |
|-------|--------------------------|-----------------------------|
|`GET` |`/dogs/{id}/credits` |Credit ledger for a dog |
|`GET` |`/attendance` |Attendance history |
|`GET` |`/notifications` |Notification inbox |
|`GET` |`/notifications/count` |Unread count (for bell badge)|
|`PATCH`|`/notifications/{id}/read`|Mark read |
|`POST` |`/notifications/read-all` |Mark all read |

-----

### Admin Dashboard Endpoints

#### Dashboard & Roster

|Method|Path |Auth |Description |
|------|------------------|------|------------------------------------------|
|`GET` |`/dashboard` |staff+|Stats, alerts, recent activity |
|`GET` |`/roster` |staff+|All active dogs with credit status |
|`POST`|`/roster/checkin` |staff+|Check in one or more dogs (bulk supported)|
|`POST`|`/roster/checkout`|staff+|Check out a dog |

#### Customers & Dogs

|Method |Path |Auth |Description |
|--------|--------------------------------|------|------------------------------------|
|`GET` |`/customers` |staff+|Paginated list with search |
|`POST` |`/customers` |staff+|Create customer |
|`GET` |`/customers/{id}` |staff+|Detail with dogs, orders, attendance|
|`PATCH` |`/customers/{id}` |staff+|Update |
|`GET` |`/dogs` |staff+|All active dogs for tenant |
|`POST` |`/dogs` |staff+|Add dog to customer |
|`GET` |`/dogs/{id}` |staff+|Dog detail |
|`PATCH` |`/dogs/{id}` |staff+|Update |
|`DELETE`|`/dogs/{id}` |staff+|Soft delete |
|`POST` |`/dogs/{id}/credits/goodwill` |staff+|Add goodwill credits |
|`POST` |`/dogs/{id}/credits/correction` |staff+|Add/remove correction credits |
|`POST` |`/dogs/{id}/credits/transfer` |staff+|Transfer credits to another dog |
|`PATCH` |`/dogs/{id}/attendance/{att_id}`|staff+|Edit attendance record |

#### Packages, Payments, Settings

|Method |Path |Auth |Description |
|--------|-----------------------------|------|----------------------|
|`POST` |`/packages` |owner |Create package |
|`PATCH` |`/packages/{id}` |owner |Edit package |
|`POST` |`/packages/{id}/archive` |owner |Archive package |
|`GET` |`/payments` |staff+|Paginated payment list|
|`POST` |`/payments/{order_id}/refund`|staff+|Issue refund |
|`GET` |`/settings/business` |owner |Business profile |
|`PATCH` |`/settings/business` |owner |Update branding |
|`GET` |`/settings/notifications` |owner |Notification toggles |
|`PUT` |`/settings/notifications` |owner |Update toggles |
|`POST` |`/settings/staff/invite` |owner |Invite staff |
|`DELETE`|`/settings/staff/{user_id}` |owner |Deactivate staff |

-----

### Platform Admin Endpoints

|Method |Path |Description |
|-------|----------------------------------------|------------------------------|
|`GET` |`/tenants` |All tenants with status filter|
|`GET` |`/tenants/{id}` |Full tenant detail |
|`PATCH`|`/tenants/{id}` |Update fee, payout schedule |
|`POST` |`/tenants/{id}/suspend` |Suspend with reason |
|`POST` |`/tenants/{id}/reinstate` |Reinstate |
|`POST` |`/tenants/{id}/cancel` |Begin 30-day cancellation |
|`GET` |`/notifications/delivery` |Delivery stats by channel |
|`POST` |`/notifications/failures/{log_id}/retry`|Retry failed delivery |
|`GET` |`/audit-log` |Global platform audit log |

-----

### Error Codes

|Status|Code |When |
|------|-------------------------------|---------------------------------------|
|400 |`IDEMPOTENCY_KEY_REQUIRED` |Missing key on POST /orders or credits |
|401 |`AUTH_REQUIRED` |No token or expired access token |
|401 |`REFRESH_TOKEN_EXPIRED` |Must log in again |
|403 |`INSUFFICIENT_ROLE` |Staff on owner-only endpoint |
|403 |`TENANT_MISMATCH` |Resource belongs to different tenant |
|403 |`TENANT_SUSPENDED` |Write attempt on suspended tenant |
|403 |`ZERO_CREDITS_BLOCKED` |Check-in with 0 credits, no override |
|404 |`NOT_FOUND` |Resource missing or soft-deleted |
|409 |`DOG_ALREADY_CHECKED_IN` |Open attendance record exists |
|409 |`TRANSFER_INSUFFICIENT_CREDITS`|Exceeds source dog balance |
|409 |`TRANSFER_CROSS_CUSTOMER` |Different customer dogs |
|409 |`PACKAGE_ARCHIVED` |Purchase on archived package |
|422 |`VALIDATION_ERROR` |Per-field errors in `errors` object |
|429 |`RATE_LIMITED` |Too many requests — check `Retry-After`|
|503 |`STRIPE_UNAVAILABLE` |Stripe timeout — safe to retry |

-----

### Webhook Endpoints

All webhooks on `platform.pawpass.com`. Signature verification required — reject on failure.

|Provider |Path |Verification |
|----------------|--------------------------|--------------------------------------|
|Stripe |`/webhooks/stripe` |`Stripe-Signature` HMAC via Stripe SDK|
|Twilio (status) |`/webhooks/twilio/status` |`X-Twilio-Signature` via Twilio SDK |
|Twilio (inbound)|`/webhooks/twilio/inbound`|`X-Twilio-Signature` via Twilio SDK |
|Postmark |`/webhooks/postmark` |`X-Postmark-Token` shared secret |

All verified payloads logged to `raw_webhooks` (ring buffer, 7-day retention).

-----

## 11. Reporting & Analytics

### Report Catalogue

|# |Report |Audience|Refresh |
|--|-----------------------|--------|------------------------------|
|1 |Revenue Summary |Owner |Nightly cache |
|2 |Payout Forecast |Owner |Real-time (5 min Stripe cache)|
|3 |Package Performance |Owner |Nightly cache |
|4 |Credit Issuance & Usage|Owner |Nightly cache |
|5 |Customer Lifetime Value|Owner |Nightly cache |
|6 |Attendance Summary |Staff+ |Real-time |
|7 |Daily Roster History |Staff+ |Real-time |
|8 |Zero & Low Credit Dogs |Staff+ |Real-time (no cache) |
|9 |Staff Activity Log |Owner |Real-time |
|10|Platform Revenue |Platform|Nightly cache |
|11|Tenant Health |Platform|Nightly cache |
|12|Notification Delivery |Platform|Real-time |

### Refresh Strategy

- **Real-time:** Fresh DB query on each request. Target < 200ms.
- **Nightly cache:** Computed at 2:00 AM tenant-local time, stored in Redis, TTL 25h.
- **Platform cache:** Computed at 3:00 AM UTC across all tenants.
- **Cache miss:** Run live, return result, write to cache asynchronously. If > 5s, return `202 Accepted` with polling URL.

### Report Endpoints

```
GET /api/admin/v1/reports/revenue ?from= &to= &group_by=month|week|day
GET /api/admin/v1/reports/payout-forecast
GET /api/admin/v1/reports/packages ?from= &to=
GET /api/admin/v1/reports/credits ?from= &to=
GET /api/admin/v1/reports/customers/ltv ?from= &to=
GET /api/admin/v1/reports/attendance ?from= &to= &group_by=
GET /api/admin/v1/reports/roster-history ?date=
GET /api/admin/v1/reports/credit-status
GET /api/admin/v1/reports/staff-activity ?from= &to= &user_id=
GET /api/platform/v1/reports/revenue ?from= &to=
GET /api/platform/v1/reports/tenant-health
GET /api/platform/v1/reports/notifications ?from= &to= &tenant_id=
```

Append `?format=csv` to any tabular report for a streamed CSV download.

### Key Revenue Query

```sql
SELECT
date_trunc('month', created_at AT TIME ZONE tenant_tz) AS month,
SUM(amount_cents) AS gross_cents,
SUM(platform_fee_cents) AS fee_cents,
SUM(amount_cents - platform_fee_cents) AS net_cents,
SUM(refunded_amount_cents) AS refunded_cents,
COUNT(*) AS order_count
FROM orders
WHERE tenant_id = $1
AND status IN ('paid', 'partially_refunded', 'refunded')
AND created_at >= now() - INTERVAL '13 months'
GROUP BY 1
ORDER BY 1 DESC;
```

### Redis Cache Key Schema

```
report:{tenant_id}:revenue TTL 25h
report:{tenant_id}:packages TTL 25h
report:{tenant_id}:credits TTL 25h
report:{tenant_id}:customers_ltv TTL 25h
report:{tenant_id}:payout_forecast TTL 5m
platform:revenue:snapshot TTL 25h
platform:tenant_health:snapshot TTL 25h
notif:unread:{user_id} TTL 1h (invalidated on new notification)
```

### Scheduled Jobs

|Job |Schedule |Purpose |
|---------------------------|------------------------|-------------------------------------------------------------|
|`WarmTenantReportCaches` |Daily 2 AM per-tenant tz|Owner reports 1, 3, 4, 5 |
|`WarmPlatformReportCaches` |Daily 3 AM UTC |Platform reports 10, 11 |
|`ExpireSubscriptionCredits`|Daily 1 AM UTC |Writes `expiry_removal` ledger entries, fires `credits.empty`|
|`PruneOldNotifications` |Daily 4 AM UTC |Soft-deletes notifications > 90 days |
|`PruneRawWebhooks` |Daily 4 AM UTC |Hard-deletes raw webhooks > 7 days |
|`PruneDispatchedPending` |Daily 4 AM UTC |Hard-deletes `notification_pending` rows > 24h old |

-----

## Appendix — Key Design Decisions

|Decision |Choice |Rationale |
|----------------------|----------------------------------------------|--------------------------------------------------------------------------------------|
|Multi-tenancy model |Shared DB, `tenant_id` on every table |Simpler ops than DB-per-tenant; global Eloquent scope handles isolation |
|Primary keys |ULIDs for public, bigserial for internal |ULIDs are URL-safe and non-enumerable; bigserial for insert performance on log tables |
|Auth mechanism |JWT (15 min access + 30 day refresh) |Stateless API; refresh flow handles token rotation |
|Credit balance storage|Denormalized on `dogs.credit_balance` + ledger|Fast reads without aggregating ledger on every request |
|Refund credit rule |All remaining credits removed on any refund |Simplest rule; prevents partial-credit gaming |
|Notification dispatch |Immediate queue dispatch per trigger |Predictable latency; queue isolates from app performance |
|Multi-dog alerts |60-second grouping window per customer |Prevents N-dog check-in triggering N identical SMS messages |
|Package deletion |Archive only, never delete |Historical orders maintain FK integrity |
|Enum types |Native PostgreSQL enums for stable sets |Type safety at DB level; `text + CHECK` for expanding sets |
|Report caching |Nightly Redis, 25h TTL |Financial aggregations too slow for real-time; nightly is acceptable for owner reports|
|Webhook logging |Raw payload ring buffer (7 days) |Debug failed processing without re-requesting from provider |

-----

## 12. Platform Subscription Plans

> This section covers what **PawPass charges daycare businesses** — entirely separate from the credit/package system businesses use to charge their dog-owner customers.

### Overview

Every new tenant gets a **21-day free trial** with full Business-tier access — no credit card required. When the trial ends without subscribing, the tenant is permanently downgraded to the Free tier (not locked out).

### Tiers

|Feature |Free (post-trial)|Starter $29/mo|Pro $79/mo|Business $149/mo|
|-------------------------------------------|:---------------:|:------------:|:--------:|:--------------:|
|Check-in / check-out |✓ |✓ |✓ |✓ |
|Customer management (existing only) |✓ |✓ |✓ |✓ |
|Add new customers |✗ |✓ |✓ |✓ |
|Dog management (existing only) |✓ |✓ |✓ |✓ |
|Add new dogs |✗ |✓ |✓ |✓ |
|Daily roster + attendance history |✓ |✓ |✓ |✓ |
|Packages & payments |✗ |✓ |✓ |✓ |
|Stripe Connect payouts |✗ |✓ |✓ |✓ |
|Weekly/daily payout schedules |✗ |✗ |✓ |✓ |
|Staff accounts (max) |1 |2 |5 |Unlimited |
|Email notifications |✗ |✓ |✓ |✓ |
|SMS notifications (Twilio) |✗ |✗ |✓ |✓ |
|In-app notifications |✗ |✓ |✓ |✓ |
|Customer portal |✗ |✓ |✓ |✓ |
|Custom branding (logo, colour) |✗ |✗ |✓ |✓ |
|Portal PWA (installable) |✗ |✗ |✓ |✓ |
|White-label (remove PawPass badge) |✗ |✗ |✗ |✓ |
|Ops reports (attendance, roster, credits) |✗ |✓ |✓ |✓ |
|Financial reports (revenue, LTV, staff log)|✗ |✗ |✓ |✓ |
|CSV export |✗ |✗ |✓ |✓ |
|Email support |✗ |✓ |✓ |✓ |
|Priority support (< 4h SLA) |✗ |✗ |✗ |✓ |

**Annual billing:** 10 months paid = 2 months free.

### Free Tier Constraints — Adding Customers & Dogs

When a tenant is on the Free tier (`plan = 'free'`), the following hard limits apply:

- **No new customers.** `POST /api/admin/v1/customers` returns `403 PLAN_FEATURE_NOT_AVAILABLE`. Existing customer records remain fully accessible (view, edit, check-in).
- **No new dogs.** `POST /api/admin/v1/dogs` returns `403 PLAN_FEATURE_NOT_AVAILABLE`. Existing dogs remain fully accessible.
- **No customer self-registration.** `POST /api/portal/v1/auth/register` is blocked on Free tier — portal is disabled entirely.
- Staff can still edit existing customers and dogs (name, contact info, vet details, notes).
- Staff can still check in/out any existing dog.

The intent is that a business can operate day-to-day with their existing client base indefinitely, but cannot grow their roster until they subscribe. This creates natural upgrade pressure as the business acquires new dog-owner customers.

**PlanGate config additions:**

```php
'free' => [
// existing entries...
'add_customers' => false, // POST /customers blocked
'add_dogs' => false, // POST /dogs blocked
],
'starter' => [
// existing entries...
'add_customers' => true,
'add_dogs' => true,
],
// pro and business inherit from starter
```

**UI behaviour:** The “Add Customer” and “Add Dog” buttons are shown with a lock icon on Free tier, not hidden. Clicking shows an upgrade prompt: *“Adding new customers requires a Starter plan or above.”* This keeps upgrade conversion in the user’s path rather than hiding it. Starter $290/yr · Pro $790/yr · Business $1,490/yr.

### Trial Lifecycle

```
email_verified → [trialing] ──subscribes──→ [active]
│ │
21 days lapse cancels/fails
│ │
[free_tier] ←──────────────────────
```

`tenant_status` enum extended: `trialing | free_tier | active | past_due | suspended | cancelled`

### Trial Expiry Notifications

|When |Type |Channels |
|---------|---------------------|--------------|
|T−7 days |`trial.expiring_soon`|Email |
|T−3 days |`trial.expiring_soon`|Email + In-app|
|T−1 day |`trial.expiring_soon`|Email + In-app|
|T=0 |`trial.expired` |Email + In-app|
|T+7 days |`trial.upgrade_nudge`|Email |
|T+30 days|`trial.upgrade_nudge`|Email |

### Billing Architecture

Two separate Stripe contexts — never mix them:

|Context |Purpose |
|------------------|--------------------------------------------------------------------|
|**Stripe Connect**|Businesses collect payments from dog-owner customers (existing spec)|
|**Stripe Billing**|PawPass collects subscription fees from businesses (this section) |

- Platform Stripe Products: one per tier × two prices (monthly + annual)
- `platform_stripe_customer_id` and `platform_stripe_sub_id` stored on `tenants`
- Payment method via Stripe Elements on the in-app upgrade page

### New `tenants` Columns

```sql
trial_started_at timestamptz -- set on email verify
trial_ends_at timestamptz -- trial_started_at + 21 days
plan tenant_plan -- free|starter|pro|business DEFAULT 'free'
plan_billing_cycle text -- monthly|annual NULL on free
platform_stripe_customer_id text
platform_stripe_sub_id text
plan_current_period_end timestamptz
plan_cancel_at_period_end boolean DEFAULT false
plan_past_due_since timestamptz -- NULL if current

CREATE TYPE tenant_plan AS ENUM ('free','starter','pro','business');
```

### Feature Gating

All gates live in `config/plan_features.php` — never scattered across controllers.

```php
// Usage
PlanGate::allows('sms_notifications'); // bool
PlanGate::allowsOrFail('reports_financial'); // throws PlanGateException → 403
PlanGate::staffLimit(); // int

// Route middleware
Route::middleware(['auth:api', 'plan:sms_notifications'])->group(...);
// Returns 403 PLAN_FEATURE_NOT_AVAILABLE when feature is not on current plan
```

### Upgrade / Downgrade Rules

- **Upgrade:** Immediate. Stripe proration via `always_invoice`. Features unlock on `invoice.payment_succeeded`.
- **Downgrade:** Effective end of billing period. Stripe schedules it; webhook fires on period end.
- **Data on downgrade:** All gated data preserved but made inaccessible until re-upgrade.
- **Staff overlimit on downgrade:** Owner notified 7 days before. On effective date, excess staff soft-suspended (not deleted).

### Dunning Schedule

|Day|Action |
|---|---------------------------------------------------------------------|
|0 |Set `plan_past_due_since`. Show persistent red banner in dashboard. |
|3 |Stripe retry 1. Fire reminder notification if still failing. |
|7 |Stripe retry 2. Fire urgent notification. |
|14 |Stripe retry 3. Begin grace period if still failing. |
|21 |Downgrade to `free_tier`. Fire `subscription.cancelled` notification.|

Tenant remains fully functional on paid plan throughout the dunning period.

### New Billing API Endpoints

All under `/api/admin/v1/billing/*` (owner only — no PlanGate, billing always accessible):

|Method|Path |Description |
|------|---------------------|----------------------------------------------|
|`GET` |`/billing` |Current plan, period end, past_due status |
|`POST`|`/billing/subscribe` |Create subscription |
|`POST`|`/billing/upgrade` |Change plan |
|`POST`|`/billing/cancel` |Cancel at period end |
|`GET` |`/billing/invoices` |Platform invoice history from Stripe |
|`GET` |`/billing/portal-url`|Stripe Customer Portal URL for card management|

### New Migrations

```
26 alter_tenants_add_plan_columns
27 create_platform_subscription_events_table
28 alter_tenant_status_add_trialing_free_tier_past_due
29 create_tenant_plan_enum
```

### Spec Delta Summary

Changes required in other parts of this spec when implementing platform subscriptions:

- **Part 4 (Tenant Architecture):** Extended `tenant_status`, 9 new `tenants` columns, new `platform_subscription_events` table, `TenantMiddleware` handles `past_due` and `free_tier` states.
- **Part 6 (Notifications):** 5 new critical notification types: `trial.expiring_soon`, `trial.expired`, `trial.upgrade_nudge`, `subscription.plan_changed`, `subscription.payment_failed_platform`. Fire via platform Postmark sender, not tenant’s.
- **Part 7 (Admin Dashboard):** Settings > Billing tab, past_due red banner, trial countdown amber banner, locked feature icons with upgrade tooltips.
- **Part 9 (API):** New `/billing/*` route group, new error code `PLAN_FEATURE_NOT_AVAILABLE`.
- **Part 10 (Reporting):** Platform Revenue adds MRR by tier + trial conversion rate. New Subscription Funnel report. Tenant Health shows plan and days-since-trial for free_tier tenants.

