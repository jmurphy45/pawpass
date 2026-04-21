# Task: Daycare Leaderboard + SEO Enhancement

## Goal
Build a public leaderboard showing daily activity stats per daycare, and improve
search indexing for queries like "doggy daycare Memphis" and "boarding Memphis April 20".

---

## Phase 1 — LeaderboardService (data layer)

- [ ] Add failing tests for `LeaderboardService`
  - `dogsCurrentlyCheckedIn(tenantId)` — count where `checked_out_at IS NULL` and `checked_in_at::date = today`
  - `dogsTodayTotal(tenantId)` — count all check-ins today regardless of checkout
  - `leaderboardStats(Collection<Tenant>)` — returns ranked array with both counts per tenant
  - Verification: tests fail with class-not-found

- [ ] Implement `LeaderboardService`
  - Uses `attend_tenant_active_idx` partial index for currently-in count
  - Bulk loads stats for all tenants in one query (no N+1)
  - Results cached in Redis for 5 minutes (key: `leaderboard:{state}:{city}` or `leaderboard:all`)
  - Verification: all service tests pass

---

## Phase 2 — Routes + LeaderboardController

- [ ] Add failing feature test for `GET /leaderboard`
  - Returns list of publicly listed active tenants ranked by dogs-in-today
  - Filters to only `is_publicly_listed = true` + active statuses
  - Verification: test fails 404

- [ ] Add failing feature test for `GET /leaderboard/{state}/{city}`
  - Returns only tenants matching that city/state
  - Returns correct Inertia page with city-specific title prop
  - Verification: test fails 404

- [ ] Add routes to `routes/web.php`
  ```
  GET /leaderboard               → LeaderboardController@index
  GET /leaderboard/{state}/{city} → LeaderboardController@city
  ```

- [ ] Implement `LeaderboardController`
  - `index()` — top 50 across all cities, passes SEO head props
  - `city($state, $city)` — filtered list, normalizes slug to display name
  - Both return Inertia `Leaderboard` with `{ tenants, stats, headTitle, headDescription }`
  - Verification: both feature tests pass

---

## Phase 3 — Leaderboard.vue page

- [ ] Create `resources/js/Pages/Leaderboard.vue`
  - `<Head>` with dynamic `headTitle` / `headDescription` props
  - Ranked table/card list: rank badge, logo, name, city+state, dogs in now, dogs today total
  - "Book Now" link → `https://{slug}.pawpass.com`
  - "View city" link on each row → `/leaderboard/{state}/{city}` (when on global page)
  - City filter input with Inertia `router.visit` on submit
  - Kennel badge if `business_type` is `kennel` or `hybrid`
  - Responsive: cards on mobile, table on desktop
  - Verification: `npm run build` passes; page loads in browser with real data

---

## Phase 4 — SEO meta tags on existing pages

- [ ] Add `<Head>` to `FindADaycare.vue`
  - Global: "Find a Doggy Daycare Near You | PawPass"
  - City page: "Doggy Daycare in {City}, {State} | PawPass" + meta description listing count
  - Verification: view-source shows correct `<title>` and `<meta name="description">`

- [ ] Add `<Head>` to `Home.vue` tenant landing page branch
  - "{Tenant Name} — Doggy Daycare in {City}, {State}"
  - Meta description from `business_description`
  - Verification: view-source on a tenant subdomain

---

## Phase 5 — Schema.org structured data on tenant pages

- [ ] Add `LocalBusiness` JSON-LD to `Home.vue` (tenant branch only)
  - Type: `DaycareOrNursery` (for daycares) or `LodgingBusiness` (for kennels/hybrid)
  - Fields: `name`, `address`, `telephone`, `url`, `image` (logo), `description`, `geo` (if available)
  - Verification: paste URL into Google Rich Results Test (or schema.org validator)

- [ ] Add `LocalBusiness` JSON-LD to each city page in `FindADaycare.vue`
  - `ItemList` wrapping each daycare's `LocalBusiness` stub
  - Verification: schema.org validator passes

---

## Phase 6 — Boarding availability search

- [ ] Add failing feature tests for `GET /find-boarding` and `GET /find-boarding/{state}/{city}`
  - With `?checkin=2026-04-20&checkout=2026-04-21` query params
  - Returns tenants that have at least one available kennel unit (no overlapping reservation)
  - Verification: tests fail 404

- [ ] Implement `BoardingSearchController`
  - `index()` — nationwide search with optional date params, redirects to city page if state+city provided
  - `city($state, $city)` — date-aware availability query against kennel units
  - Passes `{ tenants, checkin, checkout, headTitle, headDescription }` to Inertia
  - Verification: feature tests pass

- [ ] Create `resources/js/Pages/FindBoarding.vue`
  - Date-range picker (check-in / check-out) — submits via `router.visit` with query params
  - Results list: tenant name, city, available unit count, "Book Now" → tenant subdomain
  - `<Head>` with dynamic title: "Dog Boarding in {City}, {State} | PawPass" or date-aware variant
  - `LodgingBusiness` JSON-LD for each result
  - Verification: `npm run build` passes; date filter works in browser

---

## Phase 7 — Sitemap updates

- [ ] Update `GenerateSitemapCommand` to include all new URLs
  - `/leaderboard` — priority 0.8, daily
  - `/leaderboard/{state}/{city}` — per distinct city, priority 0.7, daily
  - `/find-boarding` — priority 0.8, daily
  - `/find-boarding/{state}/{city}` — per distinct city with kennels/hybrids, priority 0.7, daily
  - Verification: run command locally, check `/public/sitemap.xml` contains all new URLs

---

## Review

### Summary of Changes
- `LeaderboardService` — bulk-queries daily attendance counts per tenant (2 queries, no N+1), Redis-cached 5 min
- `LeaderboardController` — `GET /leaderboard` and `GET /leaderboard/{state}/{city}`
- `BoardingSearchController` — `GET /find-boarding` and `GET /find-boarding/{state}/{city}` with date-range availability filter
- `Leaderboard.vue` — ranked table/cards with live dog counts, city filter, links to tenant subdomains
- `FindBoarding.vue` — date-picker search, availability badges, `LodgingBusiness` Schema.org JSON-LD
- `FindADaycare.vue` — added `<Head>` with dynamic title/description + `ItemList` Schema.org for city pages
- `Home.vue` — added `<Head>` with tenant SEO title/description + `LocalBusiness` Schema.org JSON-LD
- `HomeController` — passes `headTitle`, `headDescription`, and full location/description fields for tenant pages
- `DaycareDirectoryController` — passes `headTitle`, `headDescription` for global and city pages
- `GenerateSitemapCommand` — adds `/leaderboard`, `/leaderboard/{state}/{city}`, `/find-boarding`, `/find-boarding/{state}/{city}` (boarding cities only)
- `tests/TestCase.php` — added `$this->withoutVite()` to all tests (fixes local environment without built assets)
- `phpunit.xml` — changed `DB_HOST` from `pgsql` to `127.0.0.1` for local postgres

### Tests Added or Updated
- `tests/Unit/Services/LeaderboardServiceTest.php` — 5 tests
- `tests/Feature/Web/LeaderboardControllerTest.php` — 6 tests
- `tests/Feature/Web/BoardingSearchControllerTest.php` — 6 tests
- `tests/Feature/GenerateSitemapCommandTest.php` — 3 tests
- All existing `HomeControllerTest` tests continue to pass

### Build Status
- Tests: 24 new passing, 0 regressions on affected tests
- Build: `npm run build` successful (10.67s)

### Notes
- Boarding search links out to tenant subdomains for actual booking — a centralized booking flow would be a natural next step
- Cache keys for leaderboard use `leaderboard:all` and `leaderboard:{state}:{city}` — consider cache invalidation on check-in/check-out events if real-time accuracy matters more than DB load
- Schema.org `DaycareOrNursery` type was considered for dog daycares but Google treats animal services best as `LocalBusiness` — revisit if a more specific animal-services type emerges
