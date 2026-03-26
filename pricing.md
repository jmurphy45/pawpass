PawPass Platform Plan & Feature Analysis                                                                                                                    
                                                                                                                                                             
 Context                                                                                                                                                     
                                                                                                                                                             
 Analysis of the existing platform subscription tiers, all system features, and recommendations for feature gating improvements to optimize monetization and 
  value delivery at each plan level.

 ---
 Part 1: Current Platform Plans

 ┌──────────────────────┬──────┬──────────────────┬──────────────┬────────────────────┐
 │       Feature        │ Free │ Starter ($49/mo) │ Pro ($99/mo) │ Business ($199/mo) │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ add_customers        │ ✗    │ ✓                │ ✓            │ ✓                  │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ add_dogs             │ ✗    │ ✓                │ ✓            │ ✓                  │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ customer_portal      │ ✗    │ ✓                │ ✓            │ ✓                  │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ email_notifications  │ ✗    │ ✓                │ ✓            │ ✓                  │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ basic_reporting      │ ✗    │ ✓                │ ✓            │ ✓                  │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ sms_notifications    │ ✓    │ ✓                │ ✓            │ ✓                  │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ recurring_checkout   │ ✗    │ ✓                │ ✓            │ ✓                  │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ financial_reports    │ ✗    │ ✗                │ ✓            │ ✓                  │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ weekly_daily_payouts │ ✗    │ ✗                │ ✓            │ ✓                  │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ custom_branding      │ ✗    │ ✗                │ ✓            │ ✓                  │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ pwa                  │ ✗    │ ✗                │ ✓            │ ✓                  │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ white_label          │ ✗    │ ✗                │ ✗            │ ✓                  │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ unlimited_staff      │ ✗    │ ✗                │ ✗            │ ✓                  │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ priority_support     │ ✗    │ ✗                │ ✗            │ ✓                  │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ Staff Limit          │ 1    │ 5                │ 15           │ ∞                  │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ SMS Quota (segments) │ 0    │ 0                │ 500          │ 1,000              │
 ├──────────────────────┼──────┼──────────────────┼──────────────┼────────────────────┤
 │ Annual Price         │ $0   │ $470/yr          │ $950/yr      │ $1,910/yr          │
 └──────────────────────┴──────┴──────────────────┴──────────────┴────────────────────┘

 ---
 Part 2: Complete System Feature Inventory

 Ungated (available to all paying plans, not in feature flags)

 These features exist in the codebase but have no plan gating — any tenant on any plan can access them:

 ┌────────────────────────────┬─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┐
 │        Feature Area        │                                                      Capabilities                                                       │
 ├────────────────────────────┼─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
 │ Boarding & Reservations    │ Full reservation CRUD, kennel unit management, boarding report cards, occupancy dashboard, customer portal reservations │
 ├────────────────────────────┼─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
 │ Add-on Services            │ Create/manage addon types, attach addons to attendance/reservations, charge customers per-addon                         │
 ├────────────────────────────┼─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
 │ Vaccination Management     │ Create vaccination requirements, track dog vaccinations, compliance validation, block check-in on violation             │
 ├────────────────────────────┼─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
 │ Advanced Credit Ops        │ Goodwill credits, correction adjustments, dog-to-dog transfers                                                          │
 ├────────────────────────────┼─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
 │ Broadcast Notifications    │ Send custom announcements to all customers                                                                              │
 ├────────────────────────────┼─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
 │ Kennel Occupancy Dashboard │ View occupancy by date, availability tracking                                                                           │
 ├────────────────────────────┼─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
 │ Stripe Onboarding          │ Connect account creation, account links                                                                                 │
 ├────────────────────────────┼─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
 │ Basic attendance/roster    │ Check in/out, roster view                                                                                               │
 ├────────────────────────────┼─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
 │ Order/payment history      │ View payments, process refunds                                                                                          │
 └────────────────────────────┴─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────┘

 Gated Features (currently enforced)

 ┌───────────────────────────────────┬─────────────────────────┬──────────┐
 │              Feature              │          Gate           │  Plans   │
 ├───────────────────────────────────┼─────────────────────────┼──────────┤
 │ Create customers                  │ add_customers           │ Starter+ │
 ├───────────────────────────────────┼─────────────────────────┼──────────┤
 │ Create dogs                       │ add_dogs                │ Starter+ │
 ├───────────────────────────────────┼─────────────────────────┼──────────┤
 │ Customer portal                   │ customer_portal         │ Starter+ │
 ├───────────────────────────────────┼─────────────────────────┼──────────┤
 │ Email notifications               │ email_notifications     │ Starter+ │
 ├───────────────────────────────────┼─────────────────────────┼──────────┤
 │ Attendance/credit/roster reports  │ basic_reporting         │ Starter+ │
 ├───────────────────────────────────┼─────────────────────────┼──────────┤
 │ Package/staff activity reports    │ basic_reporting (owner) │ Starter+ │
 ├───────────────────────────────────┼─────────────────────────┼──────────┤
 │ Revenue/payout/LTV/credit reports │ financial_reports       │ Pro+     │
 ├───────────────────────────────────┼─────────────────────────┼──────────┤
 │ Daily/weekly payout schedules     │ weekly_daily_payouts    │ Pro+     │
 ├───────────────────────────────────┼─────────────────────────┼──────────┤
 │ Custom branding (colors, logo)    │ custom_branding         │ Pro+     │
 ├───────────────────────────────────┼─────────────────────────┼──────────┤
 │ Progressive Web App               │ pwa                     │ Pro+     │
 ├───────────────────────────────────┼─────────────────────────┼──────────┤
 │ White label                       │ white_label             │ Business │
 ├───────────────────────────────────┼─────────────────────────┼──────────┤
 │ Unlimited staff                   │ unlimited_staff         │ Business │
 ├───────────────────────────────────┼─────────────────────────┼──────────┤
 │ Subscription checkout             │ recurring_checkout      │ Starter+ │
 └───────────────────────────────────┴─────────────────────────┴──────────┘

 ---
 Part 3: Problems with the Current Plan Structure

 Problem 1: Free Plan Is Non-Functional

 - sms_notifications is on Free but the SMS quota is 0 — the feature flag is contradicted by the quota.
 - Without add_customers or add_dogs, a free tenant cannot onboard a single dog or customer.
 - The free plan is effectively a broken demo, not a useful entry point.

 Problem 2: Boarding Is a Major Feature With No Gating

 - Boarding/reservations, kennel units, and report cards represent a full second business vertical (kennel vs daycare).
 - These routes are completely ungated — a Starter plan gets the entire boarding module.
 - The business type setting (daycare_only / kennel_only / hybrid) is configurable but not connected to plan gating.

 Problem 3: Add-on Services Are Ungated Revenue Generators

 - Addon types allow businesses to charge customers extra per service (grooming, training, etc.).
 - This is a direct revenue multiplier for the daycare and currently ungated.
 - Should be a differentiator at Pro+.

 Problem 4: Pro → Business Jump Is Weak

 - Business adds only: white_label, unlimited_staff, priority_support over Pro.
 - The $100/month price jump ($199 vs $99) is hard to justify unless the business has >15 staff.
 - The differentiation needs more substance.

 Problem 5: Vaccination Management Is Ungated but Operationally Essential

 - Vaccination requirements and compliance are ungated — available on all plans.
 - This is fine; it's more of an operational hygiene feature than a premium feature.
 - Could stay ungated or be added to Starter+ explicitly.

 Problem 6: Broadcast Notifications Are Ungated

 - Sending custom announcements to all customers is a marketing/communication feature.
 - Currently ungated — Starter businesses can mass-message all customers.
 - This is arguably a Pro+ feature (marketing tool).

 ---
 Part 4: Recommended Feature Gating Changes

 New Feature Flags to Add

 ┌─────────────────────────┬─────────────────────────────────────────────────────┬──────────────────┐
 │    New Feature Flag     │                     Description                     │ Recommended Tier │
 ├─────────────────────────┼─────────────────────────────────────────────────────┼──────────────────┤
 │ boarding                │ Reservations, kennel units, report cards, occupancy │ Pro+             │
 ├─────────────────────────┼─────────────────────────────────────────────────────┼──────────────────┤
 │ addon_services          │ Create addon types, charge per-service              │ Pro+             │
 ├─────────────────────────┼─────────────────────────────────────────────────────┼──────────────────┤
 │ broadcast_notifications │ Send announcements to all customers                 │ Pro+             │
 ├─────────────────────────┼─────────────────────────────────────────────────────┼──────────────────┤
 │ vaccination_management  │ Create requirements, enforce compliance             │ Starter+         │
 ├─────────────────────────┼─────────────────────────────────────────────────────┼──────────────────┤
 │ advanced_credit_ops     │ Goodwill, correction, transfer operations           │ Starter+         │
 ├─────────────────────────┼─────────────────────────────────────────────────────┼──────────────────┤
 │ api_access              │ (Future) Direct API keys for integrations           │ Business         │
 └─────────────────────────┴─────────────────────────────────────────────────────┴──────────────────┘

 Revised Plan Matrix (Proposed)

 ┌─────────────────────────┬──────┬───────────────┬───────────┬─────────────────┐
 │         Feature         │ Free │ Starter ($49) │ Pro ($99) │ Business ($199) │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ add_customers           │ ✗    │ ✓             │ ✓         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ add_dogs                │ ✗    │ ✓             │ ✓         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ customer_portal         │ ✗    │ ✓             │ ✓         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ email_notifications     │ ✗    │ ✓             │ ✓         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ basic_reporting         │ ✗    │ ✓             │ ✓         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ sms_notifications       │ ✗    │ ✓             │ ✓         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ recurring_checkout      │ ✗    │ ✓             │ ✓         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ vaccination_management  │ ✗    │ ✓             │ ✓         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ advanced_credit_ops     │ ✗    │ ✓             │ ✓         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ financial_reports       │ ✗    │ ✗             │ ✓         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ weekly_daily_payouts    │ ✗    │ ✗             │ ✓         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ custom_branding         │ ✗    │ ✗             │ ✓         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ pwa                     │ ✗    │ ✗             │ ✓         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ boarding                │ ✗    │ ✗             │ ✓         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ addon_services          │ ✗    │ ✗             │ ✓         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ broadcast_notifications │ ✗    │ ✗             │ ✓         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ white_label             │ ✗    │ ✗             │ ✗         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ unlimited_staff         │ ✗    │ ✗             │ ✗         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ priority_support        │ ✗    │ ✗             │ ✗         │ ✓               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ Staff Limit             │ 0    │ 5             │ 15        │ ∞               │
 ├─────────────────────────┼──────┼───────────────┼───────────┼─────────────────┤
 │ SMS Quota               │ 0    │ 100           │ 500       │ 1,000           │
 └─────────────────────────┴──────┴───────────────┴───────────┴─────────────────┘

 Free Plan Fix

 - Remove sms_notifications from Free (quota 0 makes the flag meaningless).
 - OR give Free a small SMS quota (25 segments) and leave flag on.
 - Free should be a read-only demo mode (view existing data only, no creates).

 Starter Additions

 - Add vaccination_management — operationally necessary for any licensed daycare.
 - Add advanced_credit_ops (goodwill/correction/transfer) — operational tool.
 - Give a small SMS quota (100 segments) so email-first businesses can test SMS.

 Pro Additions (biggest change)

 - Add boarding — this is the full kennel/boarding vertical. Unlocking it at Pro creates a strong upgrade motivation for any business that does both daycare
  and boarding.
 - Add addon_services — letting businesses charge for grooming, training, etc. is a revenue multiplier. Gating this at Pro incentivizes upgrade.
 - Add broadcast_notifications — mass customer communication is a marketing tool, appropriate for growth-stage businesses.

 Business Tier Reinforcement

 - Keep white_label, unlimited_staff, priority_support.
 - Consider adding multi_location support if that feature is ever built.
 - Consider adding api_access (direct API keys) as a Business-exclusive.

 ---
 Part 5: Implementation Scope (if approved)

 Files to modify:
 1. database/seeders/PlatformPlanSeeder.php — add new features to plan arrays
 2. app/Providers/FeaturesServiceProvider.php — register new feature flags
 3. routes/api.php — add plan:boarding, plan:addon_services, plan:broadcast_notifications middleware to relevant route groups
 4. routes/web.php — same gating for web routes

 Routes to gate:
 - POST /api/admin/v1/reservations → plan:boarding
 - GET/POST /api/admin/v1/kennel-units → plan:boarding
 - GET/POST /api/admin/v1/reservations/{id}/report-cards → plan:boarding
 - GET /api/admin/v1/occupancy → plan:boarding
 - GET/POST /api/admin/v1/addon-types → plan:addon_services
 - POST/DELETE /api/admin/v1/attendances/{id}/addons → plan:addon_services
 - POST/DELETE /api/admin/v1/reservations/{id}/addons → plan:addon_services
 - POST /api/admin/v1/notifications/broadcast → plan:broadcast_notifications
 - POST /api/admin/v1/dogs/{dog}/credits/goodwill → plan:advanced_credit_ops
 - POST /api/admin/v1/dogs/{dog}/credits/correction → plan:advanced_credit_ops
 - POST /api/admin/v1/dogs/{dog}/credits/transfer → plan:advanced_credit_ops
 - Vaccination requirement CRUD → plan:vaccination_management

 No new feature for:
 - Customer portal reservation access (follows same tenant boarding flag)
 - Basic vaccination tracking on dogs (keep ungated — data entry)
 - Read-only kennel unit listing (keep ungated for basic visibility)

 ---
 Verification

 - Run php artisan test to confirm no regressions after plan seeder changes.
 - Manually test that a Starter plan tenant gets 403 when hitting a boarding route.
 - Confirm Pro plan tenant can access boarding/addons.
 - Check PlatformPlanSeeder produces correct feature lists via php artisan tinker.
