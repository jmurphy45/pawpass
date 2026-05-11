# Backend — `app/`

Loaded when editing PHP files under `app/`. See root `CLAUDE.md` for commands and project overview.

## Multi-Tenancy Implementation

- `current.tenant.id` bound in `AppServiceProvider`; defaults to `null` for platform routes
- `BelongsToTenant` trait: auto-sets `tenant_id` on `creating`, applies `TenantScope` (WHERE tenant_id = ?) when binding is non-null
- Escape hatch: `Dog::allTenants()->get()` — static method on the trait
- **SubstituteBindings runs AFTER tenant middleware** — see `app/Http/Controllers/CLAUDE.md`
- Test setup and cross-tenant factory isolation → see `tests/CLAUDE.md`

## Credit Ledger

`credit_ledger` is **append-only** — enforced by a PostgreSQL RULE. **No UPDATE or DELETE ever.**

`dogs.credit_balance` is a denormalized fast-read column updated after each ledger write.

All credit operations go through `DogCreditService` (`app/Services/DogCreditService.php`):
- `issueFromOrder` — purchase or subscription renewal
- `deductForAttendance` — check-in deduction
- `removeAllOnRefund` — removes ALL remaining credits (regardless of refund amount)
- `addGoodwill` — manual addition (requires `note`)
- `applyCorrection` — manual add/remove (requires `note`)
- `transfer` — dog-to-dog within same customer account (requires `note`)
- `expireCredits` — subscription expiry job

Ledger entry types: `purchase`, `subscription`, `deduction`, `refund`, `goodwill`, `correction_add`, `correction_remove`, `expiry_removal`, `transfer_in`, `transfer_out`.

`CreditLedger` model: `public const UPDATED_AT = null` — no `updated_at` column. Table name: `credit_ledger`.

## Stripe Architecture (Connect Express)

**All `StripeService` calls pass** `['stripe_account' => $stripeAccountId]` as the SDK option. Customers (`cus_*`) live on the **connected account**, not the platform account.

- **One-time:** `POST /api/portal/v1/orders` → PaymentIntent → `payment_intent.succeeded` webhook → `DogCreditService::issueFromOrder()`
- **Subscription:** SetupIntent → `invoice.payment_succeeded` → `subscription` ledger entry
- **Platform fee:** 5% default, snapshotted at purchase time on `orders.platform_fee_pct`
- **Platform billing** (tenant subscriptions): `StripeBillingService` uses `STRIPE_BILLING_SECRET` — never mix with `StripeService`
- Webhook endpoints: `platform.pawpass.com/webhooks/*` with HMAC signature verification

## Notification System

`NotificationService::dispatch()` → queues `SendNotificationJob` on `notifications` queue.

Channels: email (Resend), SMS (Twilio), in-app (database — always on), webpush.

- `credits.low` and `credits.empty`: **60-second grouping window** consolidates multi-dog alerts per customer
- `dogs.credits_alert_sent_at`: 24-hour dedup per dog
- Critical types (`payment.confirmed`, `credits.empty`, auth events) always fire regardless of tenant settings
- `customer.user_id` can be null — always null-guard before calling `NotificationService`

