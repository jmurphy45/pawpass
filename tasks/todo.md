# Phase: Checkout Billing — Off-Session Charge on Actual Stay

Formula: `balance = (actual_nights × nightly_rate_cents) + addons_total − deposit_amount_cents`

---

## Step 1 — Migration: checkout columns (TDD)

- [x] Write failing test for checkout columns
- [x] Create migration `2026_03_24_000004_add_checkout_columns_to_reservations.php`
- [x] Update `Reservation` fillable/casts + `ReservationResource`
  - Verification: migration runs, model/resource include columns

---

## Step 2 — processCheckout() + route (TDD)

- [x] Write failing tests (balance charge, no saved card, zero balance, invalid date, extended stay)
- [x] Add `POST /admin/boarding/reservations/{reservation}/checkout` route
- [x] Implement `BoardingController@processCheckout`
- [x] Update `showReservation()` to pass `savedCard` prop
  - Verification: all tests pass

---

## Step 3 — ReservationShow.vue: checkout form

- [x] Remove `checked_in` from `ACTION_MAP`
- [x] Add checkout form (date input + live breakdown + charge button)
- [x] Add checkout summary to stay details when checked_out
  - Verification: `npm run build` passes

---

## Step 4 — Final Verification

- [x] `./vendor/bin/sail artisan test` — 895 tests pass
- [x] `npm run build` — no TS errors

---

## Review

### Summary of Changes
- Migration adds `actual_checkout_at`, `checkout_pi_id`, `checkout_charge_cents` to `reservations`
- `BoardingController@processCheckout` calculates balance (nights × rate + addons − deposit), fires off-session Stripe charge if card on file, records `OrderPayment` of type `balance`, transitions reservation to `checked_out`
- `ReservationShow.vue` shows checkout form for `checked_in` reservations with live balance breakdown and displays actual checkout summary once complete

### Tests Added or Updated
- `tests/Feature/Web/Admin/BoardingControllerTest.php` — 6 new processCheckout tests (balance charge, no card, zero balance, invalid date, missing date, extended stay)

### Build Status
- Tests: 895 passing
- Build: Successful (no TS errors)

### Notes
- Test DB needed `migrate:fresh --env=testing` after new migrations were added
- `checkout_pi_id` / `checkout_charge_cents` columns exist on reservations table but the controller now stores payment data in `order_payments` — the columns are present but unused; can be dropped in a future cleanup migration
