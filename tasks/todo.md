# Phase: Boarding Deposit / Hold Payments

Stripe PaymentIntent with `capture_method: manual` created at reservation booking. Hold captured at check-in, released on cancel.

---

## Step 1 — Migration: Add deposit columns (TDD)

- [x] Write test asserting new columns exist
- [x] Create migration `2026_03_24_000003_add_deposit_columns_to_reservations.php`
- [x] Update `Reservation::$fillable` and casts
  - Verification: migration runs, model accepts fields ✓

---

## Step 2 — StripeService: Hold / Capture / Cancel (TDD)

- [x] Write unit tests for three new methods
- [x] Implement `createHoldPaymentIntent`, `capturePaymentIntent`, `cancelPaymentIntent`
  - Verification: unit tests pass ✓

---

## Step 3 — Portal store: create hold on booking (TDD)

- [x] Write failing tests (with/without deposit, stores stripe_pi_id)
- [x] Add `deposit_amount_cents` to `StoreReservationRequest`
- [x] Update `Portal\V1\ReservationController@store` to create hold PI and return `client_secret`
  - Verification: tests pass ✓

---

## Step 4 — Portal cancel: release hold (TDD)

- [x] Write failing test: cancel with `stripe_pi_id` calls `cancelPaymentIntent`
- [x] Update `Portal\V1\ReservationController@cancel`
  - Verification: tests pass ✓

---

## Step 5 — Admin update: capture at check-in (TDD)

- [x] Write failing tests (capture on checked_in; cancel PI on cancelled)
- [x] Inject `StripeService` into `Admin\V1\ReservationController`
- [x] Add capture/cancel logic in `update()`
  - Verification: tests pass ✓

---

## Step 6 — Webhook: authorize → confirm (TDD)

- [x] Write failing test for `payment_intent.amount_capturable_updated`
- [x] Add handler to `StripeWebhookController`
  - Verification: tests pass ✓

---

## Step 7 — Final Verification

- [x] `./vendor/bin/sail artisan test` — 861 tests pass
- [x] `npm run build` — no TS errors

---

## Review

### Summary of Changes
- Migration `2026_03_24_000003`: added `deposit_amount_cents`, `stripe_pi_id`, `deposit_captured_at`, `deposit_refunded_at` to `reservations`
- `Reservation` model: updated `$fillable` and `casts`
- `StripeService`: added `createHoldPaymentIntent`, `capturePaymentIntent`, `cancelPaymentIntent`
- `Portal\V1\ReservationController@store`: creates hold PI when `deposit_amount_cents` provided; returns `client_secret`
- `Portal\V1\ReservationController@cancel`: cancels PI and sets `deposit_refunded_at` when uncaptured hold exists
- `Admin\V1\ReservationController@update`: captures hold on `checked_in`; releases hold on `cancelled`
- `StripeWebhookController`: handles `payment_intent.amount_capturable_updated` → sets reservation `status: confirmed`

### Tests Added or Updated
- `tests/Feature/Portal/ReservationControllerTest.php` — 3 new deposit/hold tests + 1 schema test
- `tests/Feature/Admin/ReservationControllerTest.php` — 2 new capture/release tests
- `tests/Feature/Webhooks/StripeDepositWebhookTest.php` — new file, 3 tests
- `tests/Unit/Services/StripeServiceTest.php` — 3 new unit tests

### Build Status
- Tests: 861 passed
- Build: Successful

### Notes
- Deposit is optional — if `deposit_amount_cents` absent or no `stripe_account_id`, no PI is created
- Hold expires after 7 days (Stripe default); no auto-expiry handling yet
- Frontend must call `stripe.confirmCardPayment(client_secret)` when `client_secret` is non-null
