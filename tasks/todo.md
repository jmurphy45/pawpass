# Phase 16: Reporting & Analytics

## Task 0 — Assign `financial_reports` to Pro & Business plans

- [x] `financial_reports` already present in Pro/Business in `PlatformPlanSeeder.php`
  - Verification: added 6 tests to `PlanFeaturesTest` asserting Free→no reports, Starter→basic only, Pro→both, Business→both

## Task 1 — ReportService

- [x] Create `app/Services/ReportService.php` with 12 report query methods
  - `revenue`, `payoutForecast`, `packages`, `credits`, `customersLtv`, `attendance`, `rosterHistory`, `creditStatus`, `staffActivity`, `platformRevenue`, `tenantHealth`, `notificationDelivery`
  - SQLite-compatible grouping via `dateGroup()` helper (uses `strftime` for SQLite, `to_char/date_trunc` for PostgreSQL)
  - Verification: 13 unit tests pass in `ReportServiceTest`

## Task 2 — Admin ReportController (9 endpoints)

- [x] Create `app/Http/Controllers/Admin/V1/ReportController.php`
  - Nightly-cached endpoints (`revenue`, `payoutForecast`, `packages`, `credits`, `customersLtv`)
  - Real-time endpoints (`attendance`, `rosterHistory`, `creditStatus`, `staffActivity`)
  - CSV export via `?format=csv` using `StreamedResponse`
  - Verification: 25 feature tests pass in `Admin/ReportControllerTest`

## Task 3 — Platform ReportController (3 endpoints)

- [x] Create `app/Http/Controllers/Platform/V1/ReportController.php`
  - `revenue` (cached 25h), `tenantHealth` (cached 25h), `notificationDelivery` (real-time)
  - Verification: 10 feature tests pass in `Platform/ReportControllerTest`

## Task 4 — Routes

- [x] Update `routes/api.php` with 12 report API routes (3 groups: basic staff, basic owner, financial owner, platform)
- [x] Update `routes/web.php` with 7 Inertia report routes
  - Verification: `php artisan route:list | grep reports` shows 19 endpoints (12 API + 7 web)

## Task 5 — WarmTenantReportCaches Job

- [x] Implement `app/Jobs/WarmTenantReportCaches.php`
  - Iterates non-deleted tenants, resolves plan features, writes applicable caches
  - Verification: 4 unit tests pass in `WarmTenantReportCachesTest`

## Task 6 — WarmPlatformReportCaches Job

- [x] Implement `app/Jobs/WarmPlatformReportCaches.php`
  - Writes `platform:revenue:snapshot` and `platform:tenant_health:snapshot`
  - Verification: 3 unit tests pass in `WarmPlatformReportCachesTest`

## Task 7 — Frontend (Vue)

- [x] Create `resources/js/Pages/Admin/Reports/Index.vue` — landing page with Financial/Operational groups
- [x] Create `resources/js/Pages/Admin/Reports/Revenue.vue` — date range + group_by, table, CSV export
- [x] Create `resources/js/Pages/Admin/Reports/Packages.vue` — date range, table, CSV export
- [x] Create `resources/js/Pages/Admin/Reports/Credits.vue` — date range, table, CSV export
- [x] Create `resources/js/Pages/Admin/Reports/Customers.vue` — date range, table, CSV export
- [x] Create `resources/js/Pages/Admin/Reports/Attendance.vue` — date range + group_by, table, CSV export
- [x] Create `resources/js/Pages/Admin/Reports/CreditStatus.vue` — zero/low sections
- [x] Update `resources/js/Layouts/AdminLayout.vue` — Reports nav link (Starter+)
- [x] Create `app/Http/Controllers/Web/Admin/ReportController.php` — Inertia controller
  - Verification: `npm run build` succeeds, no TS errors

## Bug Fix (pre-existing)

- [x] Fix `app/Jobs/SyncPackageToStripe.php` — unlimited packages were incorrectly creating a Stripe price
  - Updated unit test to match: unlimited creates product but no price
  - Verification: both `PackageControllerStripeTest` and `SyncPackageToStripeTest` pass

---

## Review

### Summary of Changes
- `ReportService`: 12 query methods covering all report types; SQLite-compatible
- `Admin\V1\ReportController`: 9 endpoints with plan gating at route level, caching, CSV export
- `Platform\V1\ReportController`: 3 endpoints (platform_admin only), revenue + tenant health cached
- `Web\Admin\ReportController`: Inertia controller serving 7 report pages
- `WarmTenantReportCaches`: implemented cache warming per tenant plan tier
- `WarmPlatformReportCaches`: implemented platform report cache warming
- Routes: 12 API + 7 web report routes added
- Vue pages: 7 report pages (Index, Revenue, Packages, Credits, Customers, Attendance, CreditStatus)
- AdminLayout: Reports nav link added for Starter+ plans
- Bug fix: `SyncPackageToStripe` now skips price creation for unlimited packages

### Tests Added or Updated
- `tests/Unit/PlanFeaturesTest.php`: 6 new reporting feature assertions
- `tests/Unit/Services/ReportServiceTest.php`: 13 new tests (all 12 report methods)
- `tests/Feature/Admin/ReportControllerTest.php`: 25 new tests (plan gating, roles, CSV, caching, data)
- `tests/Feature/Platform/ReportControllerTest.php`: 10 new tests
- `tests/Unit/Jobs/WarmTenantReportCachesTest.php`: 4 new tests
- `tests/Unit/Jobs/WarmPlatformReportCachesTest.php`: 3 new tests
- `tests/Unit/Jobs/SyncPackageToStripeTest.php`: renamed + fixed contradictory unlimited test

### Build Status
- Tests: 622 passed (0 failures)
- Build: Successful (`npm run build` — no TS or build errors)

### Notes
- `Admin/Reports/Index.vue` uses inline component for `ReportCard` — could be extracted later
- Platform report pages (web UI) deferred — platform admin uses the API directly
- `WarmTenantReportCaches` uses a static plan feature cache; this is cleared between PHP processes (no inter-request state issue)
- `tenantPlan` is not yet passed as a shared Inertia prop — Reports nav link in AdminLayout uses the page prop but it will be hidden until `tenantPlan` is added to `HandleInertiaRequests::share()`

---

## Phase 17: SMS Segment Quotas + Broadcast Notifications

### Part 1 — SMS Segment Quotas

- [x] Migration: `sms_segment_quota` column on `platform_plans`
  - Verification: Migration runs cleanly

- [x] Migration: `tenant_sms_usage` table
  - Verification: Migration runs cleanly

- [x] Model: `TenantSmsUsage`
  - Verification: Unit tests pass

- [x] Modify `PlatformPlan` (fillable + casts) and `PlanFeatureCache` (`smsSegmentQuota()`)
  - Verification: SmsUsageServiceTest passes

- [x] Update `PlatformPlanFactory` (add `sms_segment_quota: 0`)
  - Verification: Factory works in tests

- [x] Update `PlatformPlanSeeder` (add `sms_segment_quota` + `sms_notifications` to all plans)
  - Verification: Seeder reviewed

- [x] `TwilioService::send()` returns `int` segment count
  - Verification: TwilioServiceTest (5 tests) passes

- [x] Create `SmsUsageService` with 6 methods; register singleton
  - Verification: SmsUsageServiceTest (11 tests) passes

- [x] Update `SmsChannel` to inject `SmsUsageService` and call `track()` after successful send
  - Verification: SmsChannelTest (3 tests) passes

- [x] Add `createInvoiceItem()` and `createAndFinalizeInvoice()` to `StripeBillingService`
  - Verification: BillSmsOverageJobTest relies on them

- [x] Create `BillSmsOverageJob` — processes prior-month overages for active tenants
  - Verification: BillSmsOverageJobTest (5 tests) passes

- [x] Register `BillSmsOverageJob` in scheduler (`monthlyOn(1, '05:00')`)
  - Verification: Scheduler entry added to bootstrap/app.php

### Part 2 — Broadcast Notifications

- [x] Add `announcement` type to `PawPassNotification::buildMessage()`
  - Verification: PawPassNotificationTest (3 new tests) passes

- [x] Create `SendBroadcastNotificationJob` — sends announcement to all tenant customers
  - Verification: SendBroadcastNotificationJobTest (6 tests) passes

- [x] Create `BroadcastNotificationController` and register route `POST /api/admin/v1/notifications/broadcast`
  - Verification: BroadcastNotificationControllerTest (9 tests) passes

## Review

### Summary of Changes
- Added `sms_segment_quota` column to `platform_plans` and `tenant_sms_usage` tracking table
- New `TenantSmsUsage` model and `SmsUsageService` for tracking and querying segment usage
- `TwilioService::send()` now returns segment count instead of void
- `SmsChannel` tracks segments via `SmsUsageService` after each successful send
- `BillSmsOverageJob` runs monthly to bill tenants for SMS overages at $0.04/segment
- `PawPassNotification` supports new `announcement` type using subject/body from data
- `SendBroadcastNotificationJob` dispatches per-channel notifications to all customer users of a tenant
- `BroadcastNotificationController` validates and queues broadcast requests from admin API

### Tests Added or Updated
- `tests/Unit/Services/TwilioServiceTest.php`: 5 new tests (segment counting, failure handling)
- `tests/Unit/Services/SmsUsageServiceTest.php`: 11 new tests (all 6 service methods)
- `tests/Unit/Notifications/PawPassNotificationTest.php`: 3 new announcement type tests
- `tests/Unit/Notifications/Channels/SmsChannelTest.php`: updated to pass SmsUsageService, return segment int
- `tests/Feature/Jobs/BillSmsOverageJobTest.php`: 5 new tests
- `tests/Feature/Jobs/SendBroadcastNotificationJobTest.php`: 6 new tests
- `tests/Feature/Admin/BroadcastNotificationControllerTest.php`: 9 new tests

### Build Status
- Tests: 661 passed (0 failures)
- Build: Not yet verified (npm run build)

### Notes
- `user_notification_preferences` table uses `type` and `is_enabled` (not `notification_type`/`enabled`)
- `current.tenant` binding doesn't exist — `BroadcastNotificationController` uses `Tenant::find(app('current.tenant.id'))`
- Plan slugs in `tenant_plan` PG enum are restricted to: `free`, `starter`, `pro`, `business`
- `TenantSmsUsage` model explicitly sets `$table = 'tenant_sms_usage'` (avoids Laravel's default pluralization)

---

## Task: Production Review — P0/P1/P2 Fixes

### P0 — Breaking / Exploitable

- [x] Fix idempotency cache key — include tenant_id + user_id
- [x] Fix BillSmsOverageJob double-billing — ShouldBeUnique + Stripe idempotency keys
- [x] Fix deductForAttendance() stale dog — lockForUpdate inside transaction
- [x] Fix transfer() negative balance — InsufficientCreditsException guard
- [x] Fix NotificationService::enqueueGrouped() race — lockForUpdate in transaction
- [x] Fix ExpireSubscriptionCredits — eager-load customer, null guard, per-dog try-catch
- [x] Fix StripeWebhookController — null check on $tenant after Tenant::find()
- [x] Fix Admin/V1/BroadcastNotificationController — align validation with Web controller
- [x] Fix TwilioService segment count — detect non-ASCII, use 70 chars/segment

### P1 — Pre-scale

- [x] Fix SendBroadcastNotificationJob — chunkById(100) + $tries = 1
- [x] Add $tries/$backoff to DispatchGroupedAlertJob
- [x] Fix SmsUsageService::track() — atomic SQL upsert
- [x] Add throttle:30,1 to public API; throttle:5,1 to registration endpoint
- [x] Fix HandleInertiaRequests logo_url — use actual column value
- [x] Fix WarmTenantReportCaches — remove static from planHas cache
- [x] Fix ProvisionStripeConnectAccountJob — null guard on $owner before try-catch

### P2 — Quality

- [x] Add max:100 on search in Admin/V1/CustomerController + Web/Admin/CustomerController

---

## Task: Direct Charges — Stripe Connected Account Architecture

Switch from destination charges (customer on platform) to direct charges (customer on connected
account). All Stripe API calls for tenant payments go through `stripe_account` SDK option.

### Step 1 — StripeService: update all methods to accept stripeAccountId

- [x] Update `StripeServiceTest` — 10 new tests asserting `stripe_account` option
- [x] Update `createCustomer`, `createPaymentIntent`, `createSetupIntent`, `createSubscription`,
  `createRefund`, `createProduct`, `createPrice`, `archivePrice`, `archiveProduct`
  — all accept `?stripeAccountId` and pass as `['stripe_account' => $id]` SDK option
  - Verification: All 17 StripeServiceTest tests pass

### Step 2 — Controllers: propagate stripe_account_id

- [x] `Portal/V1/SubscriptionController` — createCustomer + createSetupIntent use account ID
- [x] `Web/Portal/SubscribeController` — createCustomer + createSetupIntent use account ID
- [x] `Web/Portal/PurchaseController` — createCustomer uses account ID; createPaymentIntent already did
- [x] `Admin/V1/PaymentController` — createRefund passes tenant stripe_account_id
- [x] `Web/Admin/PaymentController` — createRefund passes tenant stripe_account_id
  - Verification: All updated controller tests pass

### Step 3 — Jobs: propagate stripe_account_id

- [x] `SyncPackageToStripe` — createProduct, createPrice, archivePrice use tenant stripe_account_id
- [x] `ArchivePackageFromStripe` — archivePrice, archiveProduct use tenant stripe_account_id
  - Verification: SyncPackageToStripeTest + ArchivePackageFromStripeTest pass (11 tests)

### Step 4 — Final verification

- [x] Run full test suite: 675 passed, 0 failures
