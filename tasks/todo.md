# Phase: Tax Collection + Auto-Charge at Check-in

---

## Step 1 — Feature Flags

- [x] Add `tax_daycare_orders` and `tax_platform_subscriptions` to `FeaturesServiceProvider`
  - Verification: `Feature::active('tax_daycare_orders')` returns false by default

---

## Step 2 — Migrations

- [x] `2026_03_29_000001_alter_orders_add_tax_columns` — `subtotal_cents`, `tax_amount_cents`, `stripe_tax_calc_id`
- [x] `2026_03_29_000002_alter_tenants_add_billing_address` — `billing_address jsonb nullable`
- [x] `2026_03_30_000001_alter_tenants_add_tax_collection_enabled`
- [x] `2026_03_31_000001_alter_tenants_add_auto_charge_package` — `auto_charge_at_zero_package_id char(26) nullable FK → packages`
  - Verification: migrations run cleanly on both DBs

---

## Step 3 — Model Updates

- [x] `Order` model: add tax fields to fillable + casts
- [x] `Tenant` model: add `billing_address`, `tax_collection_enabled`, `auto_charge_at_zero_package_id` to fillable + casts + `autoChargePackage()` relation
  - Verification: models accept and cast new fields

---

## Step 4 — StripeService Tax Methods

- [x] Add `calculateTax()` — calls `/v1/tax/calculations` on connected account
- [x] Add `createTaxTransaction()` — calls `/v1/tax/transactions/create_from_calculation`
  - Verification: Unit tests pass for both methods

---

## Step 5 — StripeBillingService Updates

- [ ] `createCustomer()` — pass address when `tenant.billing_address` is set
- [ ] Add `updateCustomerAddress()` — syncs address to Stripe customer
- [ ] `createSubscription()` — add `automatic_tax` based on `tax_platform_subscriptions` flag
  - Verification: Tests verify automatic_tax.enabled toggled correctly

---

## Step 6 — OrderController Tax Logic

- [x] Add `postal_code` + `country` to `StoreOrderRequest` (nullable)
- [x] Calculate tax in `store()` when `tax_daycare_orders` flag is on
- [x] Platform fee on subtotal only
- [x] Store `subtotal_cents`, `tax_amount_cents`, `stripe_tax_calc_id` on order
- [x] Add `taxPreview()` endpoint
- [x] Add route for `GET portal/v1/orders/tax-preview`
  - Verification: Tests cover flag-on, flag-off, no postal_code scenarios

---

## Step 7 — Webhook: Record Tax Transaction

- [x] In `handlePaymentIntentSucceeded()` — call `createTaxTransaction()` when `tax_calculation_id` in PI metadata
  - Verification: Test webhook with tax metadata calls the Stripe tax transaction endpoint

---

## Step 8 — BillingController Address

- [ ] Add `billing_address` validation to `subscribe()`
- [ ] Store address on tenant + sync to Stripe customer
  - Verification: Test subscribe with address stores correctly

---

## Step 9 — OrderResource Tax Fields

- [x] Add `subtotal_amount`, `tax_amount`, `total_amount` to response
  - Verification: API response includes tax breakdown

---

## Step A — Plan-Gated Auto-Replenish Feature

- [x] Add `auto_replenish` to `FALLBACK_FEATURES` in `FeaturesServiceProvider`
- [x] Add feature to `PlatformFeatureSeeder` (sort_order 200)
- [x] Add `auto_replenish` to starter/pro/founders/business plans in `PlatformPlanSeeder`
  - Verification: `Feature::active('auto_replenish')` false on free, true on paid plans

---

## Step B — AutoReplenishService Refactor + Tax

- [x] Extract private `charge(Dog, Package, Tenant)` helper shared by both sync paths
- [x] Add `triggerForPackage(Dog, Package): bool` — tenant-level charge with explicit package
- [x] Wire `resolveTax()` helper into `charge()` — applies tax when `tax_daycare_orders` active + billing address set
- [x] Update `trigger()` (async) with same tax + subtotal breakdown on Order
  - Verification: Unit tests cover tax-on/off for both `triggerSync` and `triggerForPackage`

---

## Step C — RosterController: Tenant-Level Auto-Charge

- [x] Add `Feature::active('auto_replenish')` gate around entire auto-charge block
- [x] Per-dog replenish takes priority (`dog.auto_replenish_enabled` + `dog.auto_replenish_package_id`)
- [x] Tenant-level fallback: `tenant.auto_charge_at_zero_package_id` → `triggerForPackage()`
  - Verification: All 4 cases tested (per-dog priority, tenant-level fires, plan inactive, no package)

---

## Step D — SettingsController + UI

- [x] `index()`: pass `packages` (one_time type), `auto_charge_at_zero_package_id`, `can_auto_replenish`
- [x] `updateBusiness()`: validate + save `auto_charge_at_zero_package_id`
- [x] `Settings/Index.vue`: package selector below `checkin_block_at_zero` checkbox; upgrade note for free plan
  - Verification: SettingsControllerTest + visual check

---

## Review

### Summary of Changes
- New migration: `auto_charge_at_zero_package_id` on tenants
- `Tenant` model: new fillable + `autoChargePackage()` relation
- `AutoReplenishService`: refactored with shared `charge()` helper; new `triggerForPackage()` method; tax applied via `resolveTax()` on all sync charge paths
- `RosterController`: auto-charge block gated behind `auto_replenish` plan feature; tenant-level package fallback added
- `FeaturesServiceProvider` + seeders: `auto_replenish` registered as paid-plan feature
- `SettingsController`: exposes packages + auto-charge package setting
- `Settings/Index.vue`: package selector UI with plan-upgrade note

### Tests Added or Updated
- `AutoReplenishServiceTest` — 13 new tests (triggerSync + triggerForPackage × tax-on/off/no-address)
- `RosterControllerTest` — 4 new tests (plan gate + tenant-level charge paths); PlatformPlan added to setUp
- `SettingsControllerTest` (Web) — 4 new tests (index props, save/clear/reject package ID)

### Build Status
- Tests: 988 passing
- Build: Successful (3.82s)

### Notes
- Steps 5 and 8 (StripeBillingService address + BillingController) are deferred — not related to check-in auto-charge
- Tax on auto-replenish uses tenant's billing address (not customer address) — consistent with how tax is resolved for the connected account
