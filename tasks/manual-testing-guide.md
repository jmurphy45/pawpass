# PawPass — Pre-Production Manual Testing Guide

This document covers all user-facing flows with positive (happy path) and negative (edge/error) test cases. Work through each section on a staging environment with real Stripe test-mode credentials before going live.

---

## Setup Checklist (Before Starting)

- [ ] Stripe is in **test mode** — use card `4242 4242 4242 4242`, exp any future date, CVC any 3 digits
- [ ] Stripe Connect Express onboarding uses test mode flows
- [ ] Email delivery (Resend) is pointed at a test inbox or catch-all
- [ ] SMS (Twilio) is pointed at a verified test number
- [ ] Queue worker is running (`composer dev` or separate `queue:work`)
- [ ] A test tenant subdomain is accessible (e.g. `testdaycare.pawpass.com`)
- [ ] Platform admin is accessible at `platform.pawpass.com`

---

## 1. Public Pages

### 1.1 Home Page
**Positive**
- [ ] `pawpass.com/` loads without error
- [ ] "Find a Daycare" and "Register your business" CTAs are visible and link correctly
- [ ] Tenant subdomain dropdown (if present) filters and navigates to the correct subdomain login

**Negative**
- [ ] Non-existent path (e.g. `/foobar`) returns a clean 404 — not a stack trace

---

### 1.2 Find a Daycare Directory
**Positive**
- [ ] `pawpass.com/find-a-daycare` loads a list of registered daycares
- [ ] Filtering by state/city (`/find-a-daycare/tx/austin`) narrows results correctly
- [ ] Clicking a daycare card navigates to the correct subdomain

**Negative**
- [ ] A state/city with no results shows an empty-state message, not an error
- [ ] Invalid state slug in URL (`/find-a-daycare/zz/nowhere`) returns 404 or empty state

---

## 2. Tenant Self-Registration

### 2.1 Registering a New Business
**Positive**
- [ ] `pawpass.com/register` loads the registration form
- [ ] Filling in business name, owner name, email, password, and slug and submitting:
  - Creates the tenant record
  - Creates the business_owner user
  - Sends a verification email
  - Redirects to `/register/success`
- [ ] The success page has correct messaging
- [ ] Clicking the verification link in the email marks the email as verified and redirects to the admin login

**Negative**
- [ ] Submitting with a slug already in use shows a "slug taken" validation error
- [ ] Submitting with a mismatched password confirmation shows a validation error
- [ ] Submitting with an email already registered shows a validation error
- [ ] Submitting with an invalid slug format (spaces, special characters) shows a validation error
- [ ] Accessing `/register/success` directly without completing registration doesn't crash

---

## 3. Admin Portal — Authentication

### 3.1 Login
**Positive**
- [ ] `{slug}.pawpass.com/admin/login` loads the admin login page
- [ ] Logging in with valid business_owner credentials redirects to `/admin`
- [ ] Logging in with valid staff credentials redirects to `/admin`
- [ ] After login, the user's name is shown in the nav

**Negative**
- [ ] Wrong password shows "Invalid credentials" error
- [ ] Non-existent email shows "Invalid credentials" error (no user enumeration)
- [ ] A customer account trying to log in at `/admin/login` is rejected with an appropriate error
- [ ] Accessing `/admin` while logged out redirects to `/admin/login`
- [ ] After logout (`POST /admin/logout`), accessing `/admin` redirects to login

---

### 3.2 Staff Invite & Onboarding
**Positive**
- [ ] Owner invites a staff member via Settings → Staff → Invite
- [ ] Invite email arrives with a valid link
- [ ] Clicking the link loads the accept-invite page
- [ ] Setting a name and password creates the staff account and logs in
- [ ] Staff user appears as active in Settings → Staff

**Negative**
- [ ] Accessing an invite link that has already been used shows an "invalid or expired" message
- [ ] Accessing an invite link for a different tenant (wrong subdomain) returns 404 or error
- [ ] Staff user attempting to access owner-only pages (Packages, Settings, Billing) is blocked with a 403

---

### 3.3 Email Verification
**Positive**
- [ ] New business_owner receives a verification email
- [ ] Clicking the link marks the user as verified
- [ ] `/admin/verify-email` shows a re-send prompt when accessed with an unverified account

**Negative**
- [ ] An expired or tampered verification link shows an appropriate error (not a crash)

---

## 4. Admin Portal — Dashboard

**Positive**
- [ ] Dashboard loads with today's stats (dogs checked in, revenue, credits issued)
- [ ] Dashboard reflects real-time data (check in a dog, refresh — count increases)

**Negative**
- [ ] Staff with no activity doesn't crash the dashboard (zero state handled)

---

## 5. Admin Portal — Customers

### 5.1 Listing & Viewing
**Positive**
- [ ] `/admin/customers` lists all customers for the tenant
- [ ] Clicking a customer opens their profile with dogs, credits, and orders listed
- [ ] Pagination or scroll works if there are many customers

**Negative**
- [ ] Customer from a different tenant's ID in the URL returns 404 (tenant isolation)
- [ ] Empty customer list shows an empty-state message

---

### 5.2 Creating a Customer
**Positive**
- [ ] `/admin/customers/create` shows the create form
- [ ] Creating with name only (no email) succeeds (email is optional)
- [ ] Creating with name + email + phone succeeds
- [ ] New customer appears immediately in the list

**Negative**
- [ ] Submitting with no name shows a validation error
- [ ] Submitting with a duplicate email shows a validation error
- [ ] Submitting with an invalid phone format shows a validation error

---

### 5.3 Request Payment Update
**Positive**
- [ ] "Request payment update" button on a customer's profile sends them an email/SMS with an update link
- [ ] The toast/notification confirms the message was sent

**Negative**
- [ ] A customer with no email and no phone — the button should be disabled or show an appropriate warning

---

### 5.4 Charge Balance
**Positive**
- [ ] "Charge balance" on a customer with a saved card processes the charge
- [ ] The resulting order appears in Payments

**Negative**
- [ ] Customer with no saved payment method — button is disabled or shows a clear error
- [ ] A declined test card (`4000 0000 0000 0002`) surfaces a user-friendly error, not a crash

---

## 6. Admin Portal — Dogs

### 6.1 Listing & Viewing
**Positive**
- [ ] `/admin/dogs` lists all dogs across all customers for the tenant
- [ ] Dog profile page shows credit balance, attendance history, vaccinations, and ledger
- [ ] Dog photo (if uploaded) displays correctly

**Negative**
- [ ] Dog belonging to another tenant returns 404

---

### 6.2 Creating a Dog
**Positive**
- [ ] `/admin/dogs/create` loads the form with a customer search/select
- [ ] Creating a dog with name, breed, and customer assigned succeeds
- [ ] New dog appears on the customer's profile

**Negative**
- [ ] Submitting with no name returns a validation error
- [ ] Submitting with no customer assigned returns a validation error

---

### 6.3 Editing a Dog
**Positive**
- [ ] Editing name, breed, weight updates the record
- [ ] Uploading a photo stores and displays it

**Negative**
- [ ] Uploading a non-image file (e.g. PDF) shows a validation error
- [ ] Uploading an oversized image shows a validation error

---

### 6.4 Vaccinations
**Positive**
- [ ] Adding a vaccination (name + expiry date) saves and shows in the dog's vaccination list
- [ ] Deleting a vaccination removes it from the list

**Negative**
- [ ] Adding a vaccination with a past expiry date — check whether the system flags it immediately
- [ ] Adding a vaccination with no expiry date (if required) shows a validation error

---

## 7. Admin Portal — Roster (Daily Check-In/Out)

### 7.1 Check In
**Positive**
- [ ] `/admin/roster` lists today's checked-in dogs
- [ ] Checking in a dog with sufficient credits deducts 1 credit and shows the dog on the roster
- [ ] The credit balance on the dog profile updates immediately

**Negative**
- [ ] Checking in a dog with zero credits:
  - Shows a warning or blocks check-in (depending on configured behavior)
  - Does not create a negative balance silently
- [ ] Checking in the same dog twice on the same day: should either block or show a warning
- [ ] Checking in a dog belonging to a different tenant returns 404/403

---

### 7.2 Check Out
**Positive**
- [ ] Checking out a dog removes them from the active roster
- [ ] The attendance record has both check-in and check-out timestamps

**Negative**
- [ ] Checking out a dog that is not checked in returns an appropriate error

---

### 7.3 Attendance Add-ons
**Positive**
- [ ] After check-in, adding an add-on service (e.g. grooming) to an attendance record saves it
- [ ] The add-on is billed correctly (charge appears in payments)
- [ ] Add-ons can be added after check-out (post-checkout add-on flow)

**Negative**
- [ ] Adding an add-on with no price set returns a validation error
- [ ] Deleting an add-on that was already charged updates the record correctly

---

### 7.4 Stale Checkout (Signed URL)
**Positive**
- [ ] The signed stale-checkout URL (sent by AlertStaleCheckins job) loads without login
- [ ] Confirming it checks out the dog and shows a confirmation page

**Negative**
- [ ] A tampered signed URL (modified signature) returns 403
- [ ] An expired signed URL (if applicable) returns 403 or shows an expiry message

---

## 8. Admin Portal — Packages

### 8.1 Listing
**Positive**
- [ ] `/admin/packages` lists all active packages
- [ ] Archived packages are hidden or shown in a separate section

---

### 8.2 Creating a Package (Owner Only)
**Positive**
- [ ] Creating a `one_time` package (e.g. "10-Day Pack" for $100) saves and syncs to Stripe
- [ ] Creating a `subscription` package (e.g. "Monthly Unlimited" for $150/month) saves and syncs to Stripe with a recurring price
- [ ] Creating an `unlimited` package saves **without** creating a Stripe price (no billing attached)
- [ ] New package appears in the list and is available to customers in the portal

**Negative**
- [ ] Creating a package without Stripe Connect onboarding completed shows an appropriate error (blocked by `stripe.onboarded` middleware)
- [ ] Creating a package with a negative price shows a validation error
- [ ] Creating a package with no name shows a validation error
- [ ] Staff (non-owner) attempting to access `/admin/packages/create` is blocked with 403

---

### 8.3 Editing a Package
**Positive**
- [ ] Updating a package name/description updates the record and Stripe product metadata

**Negative**
- [ ] Changing the price of an existing package creates a new Stripe price (old price archived), not silently overwriting

---

### 8.4 Archiving a Package
**Positive**
- [ ] Archiving removes the package from the customer-facing purchase list
- [ ] Historical orders referencing the archived package are unaffected

**Negative**
- [ ] Archiving a package with active subscriptions — confirm behavior (should warn or block)

---

## 9. Admin Portal — Payments & Refunds

### 9.1 Payment List
**Positive**
- [ ] `/admin/payments` lists all orders with amounts and statuses
- [ ] Clicking an order links to the receipt

**Negative**
- [ ] Payments from another tenant's orders don't appear (tenant isolation)

---

### 9.2 Issuing a Refund
**Positive**
- [ ] Refunding a completed order:
  - Processes the refund in Stripe
  - Removes **all** remaining credits from the dog (regardless of refund amount)
  - Order status updates to `refunded`
- [ ] Confirmation toast appears after successful refund

**Negative**
- [ ] Attempting to refund an already-refunded order shows an appropriate error
- [ ] Attempting to refund an order from another tenant returns 404
- [ ] Network timeout during Stripe refund call — the order status should not be changed if Stripe failed

---

### 9.3 Order Receipts
**Positive**
- [ ] Admin receipt page (`/admin/orders/{id}/receipt`) loads and shows full order details
- [ ] Portal receipt page (`/my/orders/{id}/receipt`) is accessible to the customer who placed the order

**Negative**
- [ ] Customer trying to access another customer's receipt returns 403/404

---

## 10. Admin Portal — Credits (Manual Adjustments)

### 10.1 Goodwill Credits
**Positive**
- [ ] Adding goodwill credits to a dog with a note increases the credit balance
- [ ] The credit ledger shows a `goodwill` entry with the note

**Negative**
- [ ] Submitting without a note shows a validation error
- [ ] Submitting with a negative amount shows a validation error (goodwill must be positive)

---

### 10.2 Correction
**Positive**
- [ ] Correction add increases balance; correction remove decreases it
- [ ] Both require and save a note

**Negative**
- [ ] Submitting without a note shows a validation error
- [ ] A correction remove that would make balance negative — check whether it is blocked or allowed

---

### 10.3 Transfer Between Dogs
**Positive**
- [ ] Transferring credits from Dog A to Dog B (same customer) succeeds
- [ ] Dog A balance decreases; Dog B balance increases by the same amount
- [ ] Ledger shows `transfer_out` and `transfer_in` entries

**Negative**
- [ ] Attempting to transfer to a dog belonging to a different customer shows an error
- [ ] Attempting to transfer more credits than available shows an error
- [ ] Attempting to transfer 0 credits shows a validation error

---

## 11. Admin Portal — Boarding

### 11.1 Kennel Units
**Positive**
- [ ] `/admin/boarding/units` lists all kennel units
- [ ] Creating a unit (name, capacity) saves it
- [ ] Editing a unit name updates it
- [ ] Deleting an unused unit removes it

**Negative**
- [ ] Deleting a unit with active reservations shows a blocking error
- [ ] Creating a unit with no name shows a validation error

---

### 11.2 Occupancy View
**Positive**
- [ ] `/admin/boarding/occupancy` shows a calendar or grid of reserved kennel units
- [ ] Occupied units are visually distinct from available units

**Negative**
- [ ] Accessing with no kennel units created shows an empty-state prompt

---

### 11.3 Reservations (Admin View)
**Positive**
- [ ] `/admin/boarding/reservations` lists all upcoming and past reservations
- [ ] Clicking a reservation shows full details: dog, dates, unit, addons, report cards
- [ ] Updating a reservation (change dates, unit assignment) saves correctly
- [ ] Processing checkout on a reservation marks it as completed

**Negative**
- [ ] Updating a reservation to dates that overlap with another reservation in the same unit should show a conflict error
- [ ] Accessing a reservation from another tenant returns 404

---

### 11.4 Report Cards
**Positive**
- [ ] Adding a report card to a reservation saves it (text + optional photo)
- [ ] Report card is visible to the customer in the portal

**Negative**
- [ ] Submitting an empty report card shows a validation error

---

### 11.5 Boarding Add-ons
**Positive**
- [ ] Adding an add-on service to a reservation saves it with the correct price
- [ ] Deleting a boarding add-on removes it

**Negative**
- [ ] Adding a non-existent add-on type returns 404

---

## 12. Admin Portal — Reports (Owner + Paid Plans)

**Positive**
- [ ] `/admin/reports` index page loads with links to all report types
- [ ] Revenue report shows correct totals for the selected date range
- [ ] Packages report shows purchase counts and revenue per package
- [ ] Credits report shows issued/expired/used totals
- [ ] Customers report shows customer acquisition and activity
- [ ] Attendance report shows check-in counts per day/week
- [ ] Credit status report shows dogs grouped by balance level
- [ ] Vaccinations report (plan-gated) shows expiring/expired vaccinations
- [ ] CSV export downloads a valid file with correct headers and data

**Negative**
- [ ] A tenant on the free plan trying to access a plan-gated report is redirected or shown a 403/upgrade prompt
- [ ] A date range with no data returns an empty report (not a crash)
- [ ] An extremely large date range completes in reasonable time (cached)

---

## 13. Admin Portal — Settings

### 13.1 Business Settings (Owner Only)
**Positive**
- [ ] Updating business name, timezone, and phone number saves correctly
- [ ] Timezone change is reflected in report timestamps

**Negative**
- [ ] Staff user accessing `/admin/settings` cannot submit business settings changes (403)

---

### 13.2 Notification Settings
**Positive**
- [ ] Toggling notification types (email on/off, SMS on/off per event) saves preferences
- [ ] Critical notifications (payment.confirmed, credits.empty) are not toggleable — they remain always-on

**Negative**
- [ ] Submitting invalid notification preference values returns a validation error

---

### 13.3 Staff Management (Owner Only)
**Positive**
- [ ] Inviting a staff member sends the invite email
- [ ] Deactivating a staff user prevents them from logging in

**Negative**
- [ ] Inviting with an already-used email shows a validation error
- [ ] Deactivating yourself (the owner) should be blocked or require confirmation

---

### 13.4 Logo Upload
**Positive**
- [ ] Uploading a PNG/JPG logo saves it and displays on the portal and admin header

**Negative**
- [ ] Uploading a file that is too large shows a validation error
- [ ] Uploading a non-image file (e.g. `.exe`) shows a validation error
- [ ] Deleting the logo reverts to the default placeholder

---

### 13.5 Billing Address
**Positive**
- [ ] Updating the billing address saves it and is reflected in the Billing section

---

## 14. Admin Portal — Billing (Owner Only)

### 14.1 Subscribing to a Plan
**Positive**
- [ ] `/admin/billing` shows available plans with pricing
- [ ] Selecting a plan and entering a card creates a Stripe subscription
- [ ] Tenant `status` updates to `active` and `plan` is set
- [ ] Post-subscribe, plan-gated features (e.g. reports, vaccination management) are unlocked

**Negative**
- [ ] A declined card (`4000 0000 0000 0002`) shows a user-friendly payment error
- [ ] Staff user accessing `/admin/billing` cannot subscribe (403)

---

### 14.2 Upgrading a Plan
**Positive**
- [ ] Upgrading from Starter → Pro → Enterprise updates the Stripe subscription and unlocks higher-tier features immediately

**Negative**
- [ ] Attempting to downgrade to a plan below current usage (e.g. fewer dogs) shows a blocking warning

---

### 14.3 Cancelling a Subscription
**Positive**
- [ ] Cancelling sets the subscription to cancel at period end
- [ ] Tenant retains access until the billing period ends
- [ ] After period end, tenant status moves to `canceled` and plan-gated features are removed

**Negative**
- [ ] Cancelling when there is no active subscription returns an appropriate error

---

### 14.4 Stripe Connect Onboarding
**Positive**
- [ ] "Account Session" endpoint returns a valid session for the embedded Stripe component
- [ ] Completing Stripe Express onboarding marks the tenant as onboarded
- [ ] Post-onboarding, the "Create Package" flow is unblocked

**Negative**
- [ ] Accessing package create without completing Stripe onboarding is blocked with an informative message

---

## 15. Admin Portal — Services (Add-on Types)

**Positive**
- [ ] `/admin/services` lists all add-on types
- [ ] Creating a new service (name, price) saves it
- [ ] Editing a service updates name/price
- [ ] Deleting a service with no existing usage removes it

**Negative**
- [ ] Creating a service with no name shows a validation error
- [ ] Creating a service with a negative price shows a validation error
- [ ] Deleting a service that has been used on existing orders shows a blocking error or warns

---

## 16. Admin Portal — Vaccination Requirements

**Positive**
- [ ] `/admin/vaccination-requirements` lists all required vaccines for the tenant
- [ ] Adding a requirement (vaccine name, optional expiry window) saves it
- [ ] Deleting a requirement removes it

**Negative**
- [ ] Adding a requirement with no name shows a validation error

---

## 17. Admin Portal — Promotions

**Positive**
- [ ] `/admin/promotions` lists all promotions
- [ ] Creating a promotion (code, discount type: flat/percent, value, optional expiry) saves it
- [ ] Editing a promotion updates it
- [ ] Deleting an unused promotion removes it
- [ ] A valid promo code applied in the customer portal reduces the purchase price

**Negative**
- [ ] Creating a promotion with a duplicate code shows a validation error
- [ ] A percent discount > 100% shows a validation error
- [ ] An expired promotion code entered in the portal shows a "code expired" error
- [ ] A non-existent promotion code shows a "code not found" error
- [ ] Deleting a promotion that has been redeemed should warn or block

---

## 18. Admin Portal — Broadcast Notifications

**Positive**
- [ ] `/admin/notifications/broadcast` loads the send form
- [ ] Sending an email broadcast to all customers dispatches notifications to each customer
- [ ] Sending an SMS broadcast dispatches SMS notifications (and deducts SMS usage)
- [ ] SMS usage page shows running count against plan limit

**Negative**
- [ ] Submitting with an empty message shows a validation error
- [ ] Sending SMS when at the plan SMS limit shows a blocking error or warns

---

## 19. Admin Portal — Tax

**Positive**
- [ ] `/admin/tax` loads the Stripe Tax embedded component
- [ ] Toggling tax collection on/off saves the preference
- [ ] With tax collection on, the purchase tax-preview in the portal shows a tax line

**Negative**
- [ ] Toggling tax without completing Stripe Connect shows an appropriate error

---

## 20. Customer Portal — Authentication

### 20.1 Registration
**Positive**
- [ ] `{slug}.pawpass.com/my/register` loads the registration form
- [ ] Registering with valid name, email, and password:
  - Creates the customer and user accounts
  - Sends a verification email
  - Redirects to the verify-email prompt

**Negative**
- [ ] Registering with an email already in use shows a validation error
- [ ] Registering with a mismatched password confirmation shows a validation error
- [ ] Accessing `/my/register` on a non-existent subdomain returns a 404 or tenant-not-found page

---

### 20.2 Login & Logout
**Positive**
- [ ] Logging in with valid credentials redirects to `/my` (portal dashboard)
- [ ] Logout clears the session and redirects to `/my/login`

**Negative**
- [ ] Wrong password shows "Invalid credentials"
- [ ] A staff/owner account trying to log in at `/my/login` is rejected

---

### 20.3 Magic Link (Passwordless Login)
**Positive**
- [ ] Requesting a magic link sends an email with a one-time link
- [ ] Clicking the link logs in the customer without entering a password
- [ ] Confirm page (if shown) requires a click to prevent accidental login via email prefetch

**Negative**
- [ ] Clicking a magic link a second time shows "link already used" or redirects to login
- [ ] A magic link for an email not registered on that tenant shows "account not found" error

---

### 20.4 Forgot / Reset Password
**Positive**
- [ ] `/auth/forgot-password` sends a reset email
- [ ] The reset link navigates to a form; setting a new password works and allows login

**Negative**
- [ ] Using an expired reset link shows an appropriate error
- [ ] Entering mismatched passwords on reset shows a validation error

---

## 21. Customer Portal — Dashboard

**Positive**
- [ ] `/my` shows a summary of dogs, credit balances, upcoming boarding, and recent notifications
- [ ] Unread notification count in the nav reflects actual unread count

**Negative**
- [ ] A new customer with no dogs, no purchases, and no reservations sees a proper empty-state welcome screen — not errors

---

## 22. Customer Portal — Dogs

### 22.1 Adding a Dog
**Positive**
- [ ] `/my/dogs/create` allows adding a dog with name, breed, weight, birthday
- [ ] Dog appears in the dog list and on the dashboard

**Negative**
- [ ] Submitting with no name shows a validation error

---

### 22.2 Editing a Dog
**Positive**
- [ ] Editing a dog's name, breed, and other info saves correctly

**Negative**
- [ ] Editing a dog belonging to another customer returns 404

---

### 22.3 Uploading Vaccinations
**Positive**
- [ ] Adding a vaccination record (name, date) appears on the dog's profile

**Negative**
- [ ] Uploading a vaccination with no name shows a validation error

---

### 22.4 Dog Detail Page
**Positive**
- [ ] Dog detail shows credit balance, attendance history, active subscriptions
- [ ] "Cancel subscription" button cancels the active subscription (if present) and shows a confirmation

**Negative**
- [ ] Cancelling a subscription with no active subscription shows an error

---

## 23. Customer Portal — Purchase

### 23.1 Buying a One-Time Package
**Positive**
- [ ] `/my/purchase` lists all active one-time packages
- [ ] Selecting a package and completing Stripe payment:
  - Creates an order with status `paid`
  - Credits are issued to the selected dog
  - Confirmation notification sent (email + in-app)
  - Receipt accessible at `/my/orders/{id}/receipt`

**Negative**
- [ ] Declining card (`4000 0000 0000 0002`) shows payment error — no credits issued
- [ ] Using an expired card shows a card-error message
- [ ] Submitting without selecting a dog shows a validation error
- [ ] Replaying the same Stripe webhook twice only issues credits once (webhook dedup)

---

### 23.2 Applying a Promo Code
**Positive**
- [ ] Entering a valid promo code reduces the displayed price before payment
- [ ] The discount is reflected in the final order total

**Negative**
- [ ] Entering an invalid code shows "Code not found"
- [ ] Entering an expired code shows "Code expired"
- [ ] Entering a code from a different tenant shows "Code not found"

---

### 23.3 Tax Preview
**Positive**
- [ ] When tax collection is enabled, a "Tax" line appears showing the estimated tax amount
- [ ] The final charged amount includes tax

**Negative**
- [ ] Tax preview with an invalid address input returns a graceful error, not a crash

---

### 23.4 Auto-Replenish
**Positive**
- [ ] Customer can enable auto-replenish for a dog (automatically rebuys a package when credits run low)
- [ ] When a dog's credits drop below the threshold, the package is automatically repurchased
- [ ] Customer can cancel auto-replenish from the dog profile

**Negative**
- [ ] Auto-replenish with no saved payment method fails gracefully (dunning notification sent)

---

### 23.5 Subscriptions
**Positive**
- [ ] Selecting a subscription package triggers the SetupIntent flow (card saved, no immediate charge)
- [ ] On the first billing cycle, credits are issued
- [ ] On renewal, subscription webhook fires, new credits are issued, old expiry-eligible credits are expired
- [ ] Customer can cancel a subscription from their dog profile

**Negative**
- [ ] A failed subscription renewal triggers the dunning flow (customer notified, status → past_due)
- [ ] Cancelling a subscription that has already been cancelled shows an error

---

### 23.6 Save Card on File (Recurring Checkout)
**Positive**
- [ ] During purchase, customer can opt-in to save their card
- [ ] The saved card is shown in the account section
- [ ] Future purchases use the saved card without re-entering details

**Negative**
- [ ] If no card is saved and recurring checkout requires one, the customer is prompted to add a card

---

## 24. Customer Portal — Boarding Reservations

### 24.1 Creating a Reservation
**Positive**
- [ ] `/my/boarding/create` shows available kennel units and a date picker
- [ ] Selecting dates, a dog, and a unit and submitting creates the reservation
- [ ] Reservation appears in `/my/boarding` with status `pending`
- [ ] Confirmation notification is sent

**Negative**
- [ ] Selecting dates that are already fully booked shows an availability error
- [ ] Selecting check-out before check-in shows a validation error
- [ ] Submitting without selecting a dog shows a validation error

---

### 24.2 Viewing & Cancelling a Reservation
**Positive**
- [ ] Reservation detail page shows all info: dates, unit, add-ons, report cards
- [ ] Cancelling a future reservation updates status to `cancelled`

**Negative**
- [ ] Cancelling a reservation that has already started (or is past) shows a blocking error
- [ ] Accessing another customer's reservation returns 404

---

## 25. Customer Portal — Purchase History

**Positive**
- [ ] `/my/history` lists all past orders with amounts and dates
- [ ] Clicking an order links to its receipt

**Negative**
- [ ] A customer with no orders sees an empty-state message (not an error)

---

## 26. Customer Portal — Attendance History

**Positive**
- [ ] `/my/attendance` lists all past check-in/check-out records for all dogs
- [ ] Each record shows the date and any add-ons charged

**Negative**
- [ ] A customer with no attendance history sees an empty-state message

---

## 27. Customer Portal — Notifications

**Positive**
- [ ] `/my/notifications` lists all in-app notifications in reverse-chronological order
- [ ] Unread notifications are visually distinct
- [ ] Clicking "Mark as read" on a notification updates it
- [ ] "Mark all as read" clears the unread count in the nav

**Negative**
- [ ] Marking a non-existent notification as read returns 404
- [ ] Attempting to mark another customer's notification as read returns 403/404

---

## 28. Customer Portal — Account Settings

**Positive**
- [ ] `/my/account` shows the customer's name, email, phone
- [ ] Updating name/phone saves correctly
- [ ] Updating notification preferences (email/SMS opt-in/out) saves correctly

**Negative**
- [ ] Saving with a blank name shows a validation error
- [ ] Saving with an email already used by another account shows a validation error

---

## 29. Platform Admin (`platform.pawpass.com`)

### 29.1 Tenant Management
**Positive**
- [ ] Platform admin can view all tenants with status, plan, and created date
- [ ] Viewing a tenant shows full details and audit log
- [ ] Platform admin can update a tenant's plan or status
- [ ] Soft-deleting a tenant disables their subdomain

**Negative**
- [ ] A non-platform-admin user accessing `/api/platform/v1/*` gets a 403
- [ ] Accessing a non-existent tenant ID returns 404

---

### 29.2 Platform Plans
**Positive**
- [ ] Platform admin can create, edit, and sync plans to Stripe
- [ ] Plan changes propagate correctly to tenant gates

---

### 29.3 Audit Log
**Positive**
- [ ] All platform admin actions (tenant mutations, plan changes) appear in the audit log with actor + timestamp

---

### 29.4 Feature Overrides
**Positive**
- [ ] Platform admin can override a feature flag for a specific tenant (enable/disable regardless of plan)

---

## 30. Notifications (End-to-End)

| Notification | Trigger | Channel(s) | Verify |
|---|---|---|---|
| Payment confirmed | Successful Stripe payment | Email + in-app | Check inbox + `/my/notifications` |
| Credits low | Balance drops below threshold | Email + SMS + in-app | Check inbox, phone, portal |
| Credits empty | Balance hits zero | Email + SMS + in-app | Check inbox, phone, portal |
| Subscription renewed | Invoice paid webhook | Email + in-app | Check inbox |
| Subscription failed | Invoice payment failed | Email + SMS + in-app | Check inbox |
| Vaccination expiring soon | Background job (30-day warning) | Email + in-app | Trigger job manually in tinker |
| Vaccination expiring urgent | Background job (7-day warning) | Email + SMS + in-app | Trigger job manually in tinker |
| Trial expiring | Background job | Email + in-app | Trigger job manually |
| Stale check-in | Background job (AlertStaleCheckins) | Email with signed URL | Trigger job manually |
| Boarding report card | Admin adds report card | Email + in-app | Check inbox |

**Negative**
- [ ] Disabling email notifications for an event type prevents that email (except critical events)
- [ ] A customer with no email does not cause a crash — notification skips silently
- [ ] A customer with no phone does not cause an SMS crash
- [ ] Duplicate webhook events (replayed) do not double-send notifications

---

## 31. Background Jobs (Manual Trigger via Tinker)

For each job, trigger via `php artisan tinker` → `dispatch(new JobClass())` and verify:

| Job | What to verify |
|---|---|
| `ExpireSubscriptionCredits` | Credits past expiry window are removed; `expiry_removal` ledger entries created |
| `ExpireTrials` | Tenants past trial period move to `expired` status |
| `ProcessDunning` | Tenants past_due past grace period are suspended |
| `AlertStaleCheckins` | Sends signed URL email for dogs checked in >12 hours |
| `CancelStalePendingOrders` | Pending orders older than threshold are cancelled |
| `PruneDispatchedPending` | Old dispatched notifications pruned |
| `PruneOldNotifications` | In-app notifications older than retention period deleted |
| `PruneRawWebhooks` | Webhook records older than 7 days are deleted |
| `SendTrialExpirationWarnings` | Tenants approaching trial end receive warning email |
| `SendUpgradeNudges` | Tenants on free tier receive nudge email |
| `SendVaccinationExpiringSoonWarnings` | 30-day warnings sent for expiring vaccinations |
| `SendVaccinationExpiringUrgentWarnings` | 7-day urgent warnings sent |
| `WarmTenantReportCaches` | Report cache populated; subsequent report loads are fast |
| `WarmPlatformReportCaches` | Platform report cache populated |

**Negative for all jobs:**
- [ ] Running a job twice (e.g. `ExpireSubscriptionCredits`) does not double-process (idempotency)
- [ ] A job failure (simulated by bad DB state) retries up to 3 times then lands in failed_jobs table

---

## 32. Stripe Webhooks

**Use the Stripe CLI to replay events:**
```bash
stripe trigger payment_intent.succeeded
stripe trigger invoice.payment_succeeded
stripe trigger invoice.payment_failed
stripe trigger setup_intent.succeeded
```

**Positive**
- [ ] `payment_intent.succeeded` → credits issued to dog, order marked paid
- [ ] `invoice.payment_succeeded` → subscription credits issued (or renewed)
- [ ] `invoice.payment_failed` → tenant/customer flagged, dunning notification sent
- [ ] `setup_intent.succeeded` → card-on-file saved to customer record

**Negative**
- [ ] Replaying the same event ID a second time returns 200 but does not reprocess (dedup via unique event_id)
- [ ] A webhook with an invalid Stripe signature returns 400
- [ ] A webhook for an unknown event type is stored as raw but not processed (no crash)

---

## 33. Multi-Tenancy Isolation

These checks verify that tenant data never leaks:

- [ ] Logged in as a customer of Tenant A, manually change a dog URL to a dog ID from Tenant B → returns 404
- [ ] Logged in as staff of Tenant A, manually change a customer URL to Tenant B's customer ID → returns 404
- [ ] Admin API JWT token for Tenant A cannot be used against Tenant B's API endpoints
- [ ] Platform admin API with no tenant in JWT can read all tenants but not inject tenant-scoped data
- [ ] Report data shown to Tenant A never contains records from Tenant B

---

## 34. Session & Security

- [ ] CSRF token is required on all POST/PATCH/DELETE web routes — removing it returns 419
- [ ] Auth cookies use `HttpOnly` and `Secure` flags
- [ ] JWT access tokens expire after 15 minutes — a 15-minute-old token on an API call returns 401
- [ ] Refresh token flow issues a new access token correctly
- [ ] Rate limiting: 60 requests/min on portal/admin API — 61st request returns 429

---

## 35. Responsive / UX Smoke Tests

- [ ] All admin pages render correctly at 1280px, 1440px
- [ ] Customer portal renders correctly on mobile (375px)
- [ ] Navigation menus collapse/expand correctly on mobile
- [ ] Form validation errors are shown inline (not just as a flash message)
- [ ] Toast/success messages disappear after a few seconds and do not stack infinitely
- [ ] Loading states are shown while async operations (Stripe, API calls) are in progress

---

## Sign-Off Checklist

Before deploying to production:

- [ ] All sections above tested with no P0/P1 issues open
- [ ] Stripe Connect account is in **live mode**
- [ ] Stripe Billing account is in **live mode**
- [ ] All webhook secrets updated to live mode secrets
- [ ] DNS records for `pawpass.com`, `*.pawpass.com`, and `platform.pawpass.com` verified
- [ ] SSL certificates valid for all subdomains
- [ ] Queue worker is running in production (Supervisor or similar)
- [ ] Scheduler (`php artisan schedule:run`) is configured in cron
- [ ] Error monitoring (Sentry or equivalent) is active and receiving test events
- [ ] Log aggregation is capturing application logs
- [ ] Database backups are scheduled and tested with a restore
- [ ] `APP_ENV=production`, `APP_DEBUG=false` in production `.env`
