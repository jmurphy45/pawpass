# Phase: Admin Reservation Management — Status Actions & Payment Details

---

## Step 1 — Reservation state machine ✅

- [x] Unit tests for `allowedTransitions`, `canTransitionTo`, `transitionTo`
- [x] Added to `Reservation` model: `TRANSITIONS` const + 3 methods
  - Side-effects: `cancelled_at`/`cancelled_by` set on cancel transition

---

## Step 2 — Add deposit fields to ReservationResource ✅

- [x] Added `deposit_amount_cents`, `stripe_pi_id`, `deposit_captured_at`, `deposit_refunded_at`

---

## Step 3 — Web route + BoardingController@updateReservation ✅

- [x] `PATCH /admin/boarding/reservations/{reservation}` → `admin.boarding.reservations.update`
- [x] `updateReservation()` uses state machine, handles Stripe, returns redirect with flash
- [x] `Admin\V1\ReservationController@update` refactored to use `transitionTo()`

---

## Step 4 — ReservationShow.vue ✅

- [x] Status action buttons (Confirm, Check In, Check Out, Cancel) with inline confirmation
- [x] Payment & Deposit sidebar card (amount, status badge, timestamps, Stripe reference)

---

## Step 5 — Final Verification ✅

- [x] 882 tests pass
- [x] `npm run build` — clean

---

## Review

### Summary of Changes
- `Reservation` model: `TRANSITIONS` const, `allowedTransitions()`, `canTransitionTo()`, `transitionTo()` — single source of truth for status transitions
- `ReservationResource`: added 4 deposit fields
- `routes/web.php`: added `PATCH /admin/boarding/reservations/{reservation}`
- `BoardingController`: injected `StripeService`, added `updateReservation()` method
- `Admin\V1\ReservationController@update`: refactored to use `transitionTo()`, rejects invalid transitions with 422
- `ReservationShow.vue`: status action buttons, inline confirmation dialogs, Payment & Deposit sidebar card

### Tests Added or Updated
- `tests/Unit/Models/ReservationStateMachineTest.php` — 13 new tests
- `tests/Feature/Admin/ReservationControllerTest.php` — 1 new resource field test
- `tests/Feature/Web/Admin/BoardingControllerTest.php` — 7 new status action tests

### Build Status
- Tests: 882 passed
- Build: Successful

### Notes
- `btn-danger` CSS class referenced in Vue — verify it exists in Tailwind config if buttons appear unstyled
