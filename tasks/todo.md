# Phase: Tax Collection

---

## Step 1 — Feature Flags

- [ ] Add `tax_daycare_orders` and `tax_platform_subscriptions` to `FeaturesServiceProvider`
  - Verification: `Feature::active('tax_daycare_orders')` returns false by default

---

## Step 2 — Migrations

- [ ] `2026_03_29_000001_alter_orders_add_tax_columns` — `subtotal_cents`, `tax_amount_cents`, `stripe_tax_calc_id`
- [ ] `2026_03_29_000002_alter_tenants_add_billing_address` — `billing_address jsonb nullable`
  - Verification: migrations run cleanly on both DBs

---

## Step 3 — Model Updates

- [ ] `Order` model: add tax fields to fillable + casts
- [ ] `Tenant` model: add `billing_address` to fillable + casts
  - Verification: models accept and cast new fields

---

## Step 4 — StripeService Tax Methods

- [ ] Add `calculateTax()` — calls `/v1/tax/calculations` on connected account
- [ ] Add `createTaxTransaction()` — calls `/v1/tax/transactions/create_from_calculation`
  - Verification: Unit tests pass for both methods

---

## Step 5 — StripeBillingService Updates

- [ ] `createCustomer()` — pass address when `tenant.billing_address` is set
- [ ] Add `updateCustomerAddress()` — syncs address to Stripe customer
- [ ] `createSubscription()` — add `automatic_tax` based on `tax_platform_subscriptions` flag
  - Verification: Tests verify automatic_tax.enabled toggled correctly

---

## Step 6 — OrderController Tax Logic

- [ ] Add `postal_code` + `country` to `StoreOrderRequest` (nullable)
- [ ] Calculate tax in `store()` when `tax_daycare_orders` flag is on
- [ ] Platform fee on subtotal only
- [ ] Store `subtotal_cents`, `tax_amount_cents`, `stripe_tax_calc_id` on order
- [ ] Add `taxPreview()` endpoint
- [ ] Add route for `GET portal/v1/orders/tax-preview`
  - Verification: Tests cover flag-on, flag-off, no postal_code scenarios

---

## Step 7 — Webhook: Record Tax Transaction

- [ ] In `handlePaymentIntentSucceeded()` — call `createTaxTransaction()` when `tax_calculation_id` in PI metadata
  - Verification: Test webhook with tax metadata calls the Stripe tax transaction endpoint

---

## Step 8 — BillingController Address

- [ ] Add `billing_address` validation to `subscribe()`
- [ ] Store address on tenant + sync to Stripe customer
  - Verification: Test subscribe with address stores correctly

---

## Step 9 — OrderResource Tax Fields

- [ ] Add `subtotal_amount`, `tax_amount`, `total_amount` to response
  - Verification: API response includes tax breakdown

---

## Review

### Summary of Changes
- Two feature flags: `tax_daycare_orders`, `tax_platform_subscriptions`
- Stripe Tax API integration for daycare orders (postal code → calculation → PaymentIntent)
- Stripe `automatic_tax` on platform subscriptions
- New DB columns on `orders` and `tenants`
- Tax transaction recorded after successful payment via webhook

### Tests Added or Updated
- `StripeServiceTest` — calculateTax, createTaxTransaction
- `OrderControllerTest` — tax flag on/off, subtotal/tax breakdown
- `StripeBillingServiceTest` — automatic_tax toggling, address on customer
- `BillingControllerTest` — subscribe with billing address
- `StripeWebhookControllerTest` — tax transaction on payment_intent.succeeded

### Build Status
- Tests: Pending
- Build: Pending
