# Phase 18: Refactor Billing — Event-Driven Auto-Replenish

Replace the Stripe Subscription (interval-based) model entirely with event-driven PaymentIntents.
Instead of charging every N days, the system charges when credits reach zero or an unlimited pass expires.
The confusing dual toggle (subscription + recurring) is replaced with a single "Auto-replenish" checkbox.

## Model Overview

- All packages become `one_time` or `unlimited` — the `subscription` package type is removed.
- No more Stripe Subscriptions or SetupIntents for customer packages.
- A package can be marked `is_auto_replenish_eligible` (owner flag).
- At purchase time, customer can opt into **auto-replenish** for a dog. This saves their card and sets `dog.auto_replenish_enabled = true` + `dog.auto_replenish_package_id`.
- Trigger A — credits hit 0: `DogCreditService::dispatchCreditAlert()` fires `ProcessAutoReplenishJob`.
- Trigger B — unlimited pass expires: `ExpireSubscriptionCredits` job fires `ProcessAutoReplenishJob`.
- `AutoReplenishService::trigger()` creates an off-session PaymentIntent using the saved card.
- Existing `payment_intent.succeeded` webhook handles issuing credits — unchanged.
- On PI success: `auto_replenish.succeeded` notification fired (new type, critical).
- On PI failure: `auto_replenish.failed` notification fired (new type, critical).

### New Notification Types

| Type | Critical | Description |
|---|---|---|
| `auto_replenish.succeeded` | ✓ | Card charged successfully, credits topped up |
| `auto_replenish.failed` | ✓ | Card charge failed, credits not issued — action required |

Both are critical (always fire regardless of tenant settings) and are dispatched via the existing
`NotificationService::dispatch()` path with `dog_id` + `order_id` (or `pi_id`) in the payload.

---

## Step 1 — Migrations

- [ ] `alter_packages_billing_refactor`: drop `stripe_price_id_monthly`, `is_recurring_enabled`,
  `recurring_interval_days`, `stripe_price_id_recurring`; add `is_auto_replenish_eligible bool default false`
  - Verification: `php artisan migrate` runs cleanly; columns absent from schema

- [ ] `alter_dogs_add_auto_replenish`: add `auto_replenish_enabled bool default false`,
  `auto_replenish_package_id char(26) nullable` FK to `packages(id)`
  - Verification: migration runs; Dog factory picks up new fields without error

---

## Step 2 — Model Updates

- [ ] `Package`: remove dropped columns from `$fillable` + casts; add `is_auto_replenish_eligible`
- [ ] `Dog`: add `auto_replenish_enabled`, `auto_replenish_package_id` to `$fillable` + casts
  - Verification: existing model tests still pass

---

## Step 3 — AutoReplenishService (TDD)

- [ ] Write failing unit tests for `AutoReplenishService::trigger(Dog $dog)`:
  - Skips when `auto_replenish_enabled = false`
  - Skips when no saved payment method on customer
  - Skips when `auto_replenish_package_id` is null
  - Creates `Order` + off-session `PaymentIntent` when all conditions met
  - Handles Stripe exception (logs, notifies customer)
  - Verification: tests fail for correct reasons

- [ ] Implement `app/Services/AutoReplenishService.php`:
  - Creates order record (status=pending), calls `StripeService::createPaymentIntent()` with
    `confirm=true`, `off_session=true`, and `payment_method=$customer->stripe_payment_method_id`
  - Existing `payment_intent.succeeded` webhook issues credits automatically
  - Verification: all AutoReplenishService unit tests pass

---

## Step 4 — ProcessAutoReplenishJob (TDD)

- [ ] Write failing test: job calls `AutoReplenishService::trigger()` for the given dog
- [ ] Implement `app/Jobs/ProcessAutoReplenishJob.php` (queued, `$tries = 1`)
  - Verification: test passes

---

## Step 5 — Trigger A: Credits Empty

- [ ] Update `DogCreditService::dispatchCreditAlert()`:
  - When type is `credits.empty`: check `$dog->auto_replenish_enabled` →
    if true, dispatch `ProcessAutoReplenishJob::dispatch($dog->id)`
  - Verification: new unit test — zero balance + auto_replenish → job dispatched
  - Verification: existing dispatchCreditAlert tests still pass

---

## Step 6 — Trigger B: Unlimited Pass Expired

- [ ] Update `ExpireSubscriptionCredits` job (unlimited pass loop):
  - After `expireUnlimitedPass($dog)`, if `$dog->auto_replenish_enabled` → dispatch `ProcessAutoReplenishJob`
  - Verification: new unit test for this path; existing job tests still pass

---

## Step 7 — Webhook Controller: Remove Subscription Handlers + Auto-Replenish Notifications

- [ ] Remove `handleSetupIntentSucceeded` handler (and `setup_intent.succeeded` match arm)
- [ ] Remove `handleInvoicePaymentSucceeded` (and `invoice.payment_succeeded` match arm)
- [ ] Remove `handleInvoicePaymentFailed` (and `invoice.payment_failed` match arm)
- [ ] Remove `handleSubscriptionDeleted` (and `customer.subscription.deleted` match arm)
- [ ] In `handlePaymentIntentSucceeded`: after issuing credits, check PI metadata for
    `auto_replenish=true` — if set, dispatch `auto_replenish.succeeded` notification
    (in addition to the existing `payment.confirmed`)
- [ ] In `handlePaymentIntentFailed`: check PI metadata for `auto_replenish=true` —
    if set, dispatch `auto_replenish.failed` notification to customer
  - Verification: update StripeWebhookController tests; confirm removed event types return `ok`;
    confirm auto_replenish notification dispatched on success/failure

---

## Step 8 — PurchaseController: Remove Subscription / Recurring Branches

- [ ] Remove `billing_mode = 'subscription'` branch (SetupIntent + Subscription::create)
- [ ] Remove `billing_mode = 'recurring'` branch (SetupIntent + fast path)
- [ ] Remove unused imports (`Subscription`, `PlatformConfig`)
- [ ] Add `auto_replenish` boolean param validation in `store()`
- [ ] In `confirm()`: if `auto_replenish=true`, set `dog.auto_replenish_enabled=true` and
    `dog.auto_replenish_package_id = $order->package_id` for each `OrderDog`
  - Verification: update / add PurchaseController tests; all old subscription tests removed or updated

---

## Step 9 — SyncPackageToStripe Job: Simplify

- [ ] Remove recurring price creation (`stripe_price_id_recurring`) from `create()` and `update()`
- [ ] Remove archiving of recurring prices
- [ ] Remove monthly price creation (`stripe_price_id_monthly`) from `create()` and `update()`
  - Verification: SyncPackageToStripeTest updated; still creates product + one_time price

---

## Step 10 — Admin Package Controller & Validation

- [ ] `UpdatePackageRequest` / `StorePackageRequest`: remove `is_recurring_enabled`,
    `recurring_interval_days` fields; add `is_auto_replenish_eligible`
- [ ] `PackageResource`: remove dropped fields; expose `is_auto_replenish_eligible`
- [ ] `Admin/V1/PackageController`: update to save `is_auto_replenish_eligible`
- [ ] `Web/Admin/PackageController`: same
- [ ] `packages` enum: remove `subscription` type (Postgres migration in Step 1 above)
  - Verification: admin package CRUD tests updated and passing

---

## Step 11 — Admin Package Edit UI

- [ ] `Edit.vue`: remove `is_recurring_enabled` + `recurring_interval_days` fields;
    add "Allow auto-replenish" checkbox for `is_auto_replenish_eligible`
  - Verification: `npm run build` succeeds

---

## Step 12 — Purchase.vue: Single Recurring Toggle

- [ ] Remove "Billing" toggle section (`has_monthly_price` / subscription mode)
- [ ] Remove "Recurring" toggle section (`is_recurring_enabled` / recurring mode)
- [ ] Replace with a single "Auto-replenish" checkbox:
    shown when `selectedPackage.is_auto_replenish_eligible && recurringCheckoutEnabled`
    text: "Auto-replenish when credits run out · card saved securely"
    checking it auto-sets `saveCard = true`
- [ ] Remove `billingMode` state (always one_time); simplify `activeDogIds`
    (remove subscription/recurring special cases — always use single dropdown for single-dog packages)
- [ ] Remove `fast` path response handling (no more card-on-file subscriptions)
- [ ] Update `purchase()` function: always `confirmCardPayment`; send `auto_replenish` flag
- [ ] Update button label: always "Pay $X.XX"; remove subscription/recurring variants
- [ ] Update success message: always "Payment successful! Credits will appear shortly."
- [ ] Update `PurchasePackage` type: remove `has_monthly_price`, `is_recurring_enabled`,
    `recurring_interval_days`; add `is_auto_replenish_eligible`
  - Verification: `npm run build` succeeds; no TS errors

---

## Step 13 — PurchaseController::index() Props Update

- [ ] Remove `is_recurring_enabled`, `recurring_interval_days`, `billing_interval`,
    `has_monthly_price` from package props; add `is_auto_replenish_eligible`
- [ ] Remove `recurring_checkout_enabled` prop (or repurpose as `auto_replenish_enabled` feature flag)
- [ ] Remove `saved_card` prop from index if no longer used for subscription fast path
    (keep only if still used for card-on-file display for one-time purchases with save_card)
  - Verification: controller test updated

---

## Step 14 — Final Verification

- [ ] `composer test` — full suite passes (0 failures)
- [ ] `npm run build` — no TS errors, no build failures
- [ ] Confirm no references to `billing_mode=subscription`, `billing_mode=recurring`,
    `stripe_price_id_monthly`, `stripe_price_id_recurring`, `is_recurring_enabled`,
    `recurring_interval_days` remain in application code (only in migrations for drop)

---

## Review

### Summary of Changes

- **Removed** Stripe Subscription infrastructure: no more SetupIntents, Stripe Subscriptions, invoice webhooks (`invoice.payment_succeeded`, `invoice.payment_failed`, `setup_intent.succeeded`, `customer.subscription.deleted`).
- **Removed** `subscription` package type; all packages are `one_time` or `unlimited`.
- **Removed** `is_recurring_enabled`, `recurring_interval_days`, `stripe_price_id_monthly`, `stripe_price_id_recurring` from `packages` table and all related code.
- **Added** `is_auto_replenish_eligible bool` to `packages` (owner flag per-package).
- **Added** `auto_replenish_enabled bool` + `auto_replenish_package_id` to `dogs`.
- **Added** `AutoReplenishService` — creates an off-session PaymentIntent (`confirm=true`, `off_session=true`) using customer's saved card when auto-replenish triggers.
- **Added** `ProcessAutoReplenishJob` — queued, `$tries=1`, dispatches `AutoReplenishService::trigger()`.
- **Added** trigger A: `DogCreditService::dispatchCreditAlert()` dispatches job when `credits.empty` and `auto_replenish_enabled=true`.
- **Added** trigger B: `ExpireSubscriptionCredits` dispatches job after unlimited pass expires if `auto_replenish_enabled=true`.
- **Added** two critical notification types: `auto_replenish.succeeded` and `auto_replenish.failed`.
- **Updated** webhook handler: `payment_intent.succeeded` dispatches `auto_replenish.succeeded` when `metadata.auto_replenish=true`; `payment_intent.payment_failed` dispatches `auto_replenish.failed`.
- **Updated** `PurchaseController`: all purchases are one-time PaymentIntent; `confirm()` sets dog auto-replenish fields if opted in.
- **Updated** `SyncPackageToStripe`: creates single one-time price only (no monthly/recurring prices).
- **Updated** `Purchase.vue`: single "Auto-replenish when credits run out" checkbox; removed dual billing toggle UI.
- **Updated** `Admin/Packages/Edit.vue`: "Allow auto-replenish" checkbox replaces recurring fields.

### Tests Added or Updated

- **New** `tests/Unit/Services/AutoReplenishServiceTest.php` — 6 tests covering skip conditions, successful PI creation, and Stripe exception handling.
- **New** `tests/Unit/Jobs/ProcessAutoReplenishJobTest.php` — 2 tests: calls service for valid dog, skips for unknown dog ID.

### Build Status

- **PHP syntax:** All new/modified PHP files pass `php -l` syntax check (no errors).
- **JS Build:** `npm run build` completed successfully (✓ 4.52s, no TypeScript errors).
- **Unit tests (DB-independent):** `Tests\Unit\ExampleTest` passes. New unit tests require PostgreSQL (same pre-existing limitation as `HasUlidTest`, `BelongsToTenantTest`, `JwtServiceTest` in this environment).

### Notes

- `subscriptions` table is retained for FK integrity on `credit_ledger.subscription_id` (historical rows), but no new rows are inserted. A future cleanup migration can drop the table once credit_ledger rows are purged.
- The `stripe_price_id` (one-time) and `stripe_product_id` columns on `packages` are kept — they are used for Stripe dashboard product tracking, not for the payment flow.
- Off-session PaymentIntents use `confirm: true` + `off_session: true` + `error_on_requires_action: true` to handle 3DS-required cards gracefully (they fail immediately rather than entering a limbo state requiring customer action).
- The existing `payment_intent.succeeded` webhook issues credits for auto-replenish orders automatically, since the PI `metadata` includes `order_id` just like manual purchases.
