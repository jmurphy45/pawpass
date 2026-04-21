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

## Phase 6 — Sitemap updates

- [ ] Update `GenerateSitemapCommand` to include leaderboard URLs
  - `GET /leaderboard` — priority 0.8, daily change frequency
  - `GET /leaderboard/{state}/{city}` — one entry per distinct city (same source query as existing city pages), priority 0.7, daily
  - Verification: run command locally, check `/public/sitemap.xml` contains new URLs

---

## Review

*(Fill in after all tasks complete)*

### Summary of Changes
-

### Tests Added or Updated
-

### Build Status
- Tests:
- Build:

### Notes
-
