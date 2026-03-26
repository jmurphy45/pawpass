# Phase: Add-on Services — Boarding + Daycare with Billing

---

## Step 1 — Migration: attendance_id on orders

- [x] New migration `2026_03_25_000008_add_attendance_id_to_orders.php`
- [x] Update `Order` model: `attendance_id` fillable + `attendance()` BelongsTo
  - Verification: migration runs on both main and test DB

---

## Step 2 — Services Management Page (Owner-Only)

- [x] Write failing tests in `tests/Feature/Web/Admin/ServicesControllerTest.php`
- [x] Create `app/Http/Controllers/Web/Admin/ServicesController.php` (index/store/update/destroy)
- [x] Add routes to `routes/web.php` (`admin.services.*`)
- [x] Create `resources/js/Pages/Admin/Services/Index.vue` (table + inline create/edit form)
- [x] Add "Services" nav link to `AdminLayout.vue` (sparkles icon, after Packages)
  - Verification: 9 tests pass

---

## Step 3 — Delete Boarding Reservation Addon

- [x] Write failing tests (destroy allowed, 409 when checked_out)
- [x] Add `BoardingController::destroyAddon`
- [x] Add web route `DELETE /boarding/reservations/{reservation}/addons/{addon}`
- [x] Add remove button (×) to `ReservationShow.vue` addon list
  - Verification: 2 tests pass

---

## Step 4 — Roster Inline Addon UI + Billing

- [x] Write failing tests in `tests/Feature/Web/Admin/RosterControllerTest.php`
- [x] Update `RosterController::index` — include `attendance_id`, `attendance_addons`, `addonTypes` prop
- [x] Add `RosterController::chargeAttendanceAddons` private helper
- [x] Update `RosterController::checkout` — charge addons at checkout
- [x] Add `RosterController::storeAttendanceAddon` — save addon; charge immediately if already checked out
- [x] Add `RosterController::destroyAttendanceAddon` — guard: 409 if order already exists
- [x] Add routes: `POST/DELETE /roster/attendances/{attendance}/addons`
- [x] Update `resources/js/Pages/Admin/Roster/Index.vue` — expandable inline addon panel per dog
  - Verification: 7 tests pass

---

## Final Verification

- [x] `./vendor/bin/sail artisan test` — 913 tests pass (up from 895)
- [x] `npm run build` — no TS errors, built in 4s

---

## Review

### Summary of Changes
- Migration: `attendance_id` (nullable FK) added to `orders` for daycare addon billing traceability
- New `ServicesController` (web) + `Admin/Services/Index.vue` — owner-only CRUD for addon type catalog with context badges (both/boarding/daycare)
- `AdminLayout.vue` — "Services" nav link (sparkles icon) in owner section
- `BoardingController::destroyAddon` — delete reservation addon, guarded for checked_out status
- `ReservationShow.vue` — × remove button per addon (hidden when reservation is checked_out)
- `RosterController` — `chargeAttendanceAddons` helper creates Order + charges Stripe on checkout; `storeAttendanceAddon` handles both "add during stay" and "add after checkout (charge immediately)"; `destroyAttendanceAddon` with billing guard
- `Roster/Index.vue` — expandable per-dog addon panel (click dog name), add/remove addons inline, addon count hint in subtitle

### Tests Added or Updated
- `tests/Feature/Web/Admin/ServicesControllerTest.php` — 9 new tests
- `tests/Feature/Web/Admin/BoardingControllerTest.php` — 2 new tests
- `tests/Feature/Web/Admin/RosterControllerTest.php` — 7 new tests

### Build Status
- Tests: 913 passing
- Build: Successful (no TS errors)

### Notes
- `both`-context addon types (e.g. Nail Clip) appear in both the boarding ReservationShow dropdown and the roster inline panel
- If no card on file at daycare checkout, an order is created with `status='pending'` for manual follow-up
- `destroyAttendanceAddon` guards against removing addons that have already been billed (order exists for the attendance)
