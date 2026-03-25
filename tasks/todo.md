# Phase: Checkout Billing — Off-Session Charge on Actual Stay

Formula: `balance = (actual_nights × nightly_rate_cents) + addons_total − deposit_amount_cents`

---

## Step 1 — Migration: checkout columns (TDD)

- [ ] Write failing test for checkout columns
- [ ] Create migration `2026_03_24_000004_add_checkout_columns_to_reservations.php`
- [ ] Update `Reservation` fillable/casts + `ReservationResource`
  - Verification: migration runs, model/resource include columns

---

## Step 2 — processCheckout() + route (TDD)

- [ ] Write failing tests (balance charge, no saved card, zero balance, invalid date, extended stay)
- [ ] Add `POST /admin/boarding/reservations/{reservation}/checkout` route
- [ ] Implement `BoardingController@processCheckout`
- [ ] Update `showReservation()` to pass `savedCard` prop
  - Verification: all tests pass

---

## Step 3 — ReservationShow.vue: checkout form

- [ ] Remove `checked_in` from `ACTION_MAP`
- [ ] Add checkout form (date input + live breakdown + charge button)
- [ ] Add checkout summary to stay details when checked_out
  - Verification: `npm run build` passes

---

## Step 4 — Final Verification

- [ ] `./vendor/bin/sail artisan test` — 882+ tests pass
- [ ] `npm run build` — no TS errors
