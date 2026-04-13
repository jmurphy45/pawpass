# Portal Test Cases — Admin & Customer

**Legend:**
- ✅ Existing test (currently passing)
- ⬜ New test case (needs to be written)
- 🔴 Critical gap (blocks production release)
- 🟡 High priority gap
- 🔵 Medium priority gap

---

## ADMIN PORTAL

### 1. Authentication & Session

| # | Test Case | Status | File |
|---|---|---|---|
| A-AUTH-01 | Staff login with valid credentials redirects to dashboard | ✅ | `Web/Admin/LoginControllerTest` |
| A-AUTH-02 | Invalid credentials returns back with error | ✅ | `Web/Admin/LoginControllerTest` |
| A-AUTH-03 | Suspended staff is rejected by session middleware even with valid session | ✅ | `Web/Admin/LoginControllerTest` |
| A-AUTH-04 | Customer role cannot access admin dashboard | ✅ | `Web/Admin/DashboardTest` |
| A-AUTH-05 | Unauthenticated request to any admin API returns 401 | ✅ | `Admin/CustomerControllerTest` |
| A-AUTH-06 | Accept invite activates user and redirects to dashboard | ✅ | `Web/Admin/AcceptInviteControllerTest` |
| A-AUTH-07 | Accept invite returns 404 for expired token | ✅ | `Web/Admin/AcceptInviteControllerTest` |
| A-AUTH-08 | Staff invite creates pending user and dispatches notification | ✅ | `Admin/SettingsControllerTest` |
| A-AUTH-09 | Staff invite rejects duplicate email | ✅ | `Admin/SettingsControllerTest` |
| A-AUTH-10 | Staff invite is forbidden for non-owner staff | ✅ | `Admin/SettingsControllerTest` |
| A-AUTH-11 | Deactivate staff sets status to suspended | ✅ | `Admin/SettingsControllerTest` |
| A-AUTH-12 | Cannot deactivate last active business_owner | ✅ | `Web/Admin/SettingsControllerTest` |

---

### 2. Dashboard

| # | Test Case | Status | File |
|---|---|---|---|
| A-DASH-01 | Business owner can view dashboard (Inertia page renders) | ✅ | `Web/Admin/DashboardTest` |
| A-DASH-02 | Staff can view dashboard | ✅ | `Web/Admin/DashboardTest` |
| A-DASH-03 | Dashboard props include correct keys | ✅ | `Web/Admin/DashboardTest` |
| A-DASH-04 | Recent notifications are returned as flat DTO | ✅ | `Admin/ReportControllerTest` |
| A-DASH-05 | ⬜ Dashboard tenant timezone is passed to Inertia shared props | ⬜ 🔵 | — |

---

### 3. Customer Management

| # | Test Case | Status | File |
|---|---|---|---|
| A-CUST-01 | Staff can list customers scoped to tenant (paginated) | ✅ | `Admin/CustomerControllerTest` |
| A-CUST-02 | Search filters customers by name | ✅ | `Admin/CustomerControllerTest` |
| A-CUST-03 | Search filters customers by email | ✅ | `Admin/CustomerControllerTest` |
| A-CUST-04 | Create customer with email also creates linked user | ✅ | `Admin/CustomerControllerTest` |
| A-CUST-05 | Create customer without email creates customer-only record | ✅ | `Admin/CustomerControllerTest` |
| A-CUST-06 | Update modifies customer data | ✅ | `Admin/CustomerControllerTest` |
| A-CUST-07 | Show returns customer with their dogs | ✅ | `Admin/CustomerControllerTest` |
| A-CUST-08 | Cross-tenant customer returns 404 | ✅ | `Admin/CustomerControllerTest` |
| A-CUST-09 | Free tier tenant blocked from adding customer | ✅ | `Admin/PlanGateMiddlewareTest` |
| A-CUST-10 | Starter plan tenant can add customer | ✅ | `Admin/PlanGateMiddlewareTest` |
| A-CUST-11 | Request payment update sends notification | ✅ | `Admin/CustomerControllerTest` |
| A-CUST-12 | Request payment update returns 422 when customer has no portal access | ✅ | `Admin/CustomerControllerTest` |
| A-CUST-13 | Owner can charge outstanding balance | ✅ | `Admin/CustomerControllerStripeTest` |
| A-CUST-14 | Charge balance requires business_owner role | ✅ | `Admin/CustomerControllerStripeTest` |
| A-CUST-15 | Charge balance returns 422 when no balance outstanding | ✅ | `Admin/CustomerControllerStripeTest` |
| A-CUST-16 | Charge balance returns 422 when no payment method on file | ✅ | `Admin/CustomerControllerStripeTest` |
| A-CUST-17 | Store creates Stripe customer when tenant has Stripe account | ✅ | `Admin/CustomerControllerStripeTest` |
| A-CUST-18 | Store skips Stripe when tenant has no connect account | ✅ | `Admin/CustomerControllerStripeTest` |
| A-CUST-19 | ⬜ Duplicate email within same tenant returns 422 | ⬜ 🟡 | — |

---

### 4. Dog Management

| # | Test Case | Status | File |
|---|---|---|---|
| A-DOG-01 | Staff can list all dogs for tenant | ✅ | `Admin/DogControllerTest` |
| A-DOG-02 | Staff can create dog | ✅ | `Admin/DogControllerTest` |
| A-DOG-03 | Staff can update dog | ✅ | `Admin/DogControllerTest` |
| A-DOG-04 | Staff can soft delete dog | ✅ | `Admin/DogControllerTest` |
| A-DOG-05 | Create dog with invalid customer returns 404 | ✅ | `Admin/DogControllerTest` |
| A-DOG-06 | Cross-tenant dog returns 404 | ✅ | `Admin/DogControllerTest` |
| A-DOG-07 | Deleted dog not listed | ✅ | `Admin/DogControllerTest` |
| A-DOG-08 | Show dog includes recent credit ledger entries | ✅ | `Web/Admin/DogControllerTest` |
| A-DOG-09 | Show dog includes vaccination records | ✅ | `Web/Admin/DogControllerTest` |
| A-DOG-10 | Show dog exposes auto-replenish eligible flag | ✅ | `Admin/DogControllerTest` |
| A-DOG-11 | Free tier tenant blocked from adding dog | ✅ | `Admin/PlanGateMiddlewareTest` |
| A-DOG-12 | ⬜ Suspended dog does not appear on active roster | ⬜ 🔵 | — |

---

### 5. Vaccination Management

| # | Test Case | Status | File |
|---|---|---|---|
| A-VAC-01 | Staff can add vaccination to dog | ✅ | `Admin/DogVaccinationControllerTest` |
| A-VAC-02 | Staff can update vaccination | ✅ | `Admin/DogVaccinationControllerTest` |
| A-VAC-03 | Staff can delete vaccination | ✅ | `Admin/DogVaccinationControllerTest` |
| A-VAC-04 | Store requires vaccine name and date | ✅ | `Admin/DogVaccinationControllerTest` |
| A-VAC-05 | Store allows vaccine without expiry | ✅ | `Admin/DogVaccinationControllerTest` |
| A-VAC-06 | Index returns vaccinations for dog | ✅ | `Admin/DogVaccinationControllerTest` |
| A-VAC-07 | Expired vaccination reports is_valid as false | ✅ | `Admin/DogVaccinationControllerTest` |
| A-VAC-08 | Owner can add vaccination requirement | ✅ | `Admin/VaccinationRequirementControllerTest` |
| A-VAC-09 | Owner can delete vaccination requirement | ✅ | `Admin/VaccinationRequirementControllerTest` |
| A-VAC-10 | Staff cannot add/delete vaccination requirement | ✅ | `Admin/VaccinationRequirementControllerTest` |
| A-VAC-11 | Duplicate requirement returns 409 | ✅ | `Admin/VaccinationRequirementControllerTest` |
| A-VAC-12 | Cross-tenant requirement not accessible | ✅ | `Admin/VaccinationRequirementControllerTest` |

---

### 6. Package Management

| # | Test Case | Status | File |
|---|---|---|---|
| A-PKG-01 | Index returns non-archived packages for tenant only | ✅ | `Admin/PackageControllerTest` |
| A-PKG-02 | Does not return packages from other tenants | ✅ | `Admin/PackageControllerTest` |
| A-PKG-03 | Store creates one-time package | ✅ | `Admin/PackageControllerTest` |
| A-PKG-04 | Store creates unlimited package | ✅ | `Admin/PackageControllerTest` |
| A-PKG-05 | Store is forbidden for staff role | ✅ | `Admin/PackageControllerTest` |
| A-PKG-06 | Store requires credit_count for one-time | ✅ | `Admin/PackageControllerTest` |
| A-PKG-07 | Store prohibits credit_count for unlimited | ✅ | `Admin/PackageControllerTest` |
| A-PKG-08 | Store rejects unknown package type | ✅ | `Admin/PackageControllerTest` |
| A-PKG-09 | Update modifies package | ✅ | `Admin/PackageControllerTest` |
| A-PKG-10 | Update dispatches Stripe sync job when price changes | ✅ | `Admin/PackageControllerTest` |
| A-PKG-11 | Update returns 404 for other tenant's package | ✅ | `Admin/PackageControllerTest` |
| A-PKG-12 | Archive soft-deletes and deactivates package | ✅ | `Admin/PackageControllerTest` |
| A-PKG-13 | Archive returns 409 if already archived | ✅ | `Admin/PackageControllerTest` |
| A-PKG-14 | Archive is forbidden for staff | ✅ | `Admin/PackageControllerTest` |
| A-PKG-15 | One-time package creates Stripe product and price | ✅ | `Admin/PackageControllerStripeTest` |
| A-PKG-16 | Unlimited package creates Stripe product (no price) | ✅ | `Admin/PackageControllerStripeTest` |
| A-PKG-17 | Update price on one-time package archives old and creates new Stripe price | ✅ | `Admin/PackageControllerStripeTest` |
| A-PKG-18 | Package blocked by plan gate (requires Stripe onboarding) | ✅ | `Admin/PackageControllerTest` |
| A-PKG-19 | ⬜ Store subscription package creates Stripe product and recurring price | ⬜ 🟡 | — |

---

### 7. Roster / Check-In / Check-Out

| # | Test Case | Status | File |
|---|---|---|---|
| A-ROST-01 | Roster lists dogs with credit status and attendance state | ✅ | `Admin/RosterControllerTest` |
| A-ROST-02 | Roster shows checked-in state for dog already checked in | ✅ | `Admin/RosterControllerTest` |
| A-ROST-03 | Roster shows done state for dog checked out today | ✅ | `Admin/RosterControllerTest` |
| A-ROST-04 | Normal check-in creates attendance and deducts one credit | ✅ | `Admin/RosterControllerTest` |
| A-ROST-05 | Already checked-in dog returns 409 | ✅ | `Admin/RosterControllerTest` |
| A-ROST-06 | Dog with zero credits is blocked when blocking is enabled | ✅ | `Admin/RosterControllerTest` |
| A-ROST-07 | Zero credit override allows check-in when specified | ✅ | `Admin/RosterControllerTest` |
| A-ROST-08 | Dog with active unlimited pass checks in without credits | ✅ | `Admin/RosterControllerTest` |
| A-ROST-09 | Dog with expired unlimited pass is blocked at zero credits | ✅ | `Admin/RosterControllerTest` |
| A-ROST-10 | Bulk check-in processes multiple dogs | ✅ | `Admin/RosterControllerTest` |
| A-ROST-11 | Check-out sets checked_out_at on today's open attendance | ✅ | `Admin/RosterControllerTest` |
| A-ROST-12 | Check-out returns 404 when no open attendance today | ✅ | `Admin/RosterControllerTest` |
| A-ROST-13 | Check-out does not close previous day's attendance record | ✅ | `Admin/RosterControllerTest` |
| A-ROST-14 | Auto-replenish triggers and checks in when blocking disabled | ✅ | `Admin/RosterControllerTest` |
| A-ROST-15 | Auto-replenish failure blocks check-in when blocking enabled | ✅ | `Admin/RosterControllerTest` |
| A-ROST-16 | Auto-replenish skipped when blocking disabled | ✅ | `Admin/RosterControllerTest` |
| A-ROST-17 | Inactive dog cannot be checked in | ✅ | `Web/Admin/RosterControllerTest` |
| A-ROST-18 | Suspended dog cannot be checked in | ✅ | `Web/Admin/RosterControllerTest` |
| A-ROST-19 | Per-dog auto-replenish package takes priority over tenant-level package | ✅ | `Admin/RosterControllerTest` |
| A-ROST-20 | ⬜ Check-in fires credit_low notification when balance drops to threshold | ⬜ 🟡 | — |
| A-ROST-21 | ⬜ Idempotency key prevents double check-in from rapid double-click | ⬜ 🔵 | — |

---

### 8. Credit Operations

| # | Test Case | Status | File |
|---|---|---|---|
| A-CRED-01 | Goodwill adds credits and requires note | ✅ | `Admin/CreditControllerTest` |
| A-CRED-02 | Correction adjusts credits positively | ✅ | `Admin/CreditControllerTest` |
| A-CRED-03 | Correction adjusts credits negatively | ✅ | `Admin/CreditControllerTest` |
| A-CRED-04 | Correction requires note | ✅ | `Admin/CreditControllerTest` |
| A-CRED-05 | Transfer moves credits between same-customer dogs | ✅ | `Admin/CreditControllerTest` |
| A-CRED-06 | Cross-customer transfer returns 409 | ✅ | `Admin/CreditControllerTest` |
| A-CRED-07 | Missing idempotency key returns 400 | ✅ | `Admin/CreditControllerTest` |
| A-CRED-08 | Idempotency key replay returns same response | ✅ | `Admin/CreditControllerTest` |
| A-CRED-09 | Credit operations require plan:advanced_credit_ops | ✅ | `Admin/PlanGateMiddlewareTest` |
| A-CRED-10 | ⬜ Goodwill on dog with user_id=null does not crash (null-guard) | ⬜ 🟡 | — |

---

### 9. Payments & Refunds

| # | Test Case | Status | File |
|---|---|---|---|
| A-PAY-01 | Index returns tenant orders with customer and package | ✅ | `Admin/PaymentControllerTest` |
| A-PAY-02 | Refund marks order refunded and removes all credits | ✅ | `Admin/PaymentControllerTest` |
| A-PAY-03 | Refund already-refunded order returns 409 | ✅ | `Admin/PaymentControllerTest` |
| A-PAY-04 | Refund from other tenant returns 404 | ✅ | `Admin/PaymentControllerTest` |
| A-PAY-05 | Price snapshot is unaffected by later package price changes | ✅ | `Admin/PaymentControllerTest` |
| A-PAY-06 | ⬜ Refund when Stripe call fails returns 502 (Stripe error handling) | ⬜ 🔴 | — |

---

### 10. Boarding — Kennel Units

| # | Test Case | Status | File |
|---|---|---|---|
| A-KU-01 | Index returns active + inactive units for tenant | ✅ | `Admin/KennelUnitControllerTest` |
| A-KU-02 | Does not return other tenant's units | ✅ | `Admin/KennelUnitControllerTest` |
| A-KU-03 | Owner can create kennel unit | ✅ | `Admin/KennelUnitControllerTest` |
| A-KU-04 | Owner can update kennel unit | ✅ | `Admin/KennelUnitControllerTest` |
| A-KU-05 | Owner can delete unit with no active reservations | ✅ | `Admin/KennelUnitControllerTest` |
| A-KU-06 | Delete blocked when active reservations exist | ✅ | `Admin/KennelUnitControllerTest` |
| A-KU-07 | Staff can read units but not write | ✅ | `Admin/KennelUnitControllerTest` |
| A-KU-08 | Requires plan:boarding to write | ✅ | `Admin/KennelUnitControllerTest` |

---

### 11. Boarding — Reservations

| # | Test Case | Status | File |
|---|---|---|---|
| A-RES-01 | Index returns reservations for tenant | ✅ | `Admin/ReservationControllerTest` |
| A-RES-02 | Index can filter by status | ✅ | `Admin/ReservationControllerTest` |
| A-RES-03 | Index can filter by date | ✅ | `Admin/ReservationControllerTest` |
| A-RES-04 | Index cross-tenant isolation | ✅ | `Admin/ReservationControllerTest` |
| A-RES-05 | Store creates reservation with unit assigned | ✅ | `Admin/ReservationControllerTest` |
| A-RES-06 | Store creates reservation without unit | ✅ | `Admin/ReservationControllerTest` |
| A-RES-07 | Store rejects overlapping reservation | ✅ | `Admin/ReservationControllerTest` |
| A-RES-08 | Store denormalizes customer_id from dog | ✅ | `Admin/ReservationControllerTest` |
| A-RES-09 | Show returns reservation with relationships | ✅ | `Admin/ReservationControllerTest` |
| A-RES-10 | Update can change status to confirmed | ✅ | `Admin/ReservationControllerTest` |
| A-RES-11 | Update to cancelled releases uncaptured hold | ✅ | `Admin/ReservationControllerTest` |
| A-RES-12 | Update to checked_in captures deposit hold | ✅ | `Admin/ReservationControllerTest` |
| A-RES-13 | Update rejects overlapping unit change | ✅ | `Admin/ReservationControllerTest` |
| A-RES-14 | Destroy succeeds for pending reservation | ✅ | `Admin/ReservationControllerTest` |
| A-RES-15 | Destroy blocked for checked-in reservation | ✅ | `Admin/ReservationControllerTest` |
| A-RES-16 | Store blocked when dog missing required vaccine | ✅ | `Admin/ReservationControllerTest` |
| A-RES-17 | Store allows override of vaccination check | ✅ | `Admin/ReservationControllerTest` |
| A-RES-18 | Excludes cancelled reservations from occupancy | ✅ | `Admin/ReservationControllerTest` |
| A-RES-19 | ⬜ Store returns 422 when ends_at <= starts_at | ⬜ 🔴 | — |
| A-RES-20 | ⬜ Store rejected when reservation spans > allowed max nights | ⬜ 🔵 | — |

---

### 12. Boarding — Operations (Web)

| # | Test Case | Status | File |
|---|---|---|---|
| A-BRD-01 | Staff can confirm a pending reservation | ✅ | `Web/Admin/BoardingControllerTest` |
| A-BRD-02 | Staff can check in a confirmed reservation | ✅ | `Web/Admin/BoardingControllerTest` |
| A-BRD-03 | Check-in captures Stripe hold | ✅ | `Web/Admin/BoardingControllerTest` |
| A-BRD-04 | Staff can check out a checked-in reservation | ✅ | `Web/Admin/BoardingControllerTest` |
| A-BRD-05 | Checkout charges addons at checkout | ✅ | `Web/Admin/BoardingControllerTest` |
| A-BRD-06 | Checkout charges balance to saved card | ✅ | `Web/Admin/BoardingControllerTest` |
| A-BRD-07 | Checkout creates pending order when no card on file | ✅ | `Web/Admin/BoardingControllerTest` |
| A-BRD-08 | Checkout with zero balance skips Stripe | ✅ | `Web/Admin/BoardingControllerTest` |
| A-BRD-09 | Staff can cancel a reservation | ✅ | `Web/Admin/BoardingControllerTest` |
| A-BRD-10 | Cancel releases Stripe hold | ✅ | `Web/Admin/BoardingControllerTest` |
| A-BRD-11 | Checkout with date before starts_at returns error | ✅ | `Web/Admin/BoardingControllerTest` |
| A-BRD-12 | Invalid reservation transition returns redirect with error | ✅ | `Web/Admin/BoardingControllerTest` |
| A-BRD-13 | ⬜ Checkout when Stripe capture fails rolls back state | ⬜ 🔴 | — |

---

### 13. Boarding — Report Cards & Add-ons

| # | Test Case | Status | File |
|---|---|---|---|
| A-RC-01 | Store requires report date | ✅ | `Admin/BoardingReportCardControllerTest` |
| A-RC-02 | Store upserts card for same date | ✅ | `Admin/BoardingReportCardControllerTest` |
| A-RC-03 | Index returns cards sorted by date | ✅ | `Admin/BoardingReportCardControllerTest` |
| A-RC-04 | Destroy rejects wrong reservation | ✅ | `Admin/BoardingReportCardControllerTest` |
| A-RC-05 | Add-on created with correct price snapshot | ✅ | `Admin/ReservationAddonControllerTest` |
| A-RC-06 | Add-on rejects boarding-only type on attendance | ✅ | `Admin/AttendanceAddonControllerTest` |
| A-RC-07 | Attendance add-on charges immediately when already checked out | ✅ | `Web/Admin/BoardingControllerTest` |
| A-RC-08 | Destroy add-on blocked when reservation checked out | ✅ | `Web/Admin/BoardingControllerTest` |

---

### 14. Occupancy Dashboard

| # | Test Case | Status | File |
|---|---|---|---|
| A-OCC-01 | Occupancy dashboard renders Inertia page | ✅ | `Web/Admin/BoardingControllerTest` |
| A-OCC-02 | Occupancy contains units and date range props | ✅ | `Web/Admin/BoardingControllerTest` |
| A-OCC-03 | Returns units with overlapping reservations | ✅ | `Admin/OccupancyControllerTest` |
| A-OCC-04 | Excludes non-overlapping reservations | ✅ | `Admin/OccupancyControllerTest` |
| A-OCC-05 | ⬜ Handles reservations with null starts_at/ends_at without crashing | ⬜ 🔵 | — |

---

### 15. Add-On Services

| # | Test Case | Status | File |
|---|---|---|---|
| A-SVC-01 | Owner can create addon type | ✅ | `Admin/AddonTypeControllerTest` |
| A-SVC-02 | Owner can update addon type | ✅ | `Admin/AddonTypeControllerTest` |
| A-SVC-03 | Owner can deactivate addon type | ✅ | `Admin/AddonTypeControllerTest` |
| A-SVC-04 | Owner can delete unused addon type | ✅ | `Admin/AddonTypeControllerTest` |
| A-SVC-05 | Cannot delete addon type in use | ✅ | `Admin/AddonTypeControllerTest` |
| A-SVC-06 | Staff cannot create/update addon types | ✅ | `Admin/AddonTypeControllerTest` |
| A-SVC-07 | Cross-tenant addon type not accessible | ✅ | `Admin/AddonTypeControllerTest` |
| A-SVC-08 | Index filters by context (daycare/boarding/both) | ✅ | `Admin/AddonTypeControllerTest` |

---

### 16. Promotions

| # | Test Case | Status | File |
|---|---|---|---|
| A-PROMO-01 | Owner can create promotion | ✅ | `Web/Admin/PromotionControllerTest` |
| A-PROMO-02 | Owner can deactivate promotion | ✅ | `Web/Admin/PromotionControllerTest` |
| A-PROMO-03 | Owner can delete promotion | ✅ | `Web/Admin/PromotionControllerTest` |
| A-PROMO-04 | Percentage discount over 100 is rejected | ✅ | `Web/Admin/PromotionControllerTest` |
| A-PROMO-05 | Code is uppercased on store | ✅ | `Web/Admin/PromotionControllerTest` |
| A-PROMO-06 | Staff cannot create promotion | ✅ | `Web/Admin/PromotionControllerTest` |

---

### 17. Reports

| # | Test Case | Status | File |
|---|---|---|---|
| A-RPT-01 | Revenue returns 200 for pro owner | ✅ | `Admin/ReportControllerTest` |
| A-RPT-02 | Revenue returns 403 for starter owner | ✅ | `Admin/ReportControllerTest` |
| A-RPT-03 | Revenue returns 403 for staff on pro plan | ✅ | `Admin/ReportControllerTest` |
| A-RPT-04 | Revenue returns correct data | ✅ | `Admin/ReportControllerTest` |
| A-RPT-05 | Revenue uses cached result on second call | ✅ | `Admin/ReportControllerTest` |
| A-RPT-06 | Revenue rejects date range over 90 days | ✅ | `Admin/ReportControllerTest` |
| A-RPT-07 | Revenue CSV returns correct content type | ✅ | `Admin/ReportControllerTest` |
| A-RPT-08 | Attendance returns 200 for starter | ✅ | `Admin/ReportControllerTest` |
| A-RPT-09 | Credit status returns 200 for staff | ✅ | `Admin/ReportControllerTest` |
| A-RPT-10 | Roster history returns 200 for starter | ✅ | `Admin/ReportControllerTest` |
| A-RPT-11 | Packages returns 200 for starter owner | ✅ | `Admin/ReportControllerTest` |
| A-RPT-12 | Staff activity returns 200 for starter owner | ✅ | `Admin/ReportControllerTest` |
| A-RPT-13 | LTV returns 200 for pro owner | ✅ | `Admin/ReportControllerTest` |
| A-RPT-14 | Payout forecast returns 200 for pro owner | ✅ | `Admin/ReportControllerTest` |
| A-RPT-15 | Free tier blocked from revenue and LTV reports | ✅ | `Admin/ReportControllerTest` |

---

### 18. Settings

| # | Test Case | Status | File |
|---|---|---|---|
| A-SET-01 | Owner can view and update business settings | ✅ | `Admin/SettingsControllerTest` |
| A-SET-02 | Business settings forbidden for staff | ✅ | `Admin/SettingsControllerTest` |
| A-SET-03 | Update rejects invalid timezone | ✅ | `Admin/SettingsControllerTest` |
| A-SET-04 | Update rejects invalid primary color | ✅ | `Admin/SettingsControllerTest` |
| A-SET-05 | Owner can update notification settings | ✅ | `Admin/SettingsControllerTest` |
| A-SET-06 | Owner can upload logo | ✅ | `Web/Admin/LogoControllerTest` |
| A-SET-07 | Owner can delete logo | ✅ | `Web/Admin/LogoControllerTest` |
| A-SET-08 | Upload rejects non-image or file over 2 MB | ✅ | `Web/Admin/LogoControllerTest` |
| A-SET-09 | Re-upload deletes old S3 file | ✅ | `Web/Admin/LogoControllerTest` |
| A-SET-10 | Settings index includes billing address fields | ✅ | `Web/Admin/SettingsControllerTest` |
| A-SET-11 | Settings index includes auto-charge fields | ✅ | `Web/Admin/SettingsControllerTest` |
| A-SET-12 | Update auto-charge package id | ✅ | `Web/Admin/SettingsControllerTest` |
| A-SET-13 | ⬜ Owner can update payout schedule | ⬜ 🔵 | — |

---

### 19. Billing (Platform Plan Subscription)

| # | Test Case | Status | File |
|---|---|---|---|
| A-BILL-01 | Owner can view billing info | ✅ | `Web/Admin/BillingControllerTest` |
| A-BILL-02 | Subscribe creates Stripe customer and subscription | ✅ | `Admin/BillingControllerTest` |
| A-BILL-03 | Subscribe uses price ID from platform_plans table | ✅ | `Admin/BillingControllerTest` |
| A-BILL-04 | Subscribe skips customer creation if already exists | ✅ | `Admin/BillingControllerTest` |
| A-BILL-05 | Subscribe rejects plan not in platform_plans | ✅ | `Admin/BillingControllerTest` |
| A-BILL-06 | Subscribe validates billing cycle | ✅ | `Admin/BillingControllerTest` |
| A-BILL-07 | Subscribe returns 502 on Stripe API error | ✅ | `Admin/BillingControllerTest` |
| A-BILL-08 | Upgrade changes plan | ✅ | `Admin/BillingControllerTest` |
| A-BILL-09 | Upgrade returns 502 on Stripe API error | ✅ | `Admin/BillingControllerTest` |
| A-BILL-10 | Cancel sets cancel_at_period_end | ✅ | `Admin/BillingControllerTest` |
| A-BILL-11 | Cancel returns 502 on Stripe API error | ✅ | `Admin/BillingControllerTest` |
| A-BILL-12 | Invoices returns invoice list | ✅ | `Admin/BillingControllerTest` |
| A-BILL-13 | Invoices returns 502 on Stripe API error | ✅ | `Admin/BillingControllerTest` |
| A-BILL-14 | Portal URL returns Stripe session URL | ✅ | `Admin/BillingControllerTest` |
| A-BILL-15 | Portal URL returns 502 on Stripe API error | ✅ | `Admin/BillingControllerTest` |
| A-BILL-16 | Staff cannot access billing | ✅ | `Web/Admin/BillingControllerTest` |

---

### 20. Stripe Onboarding

| # | Test Case | Status | File |
|---|---|---|---|
| A-ONB-01 | Owner can create Stripe Connect account | ✅ | `Admin/OnboardingControllerTest` |
| A-ONB-02 | Owner can create account link | ✅ | `Admin/OnboardingControllerTest` |
| A-ONB-03 | Create Connect account returns 409 if already connected | ✅ | `Admin/OnboardingControllerTest` |
| A-ONB-04 | Create account link returns 422 when no Connect account | ✅ | `Admin/OnboardingControllerTest` |
| A-ONB-05 | Account link requires refresh and return URLs | ✅ | `Admin/OnboardingControllerTest` |
| A-ONB-06 | Staff cannot create Connect account or account link | ✅ | `Admin/OnboardingControllerTest` |
| A-ONB-07 | Connect account passes billing address when available | ✅ | `Admin/OnboardingControllerTest` |

---

### 21. Tax

| # | Test Case | Status | File |
|---|---|---|---|
| A-TAX-01 | Owner can view tax page | ✅ | `Web/Admin/TaxControllerTest` |
| A-TAX-02 | Staff cannot access tax page | ✅ | `Web/Admin/TaxControllerTest` |
| A-TAX-03 | Account session returns client secret | ✅ | `Web/Admin/TaxControllerTest` |
| A-TAX-04 | Account session returns 422 when no Stripe account | ✅ | `Web/Admin/TaxControllerTest` |

---

### 22. Broadcast Notifications

| # | Test Case | Status | File |
|---|---|---|---|
| A-BCT-01 | Owner can broadcast notification | ✅ | `Admin/BroadcastNotificationControllerTest` |
| A-BCT-02 | Staff can also broadcast | ✅ | `Admin/BroadcastNotificationControllerTest` |
| A-BCT-03 | Validation requires subject, body, channels | ✅ | `Admin/BroadcastNotificationControllerTest` |
| A-BCT-04 | Validation rejects invalid channel | ✅ | `Admin/BroadcastNotificationControllerTest` |
| A-BCT-05 | SMS channel requires billing configured | ✅ | `Admin/BroadcastNotificationControllerTest` |
| A-BCT-06 | Requires plan:broadcast_notifications | ✅ | `Admin/BroadcastNotificationControllerTest` |

---

## CUSTOMER PORTAL

### 23. Authentication (Customer)

| # | Test Case | Status | File |
|---|---|---|---|
| C-AUTH-01 | Login page renders | ✅ | `Web/Portal/Auth/LoginTest` |
| C-AUTH-02 | Successful registration returns 202 and message | ✅ | `Portal/RegisterTest` |
| C-AUTH-03 | Duplicate email within tenant returns 422 | ✅ | `Portal/RegisterTest` |
| C-AUTH-04 | Same email on different tenant succeeds | ✅ | `Portal/RegisterTest` |
| C-AUTH-05 | Short password returns 422 | ✅ | `Portal/RegisterTest` |
| C-AUTH-06 | Registration with phone stores phone number | ✅ | `Portal/RegisterTest` |
| C-AUTH-07 | Guest accessing protected page redirects to portal login | ✅ | `Web/PortalAuthTest` |
| C-AUTH-08 | Unauthenticated returns 401 on API routes | ✅ | `Portal/OrderControllerTest` |
| C-AUTH-09 | Logout redirects to portal login | ✅ | `Web/PortalAuthTest` |
| C-AUTH-10 | Authenticated customer can access dashboard | ✅ | `Web/PortalAuthTest` |
| C-AUTH-11 | User and customer records are correctly linked on registration | ✅ | `Portal/RegisterTest` |
| C-AUTH-12 | ⬜ Magic link login allows passwordless sign-in | ⬜ 🟡 | — |
| C-AUTH-13 | ⬜ Expired magic link returns error page | ⬜ 🟡 | — |

---

### 24. Dog Management (Customer)

| # | Test Case | Status | File |
|---|---|---|---|
| C-DOG-01 | Customer can list their dogs | ✅ | `Portal/DogControllerTest` |
| C-DOG-02 | Customer can create a dog | ✅ | `Portal/DogControllerTest` |
| C-DOG-03 | Create dog requires name | ✅ | `Web/Portal/DogControllerTest` |
| C-DOG-04 | Customer can view their own dog | ✅ | `Portal/DogControllerTest` |
| C-DOG-05 | Customer can update their dog | ✅ | `Portal/DogControllerTest` |
| C-DOG-06 | Customer cannot view another customer's dog | ✅ | `Portal/DogControllerTest` |
| C-DOG-07 | Customer cannot update another customer's dog | ✅ | `Portal/DogControllerTest` |
| C-DOG-08 | Soft-deleted dog returns 404 | ✅ | `Portal/DogControllerTest` |
| C-DOG-09 | Customer can view dog credit ledger (paginated) | ✅ | `Portal/DogControllerTest` |
| C-DOG-10 | Cannot view credit ledger of another customer's dog | ✅ | `Portal/DogControllerTest` |
| C-DOG-11 | Customer can add vaccination to own dog | ✅ | `Web/Portal/DogControllerTest` |
| C-DOG-12 | Customer can delete own dog's vaccination | ✅ | `Web/Portal/DogControllerTest` |
| C-DOG-13 | Customer cannot add/delete vaccination for another customer's dog | ✅ | `Web/Portal/DogControllerTest` |

---

### 25. Package Browsing

| # | Test Case | Status | File |
|---|---|---|---|
| C-PKG-01 | Returns active packages only | ✅ | `Portal/PackageControllerTest` |
| C-PKG-02 | Does not return packages from other tenants | ✅ | `Portal/PackageControllerTest` |
| C-PKG-03 | Returns expected package fields | ✅ | `Portal/PackageControllerTest` |
| C-PKG-04 | Unlimited package is visible when active | ✅ | `Portal/PackageControllerTest` |
| C-PKG-05 | ⬜ Archived package does not appear in list | ⬜ 🟡 | — |

---

### 26. One-Time Purchase (Orders)

| # | Test Case | Status | File |
|---|---|---|---|
| C-ORD-01 | Creates order and returns Stripe client_secret | ✅ | `Portal/OrderControllerTest` |
| C-ORD-02 | Creates Stripe customer when none exists | ✅ | `Portal/OrderControllerTest` |
| C-ORD-03 | Reuses existing Stripe customer | ✅ | `Portal/OrderControllerTest` |
| C-ORD-04 | Inactive dog is rejected in order | ✅ | `Portal/OrderControllerTest` |
| C-ORD-05 | Suspended dog is rejected in order | ✅ | `Portal/OrderControllerTest` |
| C-ORD-06 | Dog from different customer returns 422 | ✅ | `Portal/OrderControllerTest` |
| C-ORD-07 | Archived package returns 409 | ✅ | `Portal/OrderControllerTest` |
| C-ORD-08 | Subscription package returns 422 (wrong endpoint) | ✅ | `Portal/OrderControllerTest` |
| C-ORD-09 | Missing idempotency key returns 400 | ✅ | `Portal/OrderControllerTest` |
| C-ORD-10 | Idempotency replay returns same order without second Stripe call | ✅ | `Portal/OrderControllerTest` |
| C-ORD-11 | Payment intent metadata includes enriched fields | ✅ | `Portal/OrderControllerMetadataTest` |
| C-ORD-12 | Payment intent restricts to card and bank payment methods | ✅ | `Portal/OrderControllerTest` |
| C-ORD-13 | Multi-dog order creates order with multiple order_dogs | ✅ | `Web/Portal/PurchaseControllerStripeTest` |
| C-ORD-14 | Successful order records first_purchase tenant event | ✅ | `Portal/OrderControllerTest` |
| C-ORD-15 | Tax calculated and added to payment intent when flag active | ✅ | `Web/Portal/PurchaseControllerStripeTest` |
| C-ORD-16 | Tax skipped when flag inactive or no billing address | ✅ | `Web/Portal/PurchaseControllerStripeTest` |
| C-ORD-17 | ⬜ Backend confirm endpoint — 500 from credit service returns non-200 to client | ⬜ 🔴 | — |
| C-ORD-18 | ⬜ Order exceeds dog limit returns 422 | ⬜ 🟡 | — |
| C-ORD-19 | ⬜ Concurrent orders with same idempotency key — only one creates PaymentIntent | ⬜ 🔵 | — |

---

### 27. Promotions

| # | Test Case | Status | File |
|---|---|---|---|
| C-PROMO-01 | Valid promo code applies discount | ✅ | `Portal/PromotionControllerTest` |
| C-PROMO-02 | Invalid promo code returns invalid response | ✅ | `Portal/PromotionControllerTest` |
| C-PROMO-03 | Promo check is case-insensitive | ✅ | `Portal/PromotionControllerTest` |
| C-PROMO-04 | Order with valid promo applies discount | ✅ | `Portal/OrderControllerTest` |
| C-PROMO-05 | Order with invalid promo returns 422 | ✅ | `Portal/OrderControllerTest` |

---

### 28. Subscriptions (Customer)

| # | Test Case | Status | File |
|---|---|---|---|
| C-SUB-01 | Store creates subscription and returns client_secret | ✅ | `Portal/SubscriptionControllerTest` |
| C-SUB-02 | Store calls createCustomer with correct params | ✅ | `Portal/SubscriptionControllerTest` |
| C-SUB-03 | Store reuses existing Stripe customer id | ✅ | `Portal/SubscriptionControllerTest` |
| C-SUB-04 | Archived package returns 409 | ✅ | `Portal/SubscriptionControllerTest` |
| C-SUB-05 | Non-subscription package returns 422 | ✅ | `Portal/SubscriptionControllerTest` |
| C-SUB-06 | Dog from different customer returns 422 | ✅ | `Portal/SubscriptionControllerTest` |
| C-SUB-07 | Already subscribed returns 409 | ✅ | `Portal/SubscriptionControllerTest` |
| C-SUB-08 | Index returns customer subscriptions | ✅ | `Portal/SubscriptionControllerTest` |
| C-SUB-09 | Cancel active subscription sets cancelled_at | ✅ | `Portal/SubscriptionControllerTest` |
| C-SUB-10 | Cancel non-active subscription returns 409 | ✅ | `Portal/SubscriptionControllerTest` |
| C-SUB-11 | ⬜ New subscription stores with status = pending (not active) | ⬜ 🔴 | — |
| C-SUB-12 | ⬜ Cancel attempted on pending subscription returns 409 NOT_CANCELLABLE | ⬜ 🟡 | — |
| C-SUB-13 | ⬜ Auto-replenish subscription can be cancelled via portal web route | ⬜ 🔵 | — |

---

### 29. Attendance History (Customer)

| # | Test Case | Status | File |
|---|---|---|---|
| C-ATT-01 | Customer can list their attendance (all dogs) | ✅ | `Portal/AttendanceTest` |
| C-ATT-02 | Attendance does not include other customers' dogs | ✅ | `Portal/AttendanceTest` |
| C-ATT-03 | Attendance includes multiple dogs | ✅ | `Portal/AttendanceTest` |
| C-ATT-04 | Attendance is paginated | ✅ | `Portal/AttendanceTest` |

---

### 30. Notifications (Customer)

| # | Test Case | Status | File |
|---|---|---|---|
| C-NOTIF-01 | Index returns notifications for user | ✅ | `Portal/NotificationInboxTest` |
| C-NOTIF-02 | Index type is human-readable (not PHP class name) | ✅ | `Portal/NotificationInboxTest` |
| C-NOTIF-03 | Count returns unread count | ✅ | `Portal/NotificationInboxTest` |
| C-NOTIF-04 | Mark read sets read_at | ✅ | `Portal/NotificationInboxTest` |
| C-NOTIF-05 | Mark read on unknown returns 404 | ✅ | `Portal/NotificationInboxTest` |
| C-NOTIF-06 | Mark read cannot access other user's notification | ✅ | `Portal/NotificationInboxTest` |
| C-NOTIF-07 | Read all marks all unread as read | ✅ | `Portal/NotificationInboxTest` |
| C-NOTIF-08 | Get notification prefs returns empty by default | ✅ | `Portal/NotificationPrefsTest` |
| C-NOTIF-09 | Put updates existing preference | ✅ | `Portal/NotificationPrefsTest` |
| C-NOTIF-10 | Put upserts preferences | ✅ | `Portal/NotificationPrefsTest` |
| C-NOTIF-11 | Put rejects in_app channel (always on) | ✅ | `Portal/NotificationPrefsTest` |

---

### 31. Boarding Reservations (Customer)

| # | Test Case | Status | File |
|---|---|---|---|
| C-BRD-01 | Customer can create a reservation for their own dog | ✅ | `Portal/ReservationControllerTest` |
| C-BRD-02 | Customer can list their reservations | ✅ | `Portal/ReservationControllerTest` |
| C-BRD-03 | Customer can view their own reservation | ✅ | `Portal/ReservationControllerTest` |
| C-BRD-04 | Customer cannot view another customer's reservation | ✅ | `Portal/ReservationControllerTest` |
| C-BRD-05 | Customer cannot create reservation for another customer's dog | ✅ | `Portal/ReservationControllerTest` |
| C-BRD-06 | Customer can cancel a pending reservation | ✅ | `Portal/ReservationControllerTest` |
| C-BRD-07 | Customer cannot cancel a confirmed reservation | ✅ | `Portal/ReservationControllerTest` |
| C-BRD-08 | Customer cannot cancel another customer's reservation | ✅ | `Portal/ReservationControllerTest` |
| C-BRD-09 | Create returns 409 when unit not available | ✅ | `Portal/ReservationControllerTest` |
| C-BRD-10 | Create returns 422 when vaccination incomplete | ✅ | `Portal/ReservationControllerTest` |
| C-BRD-11 | Store with deposit creates hold and returns client_secret | ✅ | `Portal/ReservationControllerTest` |
| C-BRD-12 | Store without deposit returns null client_secret | ✅ | `Portal/ReservationControllerTest` |
| C-BRD-13 | Cancel with Stripe PaymentIntent releases hold | ✅ | `Portal/ReservationControllerTest` |
| C-BRD-14 | Daycare-only tenant returns 403 on boarding route | ✅ | `Portal/ReservationControllerTest` |
| C-BRD-15 | Inactive kennel units excluded from available units | ✅ | `Portal/KennelUnitAvailabilityTest` |
| C-BRD-16 | Available units exclude conflicting reservations | ✅ | `Portal/KennelUnitAvailabilityTest` |
| C-BRD-17 | ⬜ Store returns 422 when ends_at <= starts_at | ⬜ 🔴 | — |
| C-BRD-18 | ⬜ Store is not atomic — Stripe failure leaves orphaned reservation | ⬜ 🔴 | — (known bug) |
| C-BRD-19 | ⬜ DB transaction wraps reservation + Stripe call — rollback on Stripe failure | ⬜ 🔴 | — (fix needed) |
| C-BRD-20 | ⬜ Index does not return reservations from other tenants | ⬜ 🟡 | — |

---

### 32. Account (Customer)

| # | Test Case | Status | File |
|---|---|---|---|
| C-ACC-01 | Show returns account data | ✅ | `Portal/AccountTest` |
| C-ACC-02 | Update name updates both user and customer | ✅ | `Portal/AccountTest` |
| C-ACC-03 | Update email | ✅ | `Portal/AccountTest` |
| C-ACC-04 | Update with no fields returns current data | ✅ | `Portal/AccountTest` |
| C-ACC-05 | Unauthenticated cannot access account | ✅ | `Portal/AccountTest` |
| C-ACC-06 | Show includes vaccination data for dogs | ✅ | `Web/Portal/DogControllerTest` |
| C-ACC-07 | ⬜ Update password requires current password confirmation | ⬜ 🟡 | — |

---

### 33. Order History & Receipts (Customer)

| # | Test Case | Status | File |
|---|---|---|---|
| C-HIST-01 | Index returns customer orders | ✅ | `Portal/OrderControllerTest` |
| C-HIST-02 | Index is paginated | ✅ | `Portal/OrderControllerTest` |
| C-HIST-03 | Receipt PDF streams for paid order | ✅ | `Web/Portal/OrderReceiptControllerTest` |
| C-HIST-04 | Receipt includes subtotal and tax when order has tax | ✅ | `Web/Portal/OrderReceiptControllerTest` |
| C-HIST-05 | Returns 403 if order belongs to different customer | ✅ | `Web/Portal/OrderReceiptControllerTest` |
| C-HIST-06 | Returns 404 if order not paid | ✅ | `Web/Portal/OrderReceiptControllerTest` |
| C-HIST-07 | PDF still generated when Stripe returns no charge | ✅ | `Web/Portal/OrderReceiptControllerTest` |

---

## WEBHOOK TEST COVERAGE

### 34. Stripe Payment Webhooks

| # | Test Case | Status | File |
|---|---|---|---|
| W-01 | payment_intent.succeeded issues credits from order | ✅ | `Webhooks/StripeWebhookTest` |
| W-02 | payment_intent.failed marks order failed | ✅ | `Webhooks/StripeWebhookTest` |
| W-03 | Replayed event_id returns 200 but does not process twice | ✅ | `Webhooks/StripeWebhookTest` |
| W-04 | setup_intent.succeeded transitions subscription pending → active | ✅ | `Webhooks/StripeWebhookSubscriptionTest` |
| W-05 | setup_intent.succeeded ignores missing metadata | ✅ | `Webhooks/StripeWebhookSubscriptionTest` |
| W-06 | setup_intent.succeeded ignores unknown subscription_id | ✅ | `Webhooks/StripeWebhookSubscriptionTest` |
| W-07 | setup_intent.succeeded ignores already-active subscription | ✅ | `Webhooks/StripeWebhookSubscriptionTest` |
| W-08 | payment_intent.amount_capturable_updated creates deposit hold | ✅ | `Webhooks/StripeDepositWebhookTest` |
| W-09 | charge.dispute.created marks payment disputed | ✅ | `Webhooks/StripeWebhookTest` |
| W-10 | account.updated marks tenant Stripe onboarded | ✅ | `Webhooks/StripeWebhookAccountUpdatedTest` |

### 35. Stripe Billing Webhooks

| # | Test Case | Status | File |
|---|---|---|---|
| W-11 | customer.subscription.created creates tenant subscription | ✅ | `Webhooks/StripeBillingWebhookTest` |
| W-12 | customer.subscription.updated updates tenant plan status | ✅ | `Webhooks/StripeBillingWebhookTest` |
| W-13 | invoice.payment_succeeded marks tenant active | ✅ | `Webhooks/StripeBillingWebhookTest` |
| W-14 | invoice.payment_failed marks tenant past_due | ✅ | `Webhooks/StripeBillingWebhookTest` |
| W-15 | customer.subscription.deleted downgrades tenant to free_tier | ✅ | `Webhooks/StripeBillingWebhookTest` |
| W-16 | ⬜ Billing webhook replayed event_id does not double-process | ⬜ 🔴 | — |

---

## GAP SUMMARY — New Tests Required

| Priority | Count | IDs |
|---|---|---|
| 🔴 Critical | 8 | A-PAY-06, A-BRD-13, A-RES-19, C-BRD-17, C-BRD-18→19, C-ORD-17, C-SUB-11, W-16 |
| 🟡 High | 7 | A-CUST-19, A-ROST-20, A-CRED-10, A-PKG-19, C-AUTH-12→13, C-PKG-05, C-ACC-07 |
| 🔵 Medium | 5 | A-DASH-05, A-DOG-12, A-OCC-05, A-ROST-21, A-SET-13, C-ORD-19, C-BRD-20, C-SUB-13 |

**Total existing tests mapped:** ~220
**New tests to write:** ~20 (8 critical, 7 high, 5+ medium)
