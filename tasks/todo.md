# Task: Public Daycare Directory ("Find a Daycare Near You")

## Phase A — Business Location Data

- [x] Migration: add directory fields to tenants
- [x] Update Tenant model fillable + casts
- [x] Write failing tests for public API `/api/public/v1/daycares`
- [x] Implement `DaycareDirectoryController` (Public/V1) + add route
- [x] Update `UpdateBusinessSettingsRequest` to accept directory fields
- [x] Update `Admin\V1\SettingsController::tenantData()` to return directory fields
- [x] Update `Web\Admin\SettingsController` to accept + save directory fields
- [x] Update Settings `Index.vue` — add Public Directory section

## Phase B — Web Directory Page

- [x] Add `DaycareDirectoryController` (Web) + routes in `web.php`
- [x] Create `resources/js/Pages/FindADaycare.vue`

## Phase C — Sitemap

- [x] `composer require spatie/laravel-sitemap`
- [x] Create `app/Console/Commands/GenerateSitemapCommand.php`
- [x] Register command + schedule at 3:30 AM in `bootstrap/app.php`

## Step 5 — StripeBillingService Updates

- [x] `./vendor/bin/sail artisan test` — 1066 passed, no regressions
- [x] `npm run build` — clean, no TS/Vite errors

---

---

## Task: Domain-Driven Strategy Pattern for Order Cancellation

- [x] Interface: `OrderCancellationStrategy` (supports + cancel)
- [x] Unit tests for `BoardingCancellationStrategy` — 7 tests pass
- [x] Implement `BoardingCancellationStrategy`
- [x] Unit tests for `AttendanceAddonCancellationStrategy` — 5 tests pass
- [x] Implement `AttendanceAddonCancellationStrategy`
- [x] Unit tests for `DaycareCancellationStrategy` — 7 tests pass
- [x] Implement `DaycareCancellationStrategy`
- [x] Unit tests for `CancellationStrategyResolver` — 4 tests pass
- [x] Implement `CancellationStrategyResolver`
- [x] Update `CancelStalePendingOrders` job (query + signature)
- [x] Add new feature tests for job — 13 tests pass (5 new)
- [x] Full suite: 1213 passed, 3 pre-existing isolation failures (unrelated)

---

## Review

### Summary of Changes
- Migration `2026_04_04_000001` adds 7 columns to tenants: `business_address`, `business_city`, `business_state`, `business_zip`, `business_phone`, `business_description`, `is_publicly_listed`
- `Tenant` model: new fields in `$fillable`, `is_publicly_listed` cast to boolean
- `DaycareDirectoryController` (Public/V1): `GET /api/public/v1/daycares` — search by city/state or zip, optional date range for boarding availability
- `DaycareDirectoryController` (Web): Inertia page at `/find-a-daycare` and `/find-a-daycare/{state}/{city}`
- `FindADaycare.vue`: public search page with city/state or zip search, boarding date filter, result cards
- Settings controllers (API + Web): accept and expose all 7 new directory fields
- Settings `Index.vue`: new "Public Directory" form section
- `GenerateSitemapCommand`: `php artisan sitemap:generate` builds `public/sitemap.xml` with static pages + city pages + tenant subdomain URLs; scheduled at 3:30 AM UTC

### Tests Added or Updated
- `tests/Feature/Public/DaycareDirectoryTest.php` — 12 tests covering visibility, search filters, response shape, boarding availability logic

### Build Status
- Tests: 1066 passed (no regressions)
- Build: Successful

### Notes
- Tenants opt-in via `is_publicly_listed = true`; defaults to false
- `spatie/laravel-sitemap ^8.1` installed
- Geocoding/radius search deferred — city+state+zip text search sufficient for v1
- `config('app.domain')` used in sitemap for tenant subdomain URLs; set `APP_DOMAIN=pawpass.com` in production env if needed
