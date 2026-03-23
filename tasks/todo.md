# Phase: Customer Portal — Boarding Reservation Booking

Self-service boarding booking for the customer portal. Customers can browse available units, request reservations for their dogs, view status, and cancel pending bookings. Gated to `kennel` / `hybrid` tenants.

---

## Step 1 — Portal API: List & Show Reservations (TDD)

- [x] Write failing tests for index (own reservations only) and show (404 cross-customer)
- [x] Implement `Portal\V1\ReservationController@index` + `@show`
- [x] Register routes in `routes/api.php`
  - Verification: tests pass

---

## Step 2 — Portal API: Create Reservation (TDD)

- [x] Write failing tests: own dog ok; other dog 403; unit conflict 409; vaccination 422
- [x] Create `App\Http\Requests\Portal\StoreReservationRequest`
- [x] Implement `Portal\V1\ReservationController@store`
  - Verification: tests pass

---

## Step 3 — Portal API: Cancel Reservation (TDD)

- [x] Write failing tests: cancel pending ok; cancel confirmed 422; cross-customer 403
- [x] Implement `Portal\V1\ReservationController@cancel` (`PATCH /{id}/cancel`)
  - Verification: tests pass

---

## Step 4 — Portal API: Available Units (TDD)

- [x] Write failing test: returns active units not conflicting with date range; 403 for daycare
- [x] Implement `Portal\V1\KennelUnitController@available`
- [x] Register route
  - Verification: tests pass

---

## Step 5 — Web Portal Controller & Routes (TDD)

- [x] Write failing Inertia tests for index, create, show
- [x] Create `Web\Portal\BoardingController`
- [x] Add `/my/boarding/*` routes
  - Verification: tests pass

---

## Step 6 — Vue Pages

- [x] `Portal/Boarding/Index.vue` — list with status badges, empty state, New Reservation button
- [x] `Portal/Boarding/Create.vue` — date range → unit picker → dog dropdown → care instructions
- [x] `Portal/Boarding/Show.vue` — detail + report cards (read-only) + cancel button (pending only)
  - Verification: `npm run build` passes

---

## Step 7 — PortalLayout Navigation

- [x] Add conditional "Boarding" nav link (desktop + mobile) for kennel/hybrid tenants
  - Verification: `npm run build` passes

---

## Step 8 — Final Verification

- [x] `./vendor/bin/sail artisan test` — 849 tests pass
- [x] `npm run build` — no TS errors
