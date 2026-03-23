# Phase: Customer Portal — Boarding Reservation Booking

Self-service boarding booking for the customer portal. Customers can browse available units, request reservations for their dogs, view status, and cancel pending bookings. Gated to `kennel` / `hybrid` tenants.

---

## Step 1 — Portal API: List & Show Reservations (TDD)

- [ ] Write failing tests for index (own reservations only) and show (404 cross-customer)
- [ ] Implement `Portal\V1\ReservationController@index` + `@show`
- [ ] Register routes in `routes/api.php`
  - Verification: tests pass

---

## Step 2 — Portal API: Create Reservation (TDD)

- [ ] Write failing tests: own dog ok; other dog 403; unit conflict 409; vaccination 422
- [ ] Create `App\Http\Requests\Portal\StoreReservationRequest`
- [ ] Implement `Portal\V1\ReservationController@store`
  - Verification: tests pass

---

## Step 3 — Portal API: Cancel Reservation (TDD)

- [ ] Write failing tests: cancel pending ok; cancel confirmed 422; cross-customer 403
- [ ] Implement `Portal\V1\ReservationController@cancel` (`PATCH /{id}/cancel`)
  - Verification: tests pass

---

## Step 4 — Portal API: Available Units (TDD)

- [ ] Write failing test: returns active units not conflicting with date range; 403 for daycare
- [ ] Implement `Portal\V1\KennelUnitController@available`
- [ ] Register route
  - Verification: tests pass

---

## Step 5 — Web Portal Controller & Routes (TDD)

- [ ] Write failing Inertia tests for index, create, show
- [ ] Create `Web\Portal\BoardingController`
- [ ] Add `/my/boarding/*` routes
  - Verification: tests pass

---

## Step 6 — Vue Pages

- [ ] `Portal/Boarding/Index.vue` — list with status badges, empty state, New Reservation button
- [ ] `Portal/Boarding/Create.vue` — date range → unit picker → dog dropdown → care instructions
- [ ] `Portal/Boarding/Show.vue` — detail + report cards (read-only) + cancel button (pending only)
  - Verification: `npm run build` passes

---

## Step 7 — PortalLayout Navigation

- [ ] Add conditional "Boarding" nav link (desktop + mobile) for kennel/hybrid tenants
  - Verification: `npm run build` passes

---

## Step 8 — Final Verification

- [ ] `./vendor/bin/sail artisan test` — full suite passes
- [ ] `npm run build` — no TS errors
