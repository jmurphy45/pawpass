# Phase 18: Web Push Notifications — COMPLETE

## Summary
- `minishlink/web-push` installed; VAPID config in `config/webpush.php`
- Migration: `push_subscriptions` table + `webpush` added to `notif_channel` enum
- `PushSubscription` model; `PushPayload` DTO (`app/DTOs/PushPayload.php`)
- `WebPushChannel` sends to all user subscriptions, auto-deletes expired (410) endpoints
- `PawPassNotification::toWebPush()` returns typed `PushPayload` with `actionUrl` per type
- `NotificationService::resolveChannels()` adds `webpush` when user has subscriptions
- Portal + Admin push-subscription API endpoints (`POST`/`DELETE push-subscriptions`)
- `vapidPublicKey` shared via `HandleInertiaRequests`
- `public/sw.js` service worker handles push events and notification click
- `usePushNotifications` composable (register SW, subscribe, unsubscribe)
- Portal `Account.vue` — Browser Notifications toggle section
- `AdminLayout.vue` — notification bell with unread badge (mobile top bar + desktop footer)
- Admin `Settings/Index.vue` — push enable/disable section
- 1357 tests pass; build succeeds

---

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

---

# Phase 19: PIMS Integration — Adapter Framework & Schema

## Context
Tenants want client and patient data to sync automatically from vet PIMS (Practice Information Management Systems) so customer accounts exist before the customer registers. Vaccination records follow the same path. Self-registration still works but must guard against creating duplicates for already-synced accounts.

**Targets:** ezyVet (REST/OAuth2) and Vetspire (GraphQL/OAuth2) as first two adapters.  
**Architecture:** provider-agnostic adapter contract so adding a new PIMS = implement one interface + register it.  
**Plan gate:** `pims_integration` feature, professional plan or higher.

---

## Step 1 — Adapter Contract & DTOs

- [ ] Create `app/Contracts/PimsAdapterInterface.php`
  - Methods: `providerKey()`, `providerLabel()`, `authenticate()`, `testConnection()`, `fetchClients()`, `fetchPatients()`, `fetchVaccinations()`
  - Verification: interface file exists, no syntax errors (`php -l`)

- [ ] Create `app/DataTransferObjects/Pims/PimsClient.php`, `PimsPatient.php`, `PimsVaccination.php`
  - PHP 8.2 readonly classes, no framework dependency
  - Verification: can be instantiated in a unit test with expected fields

- [ ] Create `app/Services/Pims/PimsAdapterRegistry.php`
  - `register()`, `for()` (throws on unknown key), `providers()`
  - Verification: unit test resolves registered adapters, throws for unknown key

---

## Step 2 — Concrete Adapters (stubs — no live HTTP)

- [ ] Create `app/Services/Pims/EzyVetAdapter.php`
  - Implements interface; `authenticate()` and all `fetch*()` methods throw `NotImplementedException` stub
  - Species filter: `GET /animal?species_id={dog_species_id}` documented in code
  - Verification: `$registry->for('ezyvet')` resolves; `providerKey()` returns `'ezyvet'`

- [ ] Create `app/Services/Pims/VetspireAdapter.php`
  - Same stub pattern; GraphQL species filter `species: "Canine"` documented
  - Verification: `$registry->for('vetspire')` resolves; `providerKey()` returns `'vetspire'`

- [ ] Register both adapters as singleton in `AppServiceProvider::register()`
  - Verification: `app(PimsAdapterRegistry::class)->providers()` returns both in tinker

---

## Step 3 — Schema Migrations

- [ ] `database/migrations/XXXX_create_pims_integrations_table.php`
  - Columns: id (ULID), tenant_id, provider, api_base_url (null), credentials (encrypted text), status (CHECK), last_full_sync_at, last_delta_sync_at, sync_cursor, sync_error, timestamps
  - UNIQUE(tenant_id, provider)
  - Verification: migration runs; `\Schema::hasTable('pims_integrations')` true

- [ ] `database/migrations/XXXX_create_pims_sync_logs_table.php`
  - Columns: id (bigserial), tenant_id, provider, started_at, finished_at, status (CHECK), clients_processed, patients_processed, vaccinations_processed, error_detail
  - No updated_at (append-only)
  - Verification: migration runs; table exists

- [ ] `database/migrations/XXXX_add_pims_fields_to_customers.php`
  - Add: pims_client_id (text null), pims_provider (text null), pims_synced_at (timestamptz null)
  - Partial unique index: `(tenant_id, pims_provider, pims_client_id) WHERE pims_client_id IS NOT NULL`
  - Verification: columns exist on customers table

- [ ] `database/migrations/XXXX_add_pims_fields_to_dogs.php`
  - Add: pims_patient_id (text null), pims_provider (text null), pims_synced_at (timestamptz null), microchip_number (text null)
  - Partial unique index on (tenant_id, pims_provider, pims_patient_id)
  - Verification: columns exist on dogs table

- [ ] `database/migrations/XXXX_add_pims_fields_to_dog_vaccinations.php`
  - Add: pims_record_id (text null), pims_provider (text null), source (text NOT NULL DEFAULT 'manual', CHECK IN ('manual','pims'))
  - Partial unique index on (dog_id, pims_provider, pims_record_id)
  - Verification: columns exist; existing rows have source='manual'

---

## Step 4 — Models & Factories

- [ ] Create `app/Models/PimsIntegration.php`
  - BelongsToTenant, HasUlid; `credentials` cast as `'encrypted:array'`
  - Fillable: provider, api_base_url, credentials, status, sync_cursor, sync_error, last_full_sync_at, last_delta_sync_at
  - Verification: factory creates valid row; credentials round-trip encrypts/decrypts

- [ ] Create `app/Models/PimsSyncLog.php`
  - No BelongsToTenant scope; `$incrementing = true`; `public const UPDATED_AT = null`
  - Verification: can insert via `DB::table('pims_sync_logs')->insert()`

- [ ] Create `database/factories/PimsIntegrationFactory.php`
  - Verification: `PimsIntegration::factory()->create()` works in tests

- [ ] Update `$fillable` on Customer, Dog, DogVaccination models for new PIMS fields
  - Update factories to include nullable PIMS fields
  - Verification: all 913+ existing tests still pass

---

## Step 5 — Admin API (CRUD + test-connection)

- [ ] Create `app/Http/Controllers/Admin/V1/PimsIntegrationController.php`
  - `index`, `providers`, `store`, `update`, `destroy`, `testConnection`, `syncLogs`
  - Owner-only: `abort(403)` for non-owner roles
  - Verification: feature test covers 403 for staff role, 403 for wrong plan, 200 for owner

- [ ] Register routes in `routes/api.php` under `admin/v1` with `->middleware('plan:pims_integration')`
  - Verification: `php artisan route:list | grep pims`

- [ ] Add `pims_integration` to platform plan feature list (whichever config/service defines plan features)
  - Verification: professional plan allows; hobby plan denied

---

## Step 6 — Self-Registration Guard

- [ ] Modify `app/Http/Controllers/Portal/V1/Auth/RegisterController.php`
  - Before creating Customer/User: look up customers by (tenant_id, email)
  - If found + pims_client_id set + no user_id → create User, link customer.user_id, return success
  - If found + pims_client_id set + user_id present → return 422 "Account already exists, please log in"
  - If found + no pims_client_id (manual staff record) → create User, link customer.user_id
  - If not found → existing creation path unchanged
  - Verification: feature tests cover all four branches

---

## Step 7 — Dashboard UI (Integrations Page)

- [ ] Create `app/Http/Controllers/Web/Admin/IntegrationsController.php`
  - Inertia controller; shares `providers` (from registry) and `integrations` (tenant's rows) as props
  - Owner-only (abort 403 for staff)
  - Verification: returns Inertia response with correct props shape

- [ ] Register route: `GET /admin/integrations` → `IntegrationsController@index` (owner-only, plan:pims_integration)
  - Verification: route exists in route list

- [ ] Create `resources/js/Pages/Admin/Integrations/Index.vue`
  - Provider cards grid (from `providers` prop) with Connect button
  - Connected integrations table with status badge, last sync time, actions
  - Sync log side drawer (paginated via API call)
  - Verification: page renders without console errors; owner sees cards; staff sees 403

- [ ] Add "Integrations" link to AdminLayout.vue (owner-only, same pattern as existing owner-only links)
  - Verification: link visible for business_owner, hidden for staff

---

## Verification (Full Suite)

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan test
npm run build
```

- All migrations run without error on fresh DB
- All 913+ existing tests pass (zero regressions)
- New feature tests pass (CRUD, plan gate, registration guard, 403s)
- Build succeeds with no TypeScript errors

---

# Task: Timezone-Aware Unlimited Pass Expiry

## Part 1 — Bind `current.tenant` model

- [ ] Add `app()->instance('current.tenant', $tenant)` to `TenantMiddleware`
  - Verification: tenant model accessible via `app('current.tenant')` in services
- [ ] Add null default `$this->app->bind('current.tenant', fn () => null)` to `AppServiceProvider`
  - Verification: no error when `current.tenant` resolved outside tenant context

## Part 2 — Fix unlimited pass expiry

- [ ] Update `DogCreditService::issueUnlimitedPass()` to use tenant timezone
  - Verification: failing test for `test_issue_unlimited_pass_sets_only_unlimited_pass_expires_at`
- [ ] Add tests for timezone-aware expiry in `DogCreditServiceTest`
  - Verification: new test passes with correct UTC value
- [ ] Bind `current.tenant` in `StripeWebhookController` before `issueUnlimitedPass` calls
  - Verification: existing webhook tests pass

## Part 3 — Timezone at registration

- [ ] Add `timezones` prop + `timezone` validation to `TenantRegistrationController`
- [ ] Write `timezone` in `TenantRegistrationService::register()`
- [ ] Add timezone `<select>` to `Registration/Create.vue` step 2
  - Verification: registration form submits timezone; tenant has correct timezone after signup

## Part 4 — Timezone dropdown in settings

- [ ] Add `timezones` prop to `SettingsController::show()`
- [ ] Replace text input with `<select>` in `Settings/Index.vue`
  - Verification: settings page shows dropdown with correct options

## Part 5 — Tenant timezone in Inertia + frontend display

- [ ] Add `timezone` to `tenant` prop in `HandleInertiaRequests`
- [ ] Add `timezone: string` to `Tenant` interface in `types/index.d.ts`
- [ ] Update `formatDate` in 4 Vue pages to use `timeZone: tenant.timezone`

---

# Task: UX Audit P0 Fixes (subscription labeling, front-desk credit tools, password self-service)

## Context
A UX audit (owner + customer persona walkthrough) found three defects on the trust/revenue path: the tenant landing page advertised subscription packages that could never be sold as subscriptions; front-desk staff had no way to sell a package or adjust a dog's credit balance even though the backend for it already existed; and customers had no way to change or reset a forgotten password, even though the backend for password-change already existed.

## Fix 1 — Subscription/one-time labeling mismatch
- [x] `Home.vue` — collapsed the package type badge to `unlimited` vs `one-time` (dropped the `subscription` branch and its `/mo` suffix), since the team had already reverted real Stripe subscriptions in favor of auto-replenish (`packages.type` no longer creatable as `subscription`)
  - Verification: display-only change, no backend/migration; manually verified against ShowcaseSeeder's legacy `subscription`-typed package

## Fix 2 — Front-desk credit adjustment + package sale
- [x] Added missing edge-case tests to `CreditControllerTest` (cross-tenant transfer, positive-integer goodwill validation)
- [x] Added `siblingDogs` prop to `DogController::show()` (failing test first)
- [x] Added "Adjust Credits" button + `AppModal` (goodwill/correction/transfer) to `Dogs/Show.vue`, wired to the existing `admin.credits.*` routes
- [x] Deleted the dead `Credits/Adjust.vue` stub (zero references)
- [x] Added `SellPackageTest` (charge success, no card, Stripe decline, dog-ownership validation, webhook idempotency) before implementing
- [x] Added `CustomerController::sellPackage()` — off-session charge via `StripeService::createPaymentIntent` (metadata deliberately omits `charge_type` so the existing webhook's normal Order-crediting path stays idempotent against it), modeled on `PurchaseController::store()`/`confirm()`
- [x] Added `admin.customers.sell-package` route and a "Sell Package" button + `AppModal` on `Customers/Show.vue`
  - Verification: `SellPackageTest` (7 tests) includes a webhook-replay test proving credits are issued exactly once

## Fix 3 — Customer password self-service
- [x] Added `AccountPasswordTest` (change with/without existing password, wrong current password, confirmation mismatch) — backend (`AccountController::updatePassword`) already worked, this closed a test-coverage gap
- [x] Rendered the password-change form in `Portal/Account.vue` (`hasPassword` prop was already passed but never used)
- [x] Added `ForgotPasswordTest` / `ResetPasswordTest` for a new web-only flow, then implemented `Web/Portal/Auth/{ForgotPasswordController,ResetPasswordController}` mirroring the existing API-only implementation's token logic (custom `password_reset_tokens` table, not Laravel's stock broker), registered under the existing `/my` guest route group
- [x] Added the missing `auth.password_reset` action-link branch in `PawPassNotification` (the email had no reset link at all before this)
- [x] Added "Forgot password?" link in `Auth/Login.vue`'s password tab
  - Verification: the two pre-existing `Auth/ForgotPassword.vue`/`ResetPassword.vue` Vue pages needed zero changes — they were already calling route names that simply didn't exist yet

## Review

### Summary of Changes
- `resources/js/Pages/Home.vue` — package type label no longer implies unsellable subscriptions
- `app/Http/Controllers/Web/Admin/DogController.php` — `siblingDogs` prop for the transfer picker
- `resources/js/Pages/Admin/Dogs/Show.vue` — Adjust Credits modal (goodwill/correction/transfer)
- `resources/js/Pages/Admin/Credits/Adjust.vue` — deleted (dead stub)
- `app/Http/Controllers/Web/Admin/CustomerController.php` — `sellPackage()`, new `packages` prop on `show()`
- `resources/js/Pages/Admin/Customers/Show.vue` — Sell Package button + modal
- `routes/web.php` — `admin.customers.sell-package`, `portal.forgot-password[.store]`, `portal.reset-password[.store]`
- `resources/js/Pages/Portal/Account.vue` — password-change section
- `app/Http/Controllers/Web/Portal/Auth/ForgotPasswordController.php`, `ResetPasswordController.php` — new
- `app/Notifications/PawPassNotification.php` — `auth.password_reset` action-link branch
- `resources/js/Pages/Auth/Login.vue` — "Forgot password?" link

### Tests Added or Updated
- `tests/Feature/Web/Admin/CreditControllerTest.php` — 2 new edge-case tests (8 total)
- `tests/Feature/Web/Admin/DogControllerTest.php` — 1 new test (`siblingDogs`)
- `tests/Feature/Web/Admin/SellPackageTest.php` — new, 7 tests
- `tests/Feature/Web/Portal/AccountPasswordTest.php` — new, 5 tests
- `tests/Feature/Web/Portal/Auth/ForgotPasswordTest.php` — new, 4 tests
- `tests/Feature/Web/Portal/Auth/ResetPasswordTest.php` — new, 6 tests
- `tests/Unit/Notifications/PawPassNotificationTest.php` — 2 new tests (action-link present/absent)

### Build Status
- `php artisan test`: 1669 passed, 19 failed — all 19 failures confirmed pre-existing on the unmodified baseline (missing `imagick` PHP extension for QR/mail-attachment tests; stale hardcoded reservation-test dates now in the past relative to the container clock), verified via `git stash`/`stash pop` before and after
- `npm run type-check`: no new errors introduced (baseline already has ~40 pre-existing `route()` typing errors unrelated to this work)
- `npm run build`: succeeds
- `./vendor/bin/pint --dirty`: clean (one pre-existing unused import + spacing fix auto-applied to a touched test file)

### Notes
- Deliberately did **not** revive the abandoned Stripe Subscriptions code path (`Portal/V1/SubscriptionController`, `StripeService::createSubscription`) — the team's own migration history shows this was intentionally scrapped in favor of auto-replenish; reviving it would fight the codebase's grain for no product benefit
- Deliberately did **not** write a data migration for legacy `type=subscription` seeder rows (`DemoSeeder`/`ShowcaseSeeder`) — Fix 1 is presentation-only and those are demo fixtures
- Per explicit user decision: Sell Package is card-only (no manual/cash grant path), and both new actions (Adjust Credits, Sell Package) are open to `staff`, not owner-only
  - Verification: `npm run build` passes; displayed dates respect tenant timezone
